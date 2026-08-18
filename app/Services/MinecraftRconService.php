<?php

namespace App\Services;

use DevLancer\MinecraftRcon\Rcon;
use Illuminate\Support\Facades\Log;

class MinecraftRconService
{
    protected string $host;
    protected int $port;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->host = config('minecraft.rcon.host', '172.18.0.2');
        $this->port = (int) config('minecraft.rcon.port', 25575);
        $this->password = config('minecraft.rcon.password', 'secret123');
        $this->timeout = (int) config('minecraft.rcon.timeout', 3);
    }

    /**
     * Send a raw RCON command to Minecraft server.
     *
     * @param string $command
     * @return array
     */
    public function sendCommand(string $command): array
    {
        $command = trim($command);
        if (empty($command)) {
            return [
                'success' => false,
                'command' => $command,
                'response' => '',
                'error' => 'Command cannot be empty'
            ];
        }

        try {
            $rcon = new Rcon($this->host, $this->port, $this->password, $this->timeout);
            
            if (!$rcon->connect()) {
                return [
                    'success' => false,
                    'command' => $command,
                    'response' => '',
                    'error' => 'Unable to connect to Minecraft RCON server on ' . $this->host . ':' . $this->port
                ];
            }

            $response = $rcon->sendCommand($command);
            $rcon->disconnect();

            return [
                'success' => true,
                'command' => $command,
                'response' => $this->cleanResponse($response),
                'error' => null
            ];
        } catch (\Throwable $e) {
            Log::error("Minecraft RCON Exception [Cmd: {$command}]: " . $e->getMessage());

            return [
                'success' => false,
                'command' => $command,
                'response' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Give Rank/Permission group to a player.
     *
     * @param string $player
     * @param string $rankGroup
     * @return array
     */
    public function giveRank(string $player, string $rankGroup): array
    {
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_]/', '', $player);
        $sanitizedRank = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $rankGroup));

        if (empty($sanitizedPlayer) || empty($sanitizedRank)) {
            return ['success' => false, 'error' => 'Invalid player or rank identifier.'];
        }

        // 1. LuckPerms set group
        $cmd1 = "lp user {$sanitizedPlayer} parent set {$sanitizedRank}";
        $res1 = $this->sendCommand($cmd1);

        // 2. Broadcast announcement in-game
        $displayRank = strtoupper($sanitizedRank);
        $cmd2 = "tellraw @a [{\"text\":\"[GENZSMP] \",\"color\":\"light_purple\",\"bold\":true},{\"text\":\"{$sanitizedPlayer} \",\"color\":\"yellow\",\"bold\":true},{\"text\":\"telah mengaktifkan Rank \",\"color\":\"gray\"},{\"text\":\"[{$displayRank}]\",\"color\":\"gold\",\"bold\":true},{\"text\":\"!\",\"color\":\"gray\"}]";
        $this->sendCommand($cmd2);

        return [
            'success' => $res1['success'],
            'player' => $sanitizedPlayer,
            'rank' => $sanitizedRank,
            'primary_response' => $res1['response'],
            'error' => $res1['error']
        ];
    }

    /**
     * Give In-Game Money to a player via Vault / Essentials.
     *
     * @param string $player
     * @param float|int $amount
     * @return array
     */
    public function giveMoney(string $player, float|int $amount): array
    {
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_]/', '', $player);
        $amount = (float) $amount;

        if (empty($sanitizedPlayer) || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid player name or money amount.'];
        }

        $cmd = "eco give {$sanitizedPlayer} {$amount}";
        $res = $this->sendCommand($cmd);

        $formatted = number_format($amount, 0, ',', '.');
        $cmdMsg = "tellraw {$sanitizedPlayer} [{\"text\":\"[GENZSMP] \",\"color\":\"light_purple\",\"bold\":true},{\"text\":\"Saldo \",\"color\":\"gray\"},{\"text\":\"{$formatted} Money\",\"color\":\"green\",\"bold\":true},{\"text\":\" telah ditambahkan ke akunmu!\",\"color\":\"gray\"}]";
        $this->sendCommand($cmdMsg);

        return [
            'success' => $res['success'],
            'player' => $sanitizedPlayer,
            'amount' => $amount,
            'response' => $res['response'],
            'error' => $res['error']
        ];
    }

    /**
     * Give Item directly to player inventory.
     *
     * @param string $player
     * @param string $item
     * @param int $amount
     * @return array
     */
    public function giveItem(string $player, string $item, int $amount = 1): array
    {
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_]/', '', $player);
        $sanitizedItem = preg_replace('/[^a-zA-Z0-9_:]/', '', $item);
        $amount = max(1, (int) $amount);

        if (empty($sanitizedPlayer) || empty($sanitizedItem)) {
            return ['success' => false, 'error' => 'Invalid player or item parameter.'];
        }

        $cmd = "give {$sanitizedPlayer} {$sanitizedItem} {$amount}";
        return $this->sendCommand($cmd);
    }

    /**
     * Clean Minecraft color code characters (§) from RCON response.
     *
     * @param string $text
     * @return string
     */
    protected function cleanResponse(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        return trim(preg_replace('/§[0-9a-fk-or]/i', '', $text));
    }
}
