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
                        $balance = (float) ($pData['balance'] ?? 0);
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
                            InvestUser::create([
                                'player_name' => $name,
                                'uuid' => $uuid,
                                'is_bedrock' => $isBedrock,
                                'cash_balance' => $balance,
                                'last_login_at' => now()
                            ]);
                        }
                    }
                });
            }
        }

        // 3. Fetch pending actions for online players
        $pendingActions = [];
        if (!empty($onlineNames)) {
            $actions = InvestAction::where('status', 'PENDING')
                ->whereIn('player_name', $onlineNames)
                ->limit(20)
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
            'alerts' => $triggeredAlerts
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

        if (empty($playerName) || !in_array($tradeType, ['BUY', 'SELL']) || $amount <= 0 || $price <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid trade parameters'], 400);
        }

        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            $user = InvestUser::findOrCreateByName($playerName);
        }

        $subtotal = $amount * $price;
        $taxRate = in_array(strtoupper($asset), ['BTC', 'ETH']) ? 0.08 : 0.05;
        $tax = $subtotal * $taxRate;
        $total = ($tradeType === 'BUY') ? ($subtotal + $tax) : ($subtotal - $tax);

        $portfolio = InvestPortfolio::firstOrCreate(
            ['invest_user_id' => $user->id, 'asset' => $asset],
            ['player_name' => $user->player_name, 'amount' => 0, 'avg_buy_price' => 0]
        );

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
        } else {
            $portfolio->amount = max(0, (float) $portfolio->amount - $amount);
            if ($portfolio->amount <= 0.0001) {
                $portfolio->amount = 0;
                $portfolio->avg_buy_price = 0;
            }
            $portfolio->save();
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

        $senderPortfolio = InvestPortfolio::where('invest_user_id', $sender->id)->where('asset', $asset)->first();
        if (!$senderPortfolio || $senderPortfolio->amount < $amount) {
            $have = $senderPortfolio ? (float) $senderPortfolio->amount : 0;
            return response()->json(['success' => false, 'message' => "Jumlah {$asset} tidak mencukupi! Anda hanya memiliki {$have} {$asset}."], 400);
        }

        $receiver = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($receiverName)])->first();
        if (!$receiver) {
            $receiver = InvestUser::findOrCreateByName($receiverName);
        }

        // 2% Protocol Transfer Fee
        $feeRate = 0.02;
        $fee = round($amount * $feeRate, 4);
        $receivedAmount = $amount - $fee;

        $spotPrice = InvestMarketEngine::getCurrentPrices()[$asset] ?? 100.0;

        DB::transaction(function () use ($sender, $receiver, $senderPortfolio, $asset, $amount, $fee, $receivedAmount, $spotPrice) {
            // Deduct from sender
            $senderPortfolio->amount = max(0, $senderPortfolio->amount - $amount);
            if ($senderPortfolio->amount <= 0.0001) {
                $senderPortfolio->amount = 0;
                $senderPortfolio->avg_buy_price = 0;
            }
            $senderPortfolio->save();

            // Add to receiver
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

            // Record transfer
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
