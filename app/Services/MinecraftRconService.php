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

            $sent = $rcon->sendCommand($command);
            $response = $rcon->getResponse() ?? '';
            $rcon->disconnect();

            return [
                'success' => $sent,
                'command' => $command,
                'response' => $this->cleanResponse($response),
                'error' => $sent ? null : 'Failed to send command to server'
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
     * Deduct / Take In-Game Money from a player via Vault / Essentials.
     *
     * @param string $player
     * @param float|int $amount
     * @return array
     */
    public function takeMoney(string $player, float|int $amount): array
    {
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_.]/', '', $player);
        $amount = (float) $amount;

        if (empty($sanitizedPlayer) || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid player name or money amount.'];
        }

        $cmd = "eco take {$sanitizedPlayer} {$amount}";
        $res = $this->sendCommand($cmd);

        return [
            'success' => $res['success'],
            'player' => $sanitizedPlayer,
            'amount' => $amount,
            'response' => $res['response'],
            'error' => $res['error']
        ];
    }

    /**
     * Query real in-game Vault balance of a player.
     *
     * @param string $player
     * @return float|null Returns balance as float, or null if unreachable
     */
    public function getPlayerBalance(string $player): ?float
    {
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_.]/', '', $player);
        if (empty($sanitizedPlayer)) {
            return null;
        }

        $cmd = "eco balance {$sanitizedPlayer}";
        $res = $this->sendCommand($cmd);

        if (!$res['success'] || empty($res['response'])) {
            // Fallback command: balance <player>
            $res = $this->sendCommand("balance {$sanitizedPlayer}");
        }

        if ($res['success'] && !empty($res['response'])) {
            // Regex match balance digits like "$500,000.00" or "500000" or "Balance: 500000"
            if (preg_match('/(?:Balance:|\$|saldo:?)\s*([0-9,.]+)/i', $res['response'], $matches)) {
                $cleaned = str_replace(',', '', $matches[1]);
                return (float) $cleaned;
            }
        }

        return null;
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
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_.]/', '', $player);
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
     * Notify an online player in Minecraft server about a Web Trading transaction.
     *
     * @param string $player
     * @param string $tradeType BUY or SELL
     * @param string $asset
     * @param float $amount
     * @param float $price
     * @param float $tax
     * @param float $total
     * @param float $newBalance
     * @return array
     */
    public function notifyTradeInGame(
        string $player,
        string $tradeType,
        string $asset,
        float $amount,
        float $price,
        float $tax,
        float $total,
        float $newBalance
    ): array {
        $sanitizedPlayer = preg_replace('/[^a-zA-Z0-9_.]/', '', $player);
        if (empty($sanitizedPlayer)) {
            return ['success' => false, 'error' => 'Invalid player name'];
        }

        $formattedAmount = number_format($amount, 2);
        $formattedPrice = number_format($price, 2);
        $formattedTax = number_format($tax, 2);
        $formattedTotal = number_format($total, 2);
        $formattedBal = number_format($newBalance, 2);

        $actionText = ($tradeType === 'BUY') ? 'Membeli' : 'Menjual';
        $color = ($tradeType === 'BUY') ? 'green' : 'gold';

        // 1. In-game Chat Tellraw Message
        $msgJson = json_encode([
            ["text" => "\n[GenzSMP Web Trade] ", "color" => "light_purple", "bold" => true],
            ["text" => "Transaksi Web Berhasil!\n", "color" => "green", "bold" => true],
            ["text" => "│ ", "color" => "dark_gray"],
            ["text" => "Tipe: ", "color" => "gray"],
            ["text" => "{$tradeType} ({$actionText})\n", "color" => $color, "bold" => true],
            ["text" => "│ ", "color" => "dark_gray"],
            ["text" => "Aset: ", "color" => "gray"],
            ["text" => "{$formattedAmount} {$asset} ", "color" => "yellow", "bold" => true],
            ["text" => "(@ \${$formattedPrice})\n", "color" => "gray"],
            ["text" => "│ ", "color" => "dark_gray"],
            ["text" => "Tax (2%): ", "color" => "gray"],
            ["text" => "\${$formattedTax}\n", "color" => "gold"],
            ["text" => "│ ", "color" => "dark_gray"],
            ["text" => ($tradeType === 'BUY' ? "Total Biaya: " : "Payout Bersih: "), "color" => "gray"],
            ["text" => "\${$formattedTotal}\n", "color" => "white", "bold" => true],
            ["text" => "│ ", "color" => "dark_gray"],
            ["text" => "Sisa Saldo Vault: ", "color" => "gray"],
            ["text" => "\${$formattedBal}\n", "color" => "green", "bold" => true],
            ["text" => "[GenzSMP Web Trade] ", "color" => "light_purple", "bold" => true],
            ["text" => "Portofolio web telah diperbarui secara real-time.\n", "color" => "gray"]
        ]);

        $cmd1 = "tellraw @a[name=\"{$sanitizedPlayer}\"] {$msgJson}";
        $res1 = $this->sendCommand($cmd1);

        // 2. Actionbar notification
        $actionJson = json_encode([
            "text" => "[Web Trade] {$tradeType} {$formattedAmount} {$asset} Sukses | Saldo: \${$formattedBal}",
            "color" => "green",
            "bold" => true
        ]);
        $cmd2 = "title @a[name=\"{$sanitizedPlayer}\"] actionbar {$actionJson}";
        $this->sendCommand($cmd2);

        // 3. Play level-up chime sound
        $cmd3 = "playsound minecraft:entity.player.levelup player @a[name=\"{$sanitizedPlayer}\"] ~ ~ ~ 1 1.2";
        $this->sendCommand($cmd3);

        return [
            'success' => $res1['success'],
            'player' => $sanitizedPlayer,
            'response' => $res1['response']
        ];
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
