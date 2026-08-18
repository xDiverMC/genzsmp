<?php

namespace App\Console\Commands;

use App\Services\MinecraftRconService;
use Illuminate\Console\Command;

class MinecraftGiveRankCommand extends Command
{
    protected $signature = 'mc:rank {player : Player gamertag} {rank : Rank name}';
    protected $description = 'Assign a rank to a player via LuckPerms RCON and broadcast in-game';

    public function handle(MinecraftRconService $rconService)
    {
        $player = $this->argument('player');
        $rank = $this->argument('rank');

        $this->info("Giving Rank [{$rank}] to {$player}...");

        $result = $rconService->giveRank($player, $rank);

        if ($result['success']) {
            $this->info("✓ Successfully assigned rank {$rank} to {$player}!");
            return 0;
        } else {
            $this->error("✗ Failed to assign rank: " . ($result['error'] ?? 'Unknown error'));
            return 1;
        }
    }
}
