<?php

namespace App\Http\Controllers;

use App\Services\MinecraftServerService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected MinecraftServerService $serverService;

    public function __construct(MinecraftServerService $serverService)
    {
        $this->serverService = $serverService;
    }

    /**
     * Render the official GenzSMP web portal.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $serverInfo = config('minecraft.server');
        $admins = config('minecraft.admins');
        $ranks = config('minecraft.ranks');
        $moneyPackages = config('minecraft.money_packages');
        $skillPackages = config('minecraft.skill_packages');
        $features = config('minecraft.features');
        $skillsInfo = config('minecraft.skills_info');
        $voteLinks = config('minecraft.vote_links');
        $rules = config('minecraft.rules');
        $bans = config('minecraft.bans');

        $allCheckoutItems = array_merge(
            array_map(fn($r) => ['name' => $r['name'], 'price' => $r['price'], 'type' => 'rank'], $ranks),
            array_map(fn($m) => ['name' => $m['name'], 'price' => $m['price'], 'type' => 'money'], $moneyPackages),
            array_map(fn($s) => ['name' => $s['name'], 'price' => $s['price'], 'type' => 'skill'], $skillPackages)
        );

        $initialStatus = $this->serverService->getStatus();

        return view('home', compact(
            'serverInfo',
            'admins',
            'ranks',
            'moneyPackages',
            'skillPackages',
            'features',
            'skillsInfo',
            'voteLinks',
            'rules',
            'bans',
            'allCheckoutItems',
            'initialStatus'
        ));
    }
}
