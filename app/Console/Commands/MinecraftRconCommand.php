<?php

namespace App\Console\Commands;

use App\Services\MinecraftRconService;
use Illuminate\Console\Command;

class MinecraftRconCommand extends Command
{
    protected $signature = 'mc:rcon {cmd : Minecraft server command to execute}';
    protected $description = 'Send an RCON command to Minecraft server via dev-lancer/minecraft-rcon';

    public function handle(MinecraftRconService $rconService)
    {
        $command = $this->argument('cmd');
        $this->info("Executing RCON Command: {$command}");

        $result = $rconService->sendCommand($command);

        if ($result['success']) {
            $this->info("✓ Success!");
            $this->line("Response: " . ($result['response'] ?: '(No response)'));
            return 0;
        } else {
            $this->error("✗ Failed: " . $result['error']);
            return 1;
        }
    }
}
