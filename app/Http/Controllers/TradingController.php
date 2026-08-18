<?php

namespace App\Http\Controllers;

use App\Services\MinecraftServerService;
use Illuminate\Http\Request;

class TradingController extends Controller
{
    protected MinecraftServerService $serverService;

    public function __construct(MinecraftServerService $serverService)
    {
        $this->serverService = $serverService;
    }

    /**
     * Display the GenzSMP Financial Web Trading Terminal.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $token = $request->query('token', '');
        $player = $request->query('player', '');

        $serverInfo = config('minecraft.server');
        $tradingConfig = config('minecraft.trading');
        $serverStatus = $this->serverService->getStatus();

        return view('trading', [
            'token' => $token,
            'player' => $player,
            'isValidAccess' => !empty($token) && !empty($player),
            'serverInfo' => $serverInfo,
            'tradingConfig' => $tradingConfig,
            'serverStatus' => $serverStatus,
            'assets' => $tradingConfig['assets'],
        ]);
    }
}
