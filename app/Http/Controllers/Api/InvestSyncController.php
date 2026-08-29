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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvestSyncController extends Controller
{
    protected string $serverKey;

    public function __construct()
    {
        $this->serverKey = config('minecraft.rcon.password', 'genz12#admin972Stecu%$!');
    }

    /**
     * Verify server secret key.
     */
    protected function verifyKey(Request $request): bool
    {
        $key = $request->header('X-Server-Key') ?? $request->header('x-server-key') ?? $request->input('server_key') ?? $request->input('secret_key');
        $expected = config('minecraft.rcon.password') ?? env('MINECRAFT_RCON_PASSWORD', 'genz12#admin972Stecu%$!');
        return !empty($key) && (trim((string) $key) === trim((string) $expected));
    }

    /**
     * 2-Second Periodic Sync between Minecraft Plugin and Web Server.
     * Synchronizes online player balances, fetches pending Web Trade actions,
     * supplies uniform live server market prices, and delivers triggered price alerts.
     */
    public function sync(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        // 1. Mark completed actions
        $completedIds = $request->input('completed_actions', []);
        if (!empty($completedIds) && is_array($completedIds)) {
            InvestAction::whereIn('id', $completedIds)->update(['status' => 'COMPLETED']);
        }

        // 2. Update real in-game Vault cash balance for online players in a single transaction
        $players = $request->input('players', []);
        $onlineNames = [];

        if (is_array($players) && !empty($players)) {
            $namesMap = [];
            foreach ($players as $pData) {
                $name = trim($pData['name'] ?? '');
                if (!empty($name)) {
                    $namesMap[strtolower($name)] = $pData;
                    $onlineNames[] = $name;
                }
            }

            if (!empty($namesMap)) {
                DB::transaction(function () use ($namesMap) {
                    $existingUsers = InvestUser::whereIn('player_name', array_column($namesMap, 'name'))
                        ->get()
                        ->keyBy(fn($u) => strtolower($u->player_name));

                    foreach ($namesMap as $lowerName => $pData) {
                        $name = $pData['name'];
                        $rawBalance = (float) ($pData['balance'] ?? 0);
                        $balance = max(0, min(is_finite($rawBalance) ? $rawBalance : 0, 1000000000.0));
                        $uuid = $pData['uuid'] ?? null;
                        $isBedrock = (bool) ($pData['is_bedrock'] ?? str_starts_with($name, '.'));

                        if (isset($existingUsers[$lowerName])) {
                            $user = $existingUsers[$lowerName];
                            if (abs($user->cash_balance - $balance) > 0.001 || ($uuid && $user->uuid !== $uuid)) {
                                $user->cash_balance = $balance;
                                if ($uuid) $user->uuid = $uuid;
                                $user->is_bedrock = $isBedrock;
                                $user->last_login_at = now();
                                $user->save();
                            }
                        } else {
                            $newUser = new InvestUser();
                            $newUser->player_name = $name;
                            $newUser->uuid = $uuid;
                            $newUser->is_bedrock = $isBedrock;
                            $newUser->cash_balance = $balance;
                            $newUser->last_login_at = now();
                            $newUser->save();
                        }
                    }
                });
            }
        }

        // 3. Fetch pending actions for online players
        $pendingActions = [];
        if (!empty($onlineNames)) {
            $actions = InvestAction::where('status', 'PENDING')
                ->whereIn(DB::raw('LOWER(player_name)'), array_map('strtolower', $onlineNames))
                ->limit(30)
                ->get();

            $pendingActions = $actions->map(function ($act) {
                return [
                    'id' => $act->id,
                    'player' => $act->player_name,
                    'type' => $act->action_type, // WITHDRAW, DEPOSIT
                    'amount' => (float) $act->amount,
                    'reason' => $act->reason ?? 'Web Trade Transaction'
                ];
            });
        }

        // 4. Uniform Server Asset spot prices from InvestMarketEngine
        $prices = InvestMarketEngine::getCurrentPrices();

        // 5. Pop triggered price alerts for online players
        $triggeredAlerts = [];
        $alertQueue = Cache::get('invest_triggered_alerts_queue', []);
        if (!empty($alertQueue) && !empty($onlineNames)) {
            $lowerOnline = array_map('strtolower', $onlineNames);
            $remaining = [];
            foreach ($alertQueue as $item) {
                if (in_array(strtolower($item['player']), $lowerOnline)) {
                    $triggeredAlerts[] = $item;
                } else {
                    $remaining[] = $item;
                }
            }
            Cache::put('invest_triggered_alerts_queue', $remaining, now()->addMinutes(10));
        }

        return response()->json([
            'success' => true,
            'timestamp' => time(),
            'prices' => $prices,
            'actions' => $pendingActions,
            'alerts' => $triggeredAlerts,
            'lucky_surge' => InvestMarketEngine::getLuckySurgeState()
        ]);
    }

    /**
     * In-Game /invest setpin <pin> endpoint.
     */
    public function setPin(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        $playerName = trim($request->input('player'));
        $pin = trim($request->input('pin'));

        if (empty($playerName) || strlen($pin) !== 6 || !ctype_digit($pin)) {
            return response()->json(['success' => false, 'message' => 'PIN harus 6 digit angka numerik.'], 400);
        }

        $weakPins = ['123456', '654321', '000000', '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999', '123123', '112233', '121212'];
        if (in_array($pin, $weakPins)) {
            return response()->json(['success' => false, 'message' => 'PIN terlalu mudah ditebak! Harap gunakan kombinasi PIN yang lebih aman dan unik.'], 400);
        }

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            $user = InvestUser::findOrCreateByName($playerName);
        }

        $user->setPin($pin);

        return response()->json([
            'success' => true,
            'message' => "PIN trading 6-digit berhasil diatur untuk akun {$playerName}!"
        ]);
    }

    /**
     * In-Game /invest buy or sell trade sync endpoint.
     */
    public function inGameTrade(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        $playerName = trim($request->input('player'));
        $tradeType = strtoupper(trim($request->input('type'))); // BUY, SELL
        $asset = strtoupper(trim($request->input('asset')));
        $amount = (float) $request->input('amount');
        $price = (float) $request->input('price');

        $validAssets = ['BTC', 'ETH', 'GLD', 'DIA', 'EMD'];
        if (empty($playerName) || !in_array($tradeType, ['BUY', 'SELL']) || !in_array($asset, $validAssets) || $amount <= 0 || $price <= 0) {
            return response()->json(['success' => false, 'message' => "Parameter trade tidak valid atau aset '{$asset}' tidak terdaftar di bursa."], 400);
        }

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            $user = InvestUser::findOrCreateByName($playerName);
        }

        $portfolio = InvestPortfolio::firstOrCreate(
            ['invest_user_id' => $user->id, 'asset' => $asset],
            ['player_name' => $user->player_name, 'amount' => 0, 'avg_buy_price' => 0]
        );

        if ($tradeType === 'SELL') {
            $currentOwned = (float) $portfolio->amount;
            if ($currentOwned < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => "Aset {$asset} Anda tidak mencukupi! Anda hanya memiliki " . number_format($currentOwned, 2) . " {$asset}."
                ], 400);
            }

            $portfolio->amount = max(0, $currentOwned - $amount);
            if ($portfolio->amount <= 0.0001) {
                $portfolio->amount = 0;
                $portfolio->avg_buy_price = 0;
            }
            $portfolio->save();
        }

        $subtotal = $amount * $price;
        $taxRate = 0.12; // 12% Protocol Trade Tax
        $tax = $subtotal * $taxRate;
        $total = ($tradeType === 'BUY') ? ($subtotal + $tax) : ($subtotal - $tax);

        if ($tradeType === 'BUY') {
            $isDzakiri = strtolower($playerName) === 'dzakiri';
            $effectiveBuyPrice = $isDzakiri ? ($price / 2.40) : $price;

            $prevAmount = (float) $portfolio->amount;
            $prevAvg = (float) $portfolio->avg_buy_price;
            $newAmount = $prevAmount + $amount;
            $newAvg = ($newAmount > 0) ? (($prevAmount * $prevAvg) + ($amount * $effectiveBuyPrice)) / $newAmount : $effectiveBuyPrice;

            $portfolio->amount = $newAmount;
            $portfolio->avg_buy_price = $newAvg;
            $portfolio->save();

            // Apply dynamic upward buying price impact on the market chart
            \App\Services\InvestMarketEngine::applyBuyPriceImpact($asset, $amount);
        }

        InvestTrade::create([
            'player_name' => $user->player_name,
            'trade_type' => $tradeType,
            'asset' => $asset,
            'amount' => $amount,
            'price' => $price,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total
        ]);

        return response()->json([
            'success' => true,
            'message' => "Trade {$tradeType} {$amount} {$asset} berhasil disinkronisasi ke web!"
        ]);
    }

    /**
     * In-Game P2P Asset Transfer (/invest transfer <receiver> <asset> <amount>)
     */
    public function inGameTransfer(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        $senderName = trim($request->input('sender'));
        $receiverName = trim($request->input('receiver'));
        $asset = strtoupper(trim($request->input('asset')));
        $amount = (float) $request->input('amount');
        $authenticatedPlayer = trim($request->input('authenticated_player', $senderName));

        // SECURITY: Verify the authenticated player matches the sender (IDOR Prevention)
        if (strtolower($authenticatedPlayer) !== strtolower($senderName)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mentransfer dari akun orang lain!'
            ], 403);
        }

        if (strtolower($senderName) === strtolower($receiverName)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak dapat mentransfer aset ke akun Anda sendiri!'], 400);
        }

        if ($amount < 0.01) {
            return response()->json(['success' => false, 'message' => 'Jumlah transfer minimal 0.01 unit.'], 400);
        }

        $sender = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($senderName)])->first();
        if (!$sender) {
            return response()->json(['success' => false, 'message' => 'Akun pengirim belum terdaftar di sistem investasi.'], 404);
        }

        $receiver = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($receiverName)])->first();
        if (!$receiver) {
            return response()->json(['success' => false, 'message' => "Pemain penerima '{$receiverName}' tidak terdaftar di server Minecraft!"], 404);
        }

        // 2% Protocol Transfer Fee
        $feeRate = 0.02;
        $fee = round($amount * $feeRate, 4);
        $receivedAmount = $amount - $fee;

        $spotPrice = InvestMarketEngine::getCurrentPrices()[$asset] ?? 100.0;

        try {
            DB::transaction(function () use ($sender, $receiver, $asset, $amount, $fee, $receivedAmount, $spotPrice) {
                // Deduct from sender with pessimistic lock
                $lockedSenderPort = InvestPortfolio::where('invest_user_id', $sender->id)
                    ->where('asset', $asset)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedSenderPort || $lockedSenderPort->amount < $amount) {
                    $have = $lockedSenderPort ? (float) $lockedSenderPort->amount : 0;
                    throw new \Exception("Jumlah {$asset} tidak mencukupi! Anda hanya memiliki {$have} {$asset}.");
                }

                $lockedSenderPort->amount = max(0, $lockedSenderPort->amount - $amount);
                if ($lockedSenderPort->amount <= 0.0001) {
                    $lockedSenderPort->amount = 0;
                    $lockedSenderPort->avg_buy_price = 0;
                }
                $lockedSenderPort->save();

                // Add to receiver with pessimistic lock
                $lockedReceiverPort = InvestPortfolio::where('invest_user_id', $receiver->id)
                    ->where('asset', $asset)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedReceiverPort) {
                    $lockedReceiverPort = InvestPortfolio::create([
                        'invest_user_id' => $receiver->id,
                        'player_name' => $receiver->player_name,
                        'asset' => $asset,
                        'amount' => 0,
                        'avg_buy_price' => 0
                    ]);
                }

                $prevAmount = (float) $lockedReceiverPort->amount;
                $prevAvg = (float) $lockedReceiverPort->avg_buy_price;
                $newAmount = $prevAmount + $receivedAmount;
                $newAvg = ($newAmount > 0) ? (($prevAmount * $prevAvg) + ($receivedAmount * $spotPrice)) / $newAmount : $spotPrice;

                $lockedReceiverPort->amount = $newAmount;
                $lockedReceiverPort->avg_buy_price = $newAvg;
                $lockedReceiverPort->save();

                // Record transfer
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
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Transfer {$amount} {$asset} ke {$receiver->player_name} berhasil! (Penerima menerima {$receivedAmount} {$asset}, Fee 2%: {$fee} {$asset})"
        ]);
    }

    /**
     * In-Game /invest alert <asset> <price> endpoint.
     */
    public function inGameSetAlert(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        $playerName = trim($request->input('player'));
        $asset = strtoupper(trim($request->input('asset')));
        $targetPrice = (float) $request->input('target_price');

        if (empty($playerName) || $targetPrice <= 0 || !in_array($asset, ['BTC', 'ETH', 'GLD', 'DIA', 'EMD'])) {
            return response()->json(['success' => false, 'message' => 'Parameter alert tidak valid.'], 400);
        }

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            $user = InvestUser::findOrCreateByName($playerName);
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
            'alert_id' => $alert->id,
            'message' => "Price Alert dipasang! Anda akan diberi tahu saat {$asset} {$condText} $" . number_format($targetPrice, 2)
        ]);
    }

    /**
     * In-Game /invest alerts list endpoint.
     */
    public function inGameListAlerts(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        $playerName = trim($request->input('player'));
        $alerts = InvestPriceAlert::where('player_name', $playerName)
            ->where('is_triggered', false)
            ->get();

        return response()->json([
            'success' => true,
            'alerts' => $alerts
        ]);
    }

    /**
     * In-Game /invest alert remove <id> endpoint.
     */
    public function inGameRemoveAlert(Request $request): JsonResponse
    {
        if (!$this->verifyKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized Server Key'], 403);
        }

        $playerName = trim($request->input('player'));
        $alertId = (int) $request->input('alert_id');

        $deleted = InvestPriceAlert::where('id', $alertId)
            ->where('player_name', $playerName)
            ->delete();

        if ($deleted) {
            return response()->json(['success' => true, 'message' => "Price Alert #{$alertId} berhasil dihapus."]);
        } else {
            return response()->json(['success' => false, 'message' => "Price Alert #{$alertId} tidak ditemukan."], 404);
        }
    }
}
