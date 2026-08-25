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
            $user = InvestUser::findOrCreateByName($playerName);
        }

        // Try live Vault balance sync via RCON if reachable
        try {
            $liveBalance = $this->rconService->getPlayerBalance($playerName);
            if ($liveBalance !== null && $liveBalance >= 0) {
                $user->cash_balance = $liveBalance;
            }
        } catch (\Throwable $e) {
            // Non-blocking if RCON server offline
        }

        $user->update(['last_login_at' => now(), 'cash_balance' => $user->cash_balance]);

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

        // 1. Check if user has PIN set or set it on first trade
        if (!$user->hasPin()) {
            if (preg_match('/^[0-9]{6}$/', $pin)) {
                $user->setPin($pin);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PIN_NOT_SET',
                    'message' => 'Akun Anda belum memiliki PIN Keamanan Trading! Masukkan 6 angka numerik untuk menetapkan PIN Anda.'
                ], 403);
            }
        }

        // 2. Anti-Brute-Force PIN Lockout Check (Max 5 attempts -> 15 min lock)
        $lockKey = "trading_pin_lock:{$user->id}";
        $attemptsKey = "trading_pin_attempts:{$user->id}";

        if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
            $lockExpiry = \Illuminate\Support\Facades\Cache::get($lockKey);
            $remainingSeconds = max(0, $lockExpiry - time());
            $remainingMinutes = max(1, ceil($remainingSeconds / 60));
            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_LOCKED',
                'message' => "Akun Anda terkunci sementara karena salah PIN 5x berturut-turut. Silakan coba lagi dalam {$remainingMinutes} menit."
            ], 429);
        }

        // 3. Verify 6-digit PIN
        if (!$user->verifyPin($pin)) {
            $attempts = (int) \Illuminate\Support\Facades\Cache::get($attemptsKey, 0) + 1;
            \Illuminate\Support\Facades\Cache::put($attemptsKey, $attempts, now()->addMinutes(15));

            if ($attempts >= 5) {
                \Illuminate\Support\Facades\Cache::put($lockKey, time() + 900, now()->addMinutes(15));
                \Illuminate\Support\Facades\Cache::forget($attemptsKey);
                return response()->json([
                    'success' => false,
                    'error_code' => 'ACCOUNT_LOCKED',
                    'message' => 'PIN salah 5 kali berturut-turut! Transaksi akun dikunci selama 15 menit demi keamanan.'
                ], 429);
            }

            $remainingAttempts = 5 - $attempts;
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_PIN',
                'message' => "PIN Keamanan Salah! Sisa percobaan: {$remainingAttempts}x sebelum akun dikunci sementara."
            ], 403);
        }

        // Reset failed attempts on success
        \Illuminate\Support\Facades\Cache::forget($attemptsKey);
        \Illuminate\Support\Facades\Cache::forget($lockKey);

        // 3. Process BUY / SELL Financial Logic
        $subtotal = $amount * $spotPrice;
        $taxRate = in_array(strtoupper($assetSymbol), ['BTC', 'ETH']) ? 0.08 : 0.05;
        $tax = $subtotal * $taxRate;
        $taxDisplayPercent = (int) ($taxRate * 100);

        $portfolio = InvestPortfolio::firstOrCreate(
            ['invest_user_id' => $user->id, 'asset' => $assetSymbol],
            ['player_name' => $playerName, 'amount' => 0, 'avg_buy_price' => 0]
        );

        if ($tradeType === 'BUY') {
            $totalCost = $subtotal + $tax;
            if ($user->cash_balance < $totalCost) {
                return response()->json([
                    'success' => false,
                    'message' => "Saldo kas Vault tidak mencukupi! Dibutuhkan $" . number_format($totalCost, 2) . " (termasuk tax {$taxDisplayPercent}%). Saldo Anda: $" . number_format($user->cash_balance, 2)
                ], 400);
            }

            // 1. Queue in-game Vault withdrawal for ArqoInvest Java plugin HTTP sync
            \App\Models\InvestAction::create([
                'player_name' => $user->player_name,
                'action_type' => 'WITHDRAW',
                'amount' => $totalCost,
                'reason' => "Web Trade Beli {$amount} {$assetSymbol} (@ $" . number_format($spotPrice, 2) . ")",
                'status' => 'PENDING'
            ]);

            // Deduct local database cash balance
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

            // 1. Queue in-game Vault deposit for ArqoInvest Java plugin HTTP sync
            \App\Models\InvestAction::create([
                'player_name' => $user->player_name,
                'action_type' => 'DEPOSIT',
                'amount' => $netPayout,
                'reason' => "Web Trade Jual {$amount} {$assetSymbol} (@ $" . number_format($spotPrice, 2) . ")",
                'status' => 'PENDING'
            ]);

            // Credit local database cash balance
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

        // Try live Vault balance sync via RCON if reachable
        try {
            $liveBalance = $this->rconService->getPlayerBalance($user->player_name);
            if ($liveBalance !== null && $liveBalance >= 0) {
                $user->cash_balance = $liveBalance;
                $user->save();
            }
        } catch (\Throwable $e) {
            // Non-blocking if RCON server offline
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

    /**
     * Get Top 10 Investors Leaderboard Hall of Fame.
     *
     * @return JsonResponse
     */
    public function getLeaderboard(): JsonResponse
    {
        $prices = [
            'BTC' => 1020.00,
            'ETH' => 510.00,
            'GLD' => 105.00,
            'DIA' => 245.00,
            'EMD' => 175.00
        ];

        $users = InvestUser::with('portfolios')->get();
        $totalAssetMarketCap = 0.0;
        $activeInvestorsCount = 0;

        $leaderboard = $users->map(function ($u) use ($prices, &$totalAssetMarketCap, &$activeInvestorsCount) {
            $assetsValue = 0.0;
            $holdings = [];

            foreach ($u->portfolios as $p) {
                $sym = strtoupper($p->asset);
                $amt = (float) $p->amount;
                $spot = $prices[$sym] ?? 100.0;
                $val = $amt * $spot;
                $assetsValue += $val;
                if ($amt > 0) {
                    $holdings[$sym] = $amt;
                }
            }

            $cash = (float) $u->cash_balance;
            $netWorth = $cash + $assetsValue;
            $totalAssetMarketCap += $assetsValue;

            $tradeCount = InvestTrade::where('player_name', $u->player_name)->count();

            if ($assetsValue > 0 || $tradeCount > 0) {
                $activeInvestorsCount++;
            }

            // Tier Badge based on Asset Holdings & Investment Scale
            if ($assetsValue >= 1000000 || $netWorth >= 5000000) {
                $badge = 'WHALE 🐋';
                $badgeColor = 'purple';
            } elseif ($assetsValue >= 200000 || $netWorth >= 1000000) {
                $badge = 'SHARK 🦈';
                $badgeColor = 'cyan';
            } elseif ($assetsValue >= 50000 || $netWorth >= 200000) {
                $badge = 'DOLPHIN 🐬';
                $badgeColor = 'emerald';
            } else {
                $badge = 'FISH 🐟';
                $badgeColor = 'neutral';
            }

            return [
                'player_name' => $u->player_name,
                'is_bedrock' => $u->is_bedrock,
                'badge' => $badge,
                'badge_color' => $badgeColor,
                'cash_balance' => $cash,
                'assets_value' => $assetsValue,
                'total_net_worth' => $netWorth,
                'holdings' => $holdings,
                'total_trades' => $tradeCount,
                'avatar_url' => 'https://mc-heads.net/avatar/' . urlencode(ltrim($u->player_name, '.')) . '/64'
            ];
        })
        ->filter(function ($item) {
            return $item['assets_value'] > 0 || $item['total_trades'] > 0;
        })
        ->sort(function ($a, $b) {
            if ($b['assets_value'] !== $a['assets_value']) {
                return $b['assets_value'] <=> $a['assets_value'];
            }
            if ($b['total_trades'] !== $a['total_trades']) {
                return $b['total_trades'] <=> $a['total_trades'];
            }
            return $b['total_net_worth'] <=> $a['total_net_worth'];
        })
        ->values();

        // Assign ranking positions
        $topList = $leaderboard->take(10)->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_investors' => $activeInvestorsCount > 0 ? $activeInvestorsCount : $users->count(),
                'total_market_cap' => $totalAssetMarketCap,
                'top_investors' => $topList
            ]
        ]);
    }
}
