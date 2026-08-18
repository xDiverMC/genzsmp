<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvestPortfolio;
use App\Models\InvestTrade;
use App\Models\InvestUser;
use App\Services\MinecraftRconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TradingApiController extends Controller
{
    protected MinecraftRconService $rconService;

    public function __construct(MinecraftRconService $rconService)
    {
        $this->rconService = $rconService;
    }
    /**
     * Login or get player account details by username.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_name' => 'required|string|min:2|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Format nama player tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $playerName = trim($request->input('player_name'));
        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'USER_NOT_REGISTERED',
                'message' => "Akun Gamertag '{$playerName}' belum terdaftar di sistem! Silakan login ke in-game server Minecraft (genzsmp.site) dan ketik: /invest setpin <6-digit> terlebih dahulu."
            ], 404);
        }

        $user->update(['last_login_at' => now()]);

        // Load portfolios
        $portfolios = $user->portfolios()->get()->keyBy(function ($item) {
            return strtolower($item->asset);
        })->map(function ($item) {
            return [$item->amount, $item->avg_buy_price];
        });

        // Load recent trades
        $recentTrades = InvestTrade::where('player_name', $user->player_name)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'player_name' => $user->player_name,
                    'uuid' => $user->uuid,
                    'is_bedrock' => $user->is_bedrock,
                    'cash_balance' => (float) $user->cash_balance,
                    'has_pin' => $user->hasPin(),
                ],
                'portfolio' => $portfolios,
                'trades' => $recentTrades,
            ]
        ]);
    }

    /**
     * Execute a BUY or SELL order with 6-Digit PIN Verification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function executeTrade(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_name' => 'required|string',
            'pin' => 'required|string|digits:6',
            'trade_type' => 'required|in:BUY,SELL',
            'asset' => 'required|string',
            'amount' => 'required|numeric|min:0.01|max:1000',
            'price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Input data transaksi tidak valid atau PIN bukan 6 digit.',
                'errors' => $validator->errors()
            ], 422);
        }

        $playerName = trim($request->input('player_name'));
        $pin = $request->input('pin');
        $tradeType = strtoupper($request->input('trade_type'));
        $assetSymbol = strtoupper($request->input('asset'));
        $amount = (float) $request->input('amount');
        $spotPrice = (float) $request->input('price');

        $user = InvestUser::where('player_name', $playerName)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun player tidak ditemukan. Silakan login kembali.'
            ], 404);
        }

        // 1. Check if user has PIN set
        if (!$user->hasPin()) {
            return response()->json([
                'success' => false,
                'error_code' => 'PIN_NOT_SET',
                'message' => 'Akun Anda belum memiliki PIN Keamanan Trading! Silakan masuk ke server Minecraft dan ketik: /invest setpin <6-digit>'
            ], 403);
        }

        // 2. Verify 6-digit PIN
        if (!$user->verifyPin($pin)) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_PIN',
                'message' => 'PIN Keamanan Salah! Pastikan memasukkan 6-digit PIN in-game yang benar.'
            ], 403);
        }

        // 3. Process BUY / SELL Financial Logic
        $subtotal = $amount * $spotPrice;
        $tax = $subtotal * 0.02; // 2% protocol burn tax

        $portfolio = InvestPortfolio::firstOrCreate(
            ['invest_user_id' => $user->id, 'asset' => $assetSymbol],
            ['player_name' => $playerName, 'amount' => 0, 'avg_buy_price' => 0]
        );

        if ($tradeType === 'BUY') {
            $totalCost = $subtotal + $tax;
            if ($user->cash_balance < $totalCost) {
                return response()->json([
                    'success' => false,
                    'message' => "Saldo kas Vault tidak mencukupi! Dibutuhkan $" . number_format($totalCost, 2) . " (termasuk tax 2%). Saldo Anda: $" . number_format($user->cash_balance, 2)
                ], 400);
            }

            // Deduct cash balance
            $user->cash_balance -= $totalCost;
            $user->save();

            // Update portfolio
            $prevAmount = (float) $portfolio->amount;
            $prevAvg = (float) $portfolio->avg_buy_price;
            $newAmount = $prevAmount + $amount;
            $newAvg = ($newAmount > 0) ? (($prevAmount * $prevAvg) + ($amount * $spotPrice)) / $newAmount : $spotPrice;

            $portfolio->amount = $newAmount;
            $portfolio->avg_buy_price = $newAvg;
            $portfolio->save();

        } else {
            // SELL
            $ownedAmount = (float) $portfolio->amount;
            if ($ownedAmount < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => "Jumlah aset tidak mencukupi untuk dijual! Anda hanya memiliki {$ownedAmount} {$assetSymbol}."
                ], 400);
            }

            $netPayout = $subtotal - $tax;

            // Credit cash balance
            $user->cash_balance += $netPayout;
            $user->save();

            // Deduct portfolio
            $portfolio->amount -= $amount;
            if ($portfolio->amount <= 0.0001) {
                $portfolio->amount = 0;
                $portfolio->avg_buy_price = 0;
            }
            $portfolio->save();
        }

        // 4. Record in trade history
        $finalTotal = ($tradeType === 'BUY') ? ($subtotal + $tax) : ($subtotal - $tax);
        $trade = InvestTrade::create([
            'player_name' => $playerName,
            'trade_type' => $tradeType,
            'asset' => $assetSymbol,
            'amount' => $amount,
            'price' => $spotPrice,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $finalTotal
        ]);

        // Load fresh portfolios
        $updatedPortfolios = $user->portfolios()->get()->keyBy(function ($item) {
            return strtolower($item->asset);
        })->map(function ($item) {
            return [$item->amount, $item->avg_buy_price];
        });

        // 5. Notify player in-game if online via RCON
        try {
            $this->rconService->notifyTradeInGame(
                $playerName,
                $tradeType,
                $assetSymbol,
                $amount,
                $spotPrice,
                $tax,
                $finalTotal,
                (float) $user->cash_balance
            );
        } catch (\Throwable $e) {
            // Player may be offline or RCON unreachable; non-blocking
        }

        return response()->json([
            'success' => true,
            'message' => "Transaksi {$tradeType} {$amount} {$assetSymbol} berhasil dieksekusi!",
            'data' => [
                'trade' => $trade,
                'new_balance' => (float) $user->cash_balance,
                'portfolio' => $updatedPortfolios
            ]
        ]);
    }

    /**
     * In-Game / RCON setpin sync endpoint.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_name' => 'required|string',
            'pin' => 'required|string|digits:6',
            'old_pin' => 'nullable|string|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'PIN harus terdiri dari tepat 6 digit angka numerik (0-9).',
                'errors' => $validator->errors()
            ], 422);
        }

        $playerName = trim($request->input('player_name'));
        $pin = $request->input('pin');
        $oldPin = $request->input('old_pin');

        $user = InvestUser::findOrCreateByName($playerName);

        if ($user->hasPin() && !empty($oldPin)) {
            if (!$user->verifyPin($oldPin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN lama salah!'
                ], 403);
            }
        }

        $user->setPin($pin);

        return response()->json([
            'success' => true,
            'message' => "PIN Keamanan 6-digit untuk player {$playerName} berhasil diatur!",
            'has_pin' => true
        ]);
    }

    /**
     * Get live state of a player.
     *
     * @param string $playerName
     * @return JsonResponse
     */
    public function getUserState(string $playerName): JsonResponse
    {
        $user = InvestUser::where('player_name', trim($playerName))->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Player tidak ditemukan'
            ], 404);
        }

        $portfolios = $user->portfolios()->get()->keyBy(function ($item) {
            return strtolower($item->asset);
        })->map(function ($item) {
            return [$item->amount, $item->avg_buy_price];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'player_name' => $user->player_name,
                'cash_balance' => (float) $user->cash_balance,
                'has_pin' => $user->hasPin(),
                'portfolio' => $portfolios
            ]
        ]);
    }
}
