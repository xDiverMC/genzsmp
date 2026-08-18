<?php

namespace App\Console\Commands;

use App\Models\InvestPortfolio;
use App\Models\InvestUser;
use Illuminate\Console\Command;

class InvestRegisterUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invest:user 
                            {player_name : Nama Minecraft player (gunakan awalan . untuk Bedrock)}
                            {pin : PIN 6-digit angka untuk trading}
                            {--balance=10000 : Saldo awal Vault Cash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daftarkan player ke database trading dan atur 6-digit PIN serta saldo Vault';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $playerName = trim($this->argument('player_name'));
        $pin = trim($this->argument('pin'));
        $balance = (float) $this->option('balance');

        if (!preg_match('/^[0-9]{6}$/', $pin)) {
            $this->error("❌ Gagal: PIN harus berupa 6 digit angka numerik (contoh: 123456)!");
            return Command::FAILURE;
        }

        $user = InvestUser::findOrCreateByName($playerName);
        $user->setPin($pin);
        $user->cash_balance = $balance;
        $user->save();

        $this->info("✔ Berhasil! Player [{$user->player_name}] terdaftar ke Database Trading.");
        $this->table(
            ['ID', 'Player Name', 'Bedrock?', 'PIN Hash', 'Vault Balance'],
            [[
                $user->id,
                $user->player_name,
                $user->is_bedrock ? 'YES (Bedrock)' : 'NO (Java)',
                '✔ SET (6-Digit)',
                '$' . number_format($user->cash_balance, 2)
            ]]
        );

        return Command::SUCCESS;
    }
}
