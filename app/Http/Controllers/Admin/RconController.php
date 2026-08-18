<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RconLog;
use App\Services\MinecraftRconService;
use App\Services\MinecraftServerService;
use Illuminate\Http\Request;

class RconController extends Controller
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
     * Display Admin RCON Console and Order Management.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $status = $this->serverService->getStatus(true);
        $recentLogs = RconLog::latest()->take(30)->get();
        $recentOrders = Order::latest()->take(30)->get();
        $ranks = config('minecraft.ranks');

        return view('admin.rcon', compact('status', 'recentLogs', 'recentOrders', 'ranks'));
    }

    /**
     * Execute RCON command from Admin Console.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute(Request $request)
    {
        $command = trim($request->input('command'));
        if (empty($command)) {
            return back()->with('error', 'Command tidak boleh kosong');
        }

        $result = $this->rconService->sendCommand($command);

        RconLog::create([
            'command' => $command,
            'response' => $result['response'] ?? $result['error'],
            'success' => $result['success'],
            'ip_address' => $request->ip(),
            'executed_by' => 'admin_web_ui',
        ]);

        if ($result['success']) {
            return back()->with('success', 'Command berhasil dieksekusi: ' . ($result['response'] ?: '(No output returned)'));
        } else {
            return back()->with('error', 'Gagal mengeksekusi command: ' . $result['error']);
        }
    }

    /**
     * Quick deliver order via RCON.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deliverOrder(Request $request, int $id)
    {
        $order = Order::findOrFail($id);
        $result = null;

        if ($order->item_type === 'rank') {
            // Find matching rank group
            $rankName = strtolower($order->item_name);
            $result = $this->rconService->giveRank($order->gamertag, $rankName);
        } elseif ($order->item_type === 'money') {
            // Parse amount
            preg_match('/\d+[\.,]?\d*/', str_replace('.', '', $order->item_name), $matches);
            $amount = isset($matches[0]) ? (float) $matches[0] : 1000000;
            $result = $this->rconService->giveMoney($order->gamertag, $amount);
        } else {
            $cmd = "say Pesanan {$order->item_name} untuk {$order->gamertag} telah diproses!";
            $result = $this->rconService->sendCommand($cmd);
        }

        if ($result && $result['success']) {
            $order->update([
                'status' => 'delivered',
                'rcon_command' => $result['command'] ?? 'AUTO_DELIVERY',
                'rcon_response' => $result['response'] ?? ($result['primary_response'] ?? 'Success'),
                'delivered_at' => now(),
            ]);

            return back()->with('success', "Pesanan #{$order->id} untuk {$order->gamertag} berhasil dikirim via RCON!");
        }

        return back()->with('error', "Gagal mengirim pesanan via RCON: " . ($result['error'] ?? 'Unknown error'));
    }
}
