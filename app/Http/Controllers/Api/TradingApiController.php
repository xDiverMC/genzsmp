<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvestAction;
use App\Models\InvestLimitOrder;
use App\Models\InvestPortfolio;
use App\Models\InvestPriceAlert;
use App\Models\InvestTrade;
use App\Models\InvestTransfer;
use App\Models\InvestUser;
use App\Services\InvestMarketEngine;
use App\Services\MinecraftRconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // Load portfolios with special rules for Dzakiri (+75% profit guaranteed) & Lucky Surge (+75%-125% Puncak Hijau)
        $currentPrices = \App\Services\InvestMarketEngine::getCurrentPrices();
        $isDzakiri = strtolower($playerName) === 'dzakiri';
        $luckySurge = \App\Services\InvestMarketEngine::getLuckySurgeState();
        $isLuckySurge = ($luckySurge['active'] && strtolower($playerName) === strtolower($luckySurge['player_name'] ?? ''));

        $portfolios = $user->portfolios()->get()->keyBy(function ($item) {
            return strtolower($item->asset);
        })->map(function ($item) use ($currentPrices, $isDzakiri, $isLuckySurge, $luckySurge) {
            $amt = (float) $item->amount;
            $avgBuy = (float) $item->avg_buy_price;
            $spot = (float) ($currentPrices[strtoupper($item->asset)] ?? 100.0);

            if ($isDzakiri && $amt > 0) {
                // Guaranteed VIP profit: +75% to +140%
                $maxAvgBuy = round($spot / 1.75, 2);
                $avgBuy = min($avgBuy, $maxAvgBuy);
            } elseif ($isLuckySurge && $amt > 0) {
                // Golden Bull Surge (30 Menit Puncak Hijau)
                $maxAvgBuy = round($spot / $luckySurge['multiplier'], 2);
                $avgBuy = min($avgBuy, $maxAvgBuy);
            }

            return [$amt, $avgBuy];
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
     * Get uniform server-wide market data (candlestick chart, 24h stats, spot prices).
     */
    public function getMarketData(Request $request): JsonResponse
    {
        $timeframe = $request->input('timeframe', '5m');
        if (!in_array($timeframe, ['1m', '5m', '15m', '1h', '1d'])) {
            $timeframe = '5m';
        }

        $marketData = InvestMarketEngine::getMarketData($timeframe);

        return response()->json([
            'success' => true,
            'data' => $marketData
        ]);
    }

    /**
     * Execute a Market BUY or SELL order with 6-Digit PIN Verification.
     */
    public function executeTrade(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_name' => 'required|string',
            'pin' => 'required|string|digits:6',
            'trade_type' => 'required|in:BUY,SELL',
            'asset' => 'required|string',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'price' => 'nullable|numeric|min:0.01',
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

        // Fetch live server spot price
        $currentPrices = InvestMarketEngine::getCurrentPrices();
        $spotPrice = (float) ($currentPrices[$assetSymbol] ?? $request->input('price', 100.0));

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
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

        if (Cache::has($lockKey)) {
            $lockExpiry = Cache::get($lockKey);
            $remainingMins = max(1, ceil(($lockExpiry - time()) / 60));
            return response()->json([
                'success' => false,
                'message' => "Akun Anda terkunci sementara karena 5x salah PIN. Coba lagi dalam {$remainingMins} menit."
            ], 429);
        }

        // 3. Verify PIN
        if (!$user->verifyPin($pin)) {
            $attempts = Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(15));

            if ($attempts >= 5) {
                Cache::put($lockKey, time() + (15 * 60), now()->addMinutes(15));
                Cache::forget($attemptsKey);
                return response()->json([
                    'success' => false,
                    'message' => 'PIN salah 5 kali berturut-turut! Akun Anda dikunci selama 15 menit demi keamanan.'
                ], 429);
            }

            $remaining = 5 - $attempts;
            return response()->json([
                'success' => false,
                'message' => "PIN trading salah! Sisa percobaan: {$remaining} kali."
            ], 401);
        }

        // Reset failed attempts on success
        Cache::forget($attemptsKey);

        // 4. Cooldown Check (5 seconds)
        $cdKey = "trade_cd:{$user->id}";
        if (Cache::has($cdKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Harap tunggu 5 detik antar transaksi (Anti-Whale Cooldown).'
            ], 429);
        }
        Cache::put($cdKey, true, now()->addSeconds(5));

        // 5. Calculate Financials (8% All Assets)
        $subtotal = $amount * $spotPrice;
        $taxRate = 0.08;
        $tax = $subtotal * $taxRate;

        // Get Portfolio
        $portfolio = InvestPortfolio::firstOrCreate(
            ['invest_user_id' => $user->id, 'asset' => $assetSymbol],
            ['player_name' => $user->player_name, 'amount' => 0, 'avg_buy_price' => 0]
        );

        if ($tradeType === 'BUY') {
            $totalCost = $subtotal + $tax;

            // Check cash balance
            if ($user->cash_balance < $totalCost) {
                return response()->json([
                    'success' => false,
                    'message' => "Saldo kas Vault tidak mencukupi! Anda membutuhkan $" . number_format($totalCost, 2) . " (termasuk pajak {$taxRate}*100%), namun saldo Anda hanya $" . number_format($user->cash_balance, 2)
                ], 400);
            }

            // Deduct local database cash balance
            $user->cash_balance -= $totalCost;
            $user->save();

            // Update portfolio (VIP Whale Cost Basis Engine for Dzakiri & Lucky Surge)
            $isDzakiri = strtolower($playerName) === 'dzakiri';
            $luckySurge = \App\Services\InvestMarketEngine::getLuckySurgeState();
            $isLuckySurge = ($luckySurge['active'] && strtolower($playerName) === strtolower($luckySurge['player_name'] ?? ''));

            $effectiveBuyPrice = $spotPrice;
            if ($isDzakiri) {
                $effectiveBuyPrice = ($spotPrice / 2.40);
            } elseif ($isLuckySurge) {
                $effectiveBuyPrice = ($spotPrice / $luckySurge['multiplier']);
            }

            $prevAmount = (float) $portfolio->amount;
            $prevAvg = (float) $portfolio->avg_buy_price;
            $newAmount = $prevAmount + $amount;
            $newAvg = ($newAmount > 0) ? (($prevAmount * $prevAvg) + ($amount * $effectiveBuyPrice)) / $newAmount : $effectiveBuyPrice;

            $portfolio->amount = $newAmount;
            $portfolio->avg_buy_price = $newAvg;
            $portfolio->save();

            // Queue action for in-game Vault withdrawal
            InvestAction::create([
                'player_name' => $user->player_name,
                'action_type' => 'WITHDRAW',
                'amount' => $totalCost,
                'reason' => "Web Trade Beli {$amount} {$assetSymbol} (@ $" . number_format($spotPrice, 2) . ")",
                'status' => 'PENDING'
            ]);

            // Record trade
            $trade = InvestTrade::create([
                'player_name' => $user->player_name,
                'trade_type' => 'BUY',
                'asset' => $assetSymbol,
                'amount' => $amount,
                'price' => $spotPrice,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $totalCost
            ]);

            return response()->json([
                'success' => true,
                'message' => "Order BUY Berhasil! Anda membeli {$amount} {$assetSymbol} senilai $" . number_format($totalCost, 2) . " (Pajak: $" . number_format($tax, 2) . ")",
                'data' => [
                    'new_cash_balance' => (float) $user->cash_balance,
                    'portfolio' => [
                        strtolower($assetSymbol) => [$portfolio->amount, $portfolio->avg_buy_price]
                    ],
                    'trade' => $trade
                ]
            ]);

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

            // Target mriz loss setting (active for 1 day)
            if (strtolower($playerName) === 'mriz') {
                $netPayout = $netPayout * 0.40; // 60% loss penalty
            }

            // Update portfolio
            $portfolio->amount = max(0, $ownedAmount - $amount);
            if ($portfolio->amount <= 0.0001) {
                $portfolio->amount = 0;
                $portfolio->avg_buy_price = 0;
            }
            $portfolio->save();

            // Add cash to user
            $user->cash_balance += $netPayout;
            $user->save();

            // Queue action for in-game Vault deposit
            InvestAction::create([
                'player_name' => $user->player_name,
                'action_type' => 'DEPOSIT',
                'amount' => $netPayout,
                'reason' => "Web Trade Jual {$amount} {$assetSymbol} (@ $" . number_format($spotPrice, 2) . ")",
                'status' => 'PENDING'
            ]);

            // Record trade
            $trade = InvestTrade::create([
                'player_name' => $user->player_name,
                'trade_type' => 'SELL',
                'asset' => $assetSymbol,
                'amount' => $amount,
                'price' => $spotPrice,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $netPayout
            ]);

            return response()->json([
                'success' => true,
                'message' => "Order SELL Berhasil! Anda menjual {$amount} {$assetSymbol}. Saldo masuk: $" . number_format($netPayout, 2) . " (Dipungut pajak protocol: $" . number_format($tax, 2) . ")",
                'data' => [
                    'new_cash_balance' => (float) $user->cash_balance,
                    'portfolio' => [
                        strtolower($assetSymbol) => [$portfolio->amount, $portfolio->avg_buy_price]
                    ],
                    'trade' => $trade
                ]
            ]);
        }
    }

    /**
     * Create a Limit Order (Limit Buy / Limit Sell).
     */
    public function createLimitOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_name' => 'required|string',
            'pin' => 'required|string|digits:6',
            'order_type' => 'required|in:BUY,SELL',
            'asset' => 'required|string',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'target_price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Input data limit order tidak valid atau PIN bukan 6 digit.',
                'errors' => $validator->errors()
            ], 422);
        }

        $playerName = trim($request->input('player_name'));
        $pin = $request->input('pin');
        $orderType = strtoupper($request->input('order_type'));
        $assetSymbol = strtoupper($request->input('asset'));
        $amount = (float) $request->input('amount');
        $targetPrice = (float) $request->input('target_price');

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        if (!$user->verifyPin($pin)) {
            return response()->json(['success' => false, 'message' => 'PIN Keamanan Trading salah.'], 401);
        }

        $taxRate = 0.08;
        $subtotal = $amount * $targetPrice;
        $tax = $subtotal * $taxRate;
        $reservedCost = $subtotal + $tax;

        $portfolio = InvestPortfolio::firstOrCreate(
            ['invest_user_id' => $user->id, 'asset' => $assetSymbol],
            ['player_name' => $user->player_name, 'amount' => 0, 'avg_buy_price' => 0]
        );

        if ($orderType === 'BUY') {
            if ($user->cash_balance < $reservedCost) {
                return response()->json([
                    'success' => false,
                    'message' => "Saldo kas Vault tidak cukup untuk Limit Buy! Dibutuhkan: $" . number_format($reservedCost, 2) . " (termasuk pajak {$taxRate}*100%)."
                ], 400);
            }

            // Reserve cash
            $user->cash_balance -= $reservedCost;
            $user->save();

            // Queue withdraw in-game for safety
            InvestAction::create([
                'player_name' => $user->player_name,
                'action_type' => 'WITHDRAW',
                'amount' => $reservedCost,
                'reason' => "Limit Order Reserve: Beli {$amount} {$assetSymbol} @ $" . number_format($targetPrice, 2),
                'status' => 'PENDING'
            ]);

        } else {
            // SELL
            if ($portfolio->amount < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => "Jumlah aset {$assetSymbol} tidak mencukupi untuk Limit Sell! Anda hanya memiliki {$portfolio->amount} {$assetSymbol}."
                ], 400);
            }

            // Lock asset amount
            $portfolio->amount -= $amount;
            if ($portfolio->amount <= 0.0001) {
                $portfolio->amount = 0;
            }
            $portfolio->save();
        }

        $order = InvestLimitOrder::create([
            'invest_user_id' => $user->id,
            'player_name' => $user->player_name,
            'asset' => $assetSymbol,
            'order_type' => $orderType,
            'amount' => $amount,
            'target_price' => $targetPrice,
            'reserved_cost' => ($orderType === 'BUY') ? $reservedCost : 0,
            'status' => 'PENDING'
        ]);

        return response()->json([
            'success' => true,
            'message' => "Limit Order {$orderType} dipasang! Order akan dieksekusi otomatis saat harga menyentuh $" . number_format($targetPrice, 2),
            'data' => [
                'order' => $order,
                'new_cash_balance' => (float) $user->cash_balance,
                'portfolio' => [
                    strtolower($assetSymbol) => [$portfolio->amount, $portfolio->avg_buy_price]
                ]
            ]
        ]);
    }

    /**
     * Get active limit orders for player.
     */
    public function getLimitOrders(Request $request): JsonResponse
    {
        $playerName = trim($request->input('player_name'));
        $orders = InvestLimitOrder::where('player_name', $playerName)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Cancel a pending limit order and refund reserved funds/assets.
     */
    public function cancelLimitOrder(Request $request): JsonResponse
    {
        $playerName = trim($request->input('player_name'));
        $orderId = (int) $request->input('order_id');

        $order = InvestLimitOrder::where('id', $orderId)
            ->whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])
            ->where('status', 'PENDING')
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Limit order aktif tidak ditemukan.'], 404);
        }

        $user = InvestUser::find($order->invest_user_id);
        if (!$user) {
            $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        }

        DB::transaction(function () use ($order, $user) {
            if ($order->order_type === 'BUY') {
                // Refund reserved cash
                $user->cash_balance += $order->reserved_cost;
                $user->save();

                InvestAction::create([
                    'player_name' => $user->player_name,
                    'action_type' => 'DEPOSIT',
                    'amount' => $order->reserved_cost,
                    'reason' => "Batal Limit Order: Refund $" . number_format($order->reserved_cost, 2),
                    'status' => 'PENDING'
                ]);
            } else {
                // Refund reserved asset
                $portfolio = InvestPortfolio::firstOrCreate(
                    ['invest_user_id' => $user->id, 'asset' => $order->asset],
                    ['player_name' => $user->player_name, 'amount' => 0, 'avg_buy_price' => 0]
                );
                $portfolio->amount += $order->amount;
                $portfolio->save();
            }

            $order->status = 'CANCELLED';
            $order->save();
        });

        return response()->json([
            'success' => true,
            'message' => "Limit order #{$orderId} berhasil dibatalkan dan dana/aset dikembalikan!"
        ]);
    }

    /**
     * P2P Asset Transfer endpoint via Web Terminal.
     */
    public function transferAsset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sender_name' => 'required|string',
            'receiver_name' => 'required|string',
            'pin' => 'required|string|digits:6',
            'asset' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Input transfer tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $senderName = trim($request->input('sender_name'));
        $receiverName = trim($request->input('receiver_name'));
        $pin = $request->input('pin');
        $asset = strtoupper(trim($request->input('asset')));
        $amount = (float) $request->input('amount');

        if (strtolower($senderName) === strtolower($receiverName)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mentransfer ke diri sendiri.'], 400);
        }

        $sender = InvestUser::where('player_name', $senderName)->first();
        if (!$sender || !$sender->verifyPin($pin)) {
            return response()->json(['success' => false, 'message' => 'PIN Keamanan Trading pengirim salah.'], 401);
        }

        $senderPortfolio = InvestPortfolio::where('invest_user_id', $sender->id)->where('asset', $asset)->first();
        if (!$senderPortfolio || $senderPortfolio->amount < $amount) {
            $have = $senderPortfolio ? (float) $senderPortfolio->amount : 0;
            return response()->json(['success' => false, 'message' => "Aset {$asset} tidak cukup! Anda hanya memiliki {$have} {$asset}."], 400);
        }

        $receiver = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($receiverName)])->first();
        if (!$receiver) {
            $receiver = InvestUser::findOrCreateByName($receiverName);
        }

        $feeRate = 0.02; // 2% protocol transfer tax
        $fee = round($amount * $feeRate, 4);
        $receivedAmount = $amount - $fee;

        $spotPrice = InvestMarketEngine::getCurrentPrices()[$asset] ?? 100.0;

        DB::transaction(function () use ($sender, $receiver, $senderPortfolio, $asset, $amount, $fee, $receivedAmount, $spotPrice) {
            // Deduct sender
            $senderPortfolio->amount = max(0, $senderPortfolio->amount - $amount);
            if ($senderPortfolio->amount <= 0.0001) {
                $senderPortfolio->amount = 0;
                $senderPortfolio->avg_buy_price = 0;
            }
            $senderPortfolio->save();

            // Add receiver
            $receiverPortfolio = InvestPortfolio::firstOrCreate(
                ['invest_user_id' => $receiver->id, 'asset' => $asset],
                ['player_name' => $receiver->player_name, 'amount' => 0, 'avg_buy_price' => 0]
            );

            $prevAmount = (float) $receiverPortfolio->amount;
            $prevAvg = (float) $receiverPortfolio->avg_buy_price;
            $newAmount = $prevAmount + $receivedAmount;
            $newAvg = ($newAmount > 0) ? (($prevAmount * $prevAvg) + ($receivedAmount * $spotPrice)) / $newAmount : $spotPrice;

            $receiverPortfolio->amount = $newAmount;
            $receiverPortfolio->avg_buy_price = $newAvg;
            $receiverPortfolio->save();

            InvestTransfer::create([
                'sender_name' => $sender->player_name,
                'receiver_name' => $receiver->player_name,
                'asset' => $asset,
                'amount' => $amount,
                'fee' => $fee,
                'received_amount' => $receivedAmount
            ]);

            InvestTrade::create([
                'player_name' => $sender->player_name,
                'trade_type' => 'TRANSFER_OUT',
                'asset' => $asset,
                'amount' => $amount,
                'price' => $spotPrice,
                'subtotal' => $amount * $spotPrice,
                'tax' => $fee * $spotPrice,
                'total' => $amount * $spotPrice
            ]);

            InvestTrade::create([
                'player_name' => $receiver->player_name,
                'trade_type' => 'TRANSFER_IN',
                'asset' => $asset,
                'amount' => $receivedAmount,
                'price' => $spotPrice,
                'subtotal' => $receivedAmount * $spotPrice,
                'tax' => 0,
                'total' => $receivedAmount * $spotPrice
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Transfer {$amount} {$asset} ke {$receiver->player_name} berhasil! (Penerima dapat: {$receivedAmount} {$asset}, Pajak 2%: {$fee} {$asset})",
            'data' => [
                'portfolio' => [
                    strtolower($asset) => [$senderPortfolio->amount, $senderPortfolio->avg_buy_price]
                ]
            ]
        ]);
    }

    /**
     * Create Price Alert endpoint.
     */
    public function createPriceAlert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_name' => 'required|string',
            'asset' => 'required|string',
            'target_price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Input alert tidak valid.'], 422);
        }

        $playerName = trim($request->input('player_name'));
        $asset = strtoupper(trim($request->input('asset')));
        $targetPrice = (float) $request->input('target_price');

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        $currentPrices = InvestMarketEngine::getCurrentPrices();
        $currentPrice = $currentPrices[$asset] ?? 100.0;
        $condition = ($targetPrice >= $currentPrice) ? 'ABOVE' : 'BELOW';

        $alert = InvestPriceAlert::create([
            'invest_user_id' => $user->id,
            'player_name' => $user->player_name,
            'asset' => $asset,
            'target_price' => $targetPrice,
            'condition' => $condition,
            'initial_price' => $currentPrice,
            'is_triggered' => false
        ]);

        $condText = ($condition === 'ABOVE') ? "naik di atas atau sama dengan" : "turun di bawah atau sama dengan";

        return response()->json([
            'success' => true,
            'message' => "Price Alert dipasang! Notifikasi akan muncul saat {$asset} {$condText} $" . number_format($targetPrice, 2),
            'data' => $alert
        ]);
    }

    /**
     * Get active price alerts for player.
     */
    public function getPriceAlerts(Request $request): JsonResponse
    {
        $playerName = trim($request->input('player_name'));
        $alerts = InvestPriceAlert::where('player_name', $playerName)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
    }

    /**
     * Cancel Price Alert.
     */
    public function cancelPriceAlert(Request $request): JsonResponse
    {
        $playerName = trim($request->input('player_name'));
        $alertId = (int) $request->input('alert_id');

        $deleted = InvestPriceAlert::where('id', $alertId)
            ->where('player_name', $playerName)
            ->delete();

        if ($deleted) {
            return response()->json(['success' => true, 'message' => "Price Alert berhasil dihapus."]);
        } else {
            return response()->json(['success' => false, 'message' => "Price Alert tidak ditemukan."], 404);
        }
    }

    /**
     * Get Top 10 Investors Leaderboard.
     */
    public function getLeaderboard(): JsonResponse
    {
        $prices = InvestMarketEngine::getCurrentPrices();
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
