<?php

namespace App\Console\Commands;

use App\Services\MinecraftRconService;
use Illuminate\Console\Command;

class MinecraftGiveMoneyCommand extends Command
{
    protected $signature = 'mc:money {player : Player gamertag} {amount : Amount of money}';
    protected $description = 'Give in-game money to a player via Vault RCON';

    public function handle(MinecraftRconService $rconService)
    {
        $player = $this->argument('player');
        $amount = (float) $this->argument('amount');

        $this->info("Giving {$amount} Money to {$player}...");

        $result = $rconService->giveMoney($player, $amount);

        if ($result['success']) {
            $this->info("✓ Successfully given {$amount} Money to {$player}!");
            return 0;
        } else {
            $this->error("✗ Failed: " . ($result['error'] ?? 'Unknown error'));
            return 1;
        }
    }
}
