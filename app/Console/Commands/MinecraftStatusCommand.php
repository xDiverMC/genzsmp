<?php

namespace App\Console\Commands;

use App\Services\MinecraftServerService;
use Illuminate\Console\Command;

class MinecraftStatusCommand extends Command
{
    protected $signature = 'mc:status {--refresh : Force query without cache}';
    protected $description = 'Query live Minecraft server status and player count via dev-lancer/mc-pack';

    public function handle(MinecraftServerService $serverService)
    {
        $this->info('Querying Minecraft Server Status...');

        $status = $serverService->getStatus($this->option('refresh'));

        $this->table(
            ['Property', 'Value'],
            [
                ['Status', $status['online'] ? '<fg=green>ONLINE</>' : '<fg=red>OFFLINE</>'],
                ['Host', $status['hostname'] . ':' . $status['java_port']],
                ['Online Players', $status['players']['online'] . ' / ' . $status['players']['max']],
                ['Delay / Latency', $status['delay'] . ' ms'],
                ['Protocol', $status['protocol'] ?? 'N/A'],
                ['Version', $status['version']],
                ['MOTD', $status['motd']],
            ]
        );

        if (!empty($status['players']['list'])) {
            $this->info('Player List: ' . implode(', ', $status['players']['list']));
        }

        return 0;
    }
}
