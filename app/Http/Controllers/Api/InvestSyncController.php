<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvestAction;
use App\Models\InvestPortfolio;
use App\Models\InvestTrade;
use App\Models\InvestUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Synchronizes online player balances and fetches pending Web Trade actions.
     *
     * @param Request $request
     * @return JsonResponse
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
                \Illuminate\Support\Facades\DB::transaction(function () use ($namesMap) {
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

        // 4. Asset spot prices
        $prices = [
            'BTC' => 1020.00,
            'ETH' => 510.00,
            'GLD' => 105.00,
            'DIA' => 245.00,
            'EMD' => 175.00
        ];

        return response()->json([
            'success' => true,
            'timestamp' => time(),
            'prices' => $prices,
            'actions' => $pendingActions
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
        $tradeType = strtoupper(trim($request->input('trade_type')));
        $asset = strtoupper(trim($request->input('asset')));
        $amount = (float) $request->input('amount');
        $price = (float) $request->input('price');

        if (empty($playerName) || !in_array($tradeType, ['BUY', 'SELL']) || $amount <= 0 || $price <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid parameters'], 400);
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
            'message' => "Transaksi {$tradeType} {$amount} {$asset} berhasil disinkronisasi ke web!",
            'portfolio' => [
                'amount' => (float) $portfolio->amount,
                'avg_price' => (float) $portfolio->avg_buy_price
            ]
        ]);
    }
}
