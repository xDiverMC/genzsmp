<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RconLog;
use App\Services\MinecraftRconService;
use App\Services\MinecraftServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MinecraftController extends Controller
{
    protected MinecraftServerService $serverService;
    protected MinecraftRconService $rconService;

    public function __construct(
        MinecraftServerService $serverService,
        MinecraftRconService $rconService
    ) {
        $this->serverService = $serverService;
        $this->rconService = $rconService;
    }

    /**
     * Get real-time server status, online player count, latency and MOTD.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $force = $request->boolean('force', false);
        $status = $this->serverService->getStatus($force);

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Handle Store Checkout submission.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function submitCheckout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gamertag' => 'required|string|min:2|max:32',
            'item_name' => 'required|string|max:100',
            'edition' => 'required|string|in:Java,Bedrock,Java & Bedrock',
            'price' => 'required|string|max:50',
            'payment_method' => 'required|string|max:50',
            'date' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi form gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $gamertag = trim($request->input('gamertag'));
        $itemName = trim($request->input('item_name'));
        $edition = $request->input('edition');
        $price = $request->input('price');
        $payment = $request->input('payment_method');
        $date = $request->input('date', now()->format('d-m-Y'));

        // Determine item type
        $itemType = 'other';
        if (str_contains(strtolower($itemName), 'money')) {
            $itemType = 'money';
        } elseif (str_contains(strtolower($itemName), 'skill')) {
            $itemType = 'skill';
        } else {
            $itemType = 'rank';
        }

        // Save order to database
        $order = Order::create([
            'gamertag' => $gamertag,
            'edition' => $edition,
            'item_name' => $itemName,
            'item_type' => $itemType,
            'price' => $price,
            'payment_method' => $payment,
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        // Generate WhatsApp format message
        $formattedMessage = "Halo, saya mau beli:\n\n"
            . "Tipe : {$itemName}\n"
            . "Tanggal : {$date}\n"
            . "Gamertag : {$gamertag}\n"
            . "Java/Bedrock : {$edition}\n"
            . "Harga : {$price}\n"
            . "Pembayaran : {$payment}";

        $ownerWa = config('minecraft.server.whatsapp_owner', 'https://wa.me/6283132172199');
        $ownerPhone = '6283132172199';
        $waUrl = "https://api.whatsapp.com/send?phone={$ownerPhone}&text=" . urlencode($formattedMessage);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dicatat!',
            'data' => [
                'order_id' => $order->id,
                'gamertag' => $gamertag,
                'item_name' => $itemName,
                'price' => $price,
                'format_text' => $formattedMessage,
                'whatsapp_url' => $waUrl,
            ]
        ]);
    }

    /**
     * Execute RCON command (Protected by Admin Key).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function executeRcon(Request $request): JsonResponse
    {
        $adminKey = $request->header('X-Admin-Key') ?? $request->input('admin_key');
        $expectedKey = config('minecraft.rcon.admin_key', 'genzsmp_secret_rcon_2026');

        if ($adminKey !== $expectedKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid Admin Key'
            ], 403);
        }

        $command = $request->input('command');
        if (empty($command)) {
            return response()->json([
                'success' => false,
                'message' => 'Perintah command RCON tidak boleh kosong'
            ], 400);
        }

        $result = $this->rconService->sendCommand($command);

        // Log audit
        RconLog::create([
            'command' => $command,
            'response' => $result['response'] ?? $result['error'],
            'success' => $result['success'],
            'ip_address' => $request->ip(),
            'executed_by' => 'admin_api',
        ]);

        return response()->json($result);
    }

    /**
     * Give Rank directly via RCON (Admin Action).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function giveRank(Request $request): JsonResponse
    {
        $adminKey = $request->header('X-Admin-Key') ?? $request->input('admin_key');
        if ($adminKey !== config('minecraft.rcon.admin_key')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $player = $request->input('player');
        $rank = $request->input('rank');

        if (empty($player) || empty($rank)) {
            return response()->json(['success' => false, 'message' => 'Player and Rank are required'], 400);
        }

        $result = $this->rconService->giveRank($player, $rank);

        RconLog::create([
            'command' => "GIVE_RANK {$player} {$rank}",
            'response' => json_encode($result),
            'success' => $result['success'],
            'ip_address' => $request->ip(),
            'executed_by' => 'admin_rank',
        ]);

        return response()->json($result);
    }

    /**
     * Give Money directly via RCON (Admin Action).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function giveMoney(Request $request): JsonResponse
    {
        $adminKey = $request->header('X-Admin-Key') ?? $request->input('admin_key');
        if ($adminKey !== config('minecraft.rcon.admin_key')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $player = $request->input('player');
        $amount = (float) $request->input('amount');

        if (empty($player) || $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Player and valid positive Amount are required'], 400);
        }

        $result = $this->rconService->giveMoney($player, $amount);

        RconLog::create([
            'command' => "GIVE_MONEY {$player} {$amount}",
            'response' => json_encode($result),
            'success' => $result['success'],
            'ip_address' => $request->ip(),
            'executed_by' => 'admin_money',
        ]);

        return response()->json($result);
    }

    /**
     * Give Item directly via RCON (Admin Action).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function giveItem(Request $request): JsonResponse
    {
        $adminKey = $request->header('X-Admin-Key') ?? $request->input('admin_key');
        if ($adminKey !== config('minecraft.rcon.admin_key')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $player = $request->input('player');
        $item = $request->input('item');
        $amount = (int) $request->input('amount', 1);

        if (empty($player) || empty($item)) {
            return response()->json(['success' => false, 'message' => 'Player and Item are required'], 400);
        }

        $result = $this->rconService->giveItem($player, $item, $amount);

        RconLog::create([
            'command' => "GIVE_ITEM {$player} {$item} {$amount}",
            'response' => json_encode($result),
            'success' => $result['success'],
            'ip_address' => $request->ip(),
            'executed_by' => 'admin_item',
        ]);

        return response()->json($result);
    }
}
