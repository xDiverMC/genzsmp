<?php

namespace App\Console\Commands;

use App\Models\InvestPortfolio;
use App\Models\InvestTrade;
use App\Models\InvestUser;
use App\Services\InvestMarketEngine;
use Illuminate\Console\Command;

class InvestCliCommand extends Command
{
    protected $signature = 'invest:cli {action=all : all, prices, surge, pnl, top, player, trigger, watch} {param? : Optional parameter (e.g. player name)}';
    protected $description = 'Interactive CLI Tool for GenzSMP ArqoInvest Financial Market & Portfolio Management';

    public function handle(): int
    {
        $action = strtolower($this->argument('action') ?? 'all');
        $param = $this->argument('param');

        switch ($action) {
            case 'prices':
            case 'price':
            case 'p':
                $this->showPrices();
                break;
            case 'surge':
            case 's':
            case 'golden':
                $this->showSurge();
                break;
            case 'pnl':
            case 'profit':
            case '+':
            case '-':
                $this->showPnL();
                break;
            case 'top':
            case 'leaderboard':
            case 'rank':
                $this->showTop();
                break;
            case 'player':
            case 'user':
            case 'u':
                if (!$param) {
                    $param = $this->ask('Masukkan nama pemain yang ingin dicek:');
                }
                $this->showPlayer($param);
                break;
            case 'trigger':
            case 'trigger-surge':
                $this->triggerSurge($param);
                break;
            case 'watch':
            case 'live':
                $this->watchDashboard();
                break;
            case 'all':
            default:
                $this->showAll();
                break;
        }

        return 0;
    }

    protected function showHeader(): void
    {
        $this->line("[1;35m╔══════════════════════════════════════════════════════════════════════════════╗[0m");
        $this->line("[1;35m║[0m   [1;37m⚡ GENZSMP ARQOINVEST — REALTIME FINANCIAL CLI TERMINAL[0m                  [1;35m║[0m");
        $this->line("[1;35m╚══════════════════════════════════════════════════════════════════════════════╝[0m");
    }

    protected function showPrices(): void
    {
        $prices = InvestMarketEngine::getCurrentPrices();
        $this->line("
[1;36m▶ 📊 HARGA SPOT PASAR SERVER REALTIME (TAX: 8.0% FLAT)[0m");
        $this->line(str_repeat("─", 78));
        $this->line(sprintf("  %-6s │ %-14s │ %-14s │ %-18s │ %-12s", "ASET", "HARGA SPOT", "HARGA BASE", "SELISIH ($)", "STATUS"));
        $this->line(str_repeat("─", 78));

        foreach ($prices as $sym => $p) {
            $base = InvestMarketEngine::BASE_PRICES[$sym] ?? 100.0;
            $diff = $p - $base;
            $pct = ($diff / $base) * 100;
            $isUp = $diff >= 0;
            $color = $isUp ? "[1;32m" : "[1;31m";
            $status = $isUp ? "▲ BULLISH" : "▼ BEARISH";
            $sign = $isUp ? "+" : "-";

            $this->line(sprintf("  %-6s │ %s$%12s[0m │ $%12s │ %s%s$%9s (%s%.2f%%)[0m │ %s%-10s[0m",
                $sym,
                $color, number_format($p, 2),
                number_format($base, 2),
                $color, $sign, number_format(abs($diff), 2), $sign, abs($pct),
                $color, $status
            ));
        }
        $this->line(str_repeat("─", 78));
    }

    protected function showSurge(): void
    {
        $surge = InvestMarketEngine::getLuckySurgeState();
        $this->line("
[1;33m▶ ⚡ STATUS GOLDEN BULL SURGE ENGINE (1 JAM SEKALI, 30 MENIT)[0m");
        $this->line(str_repeat("─", 78));

        if ($surge && !empty($surge['active'])) {
            $remM = floor(($surge['remaining_seconds'] ?? 0) / 60);
            $remS = ($surge['remaining_seconds'] ?? 0) % 60;
            $nextSec = max(0, ($surge['next_eligible_at'] ?? 0) - time());

            $this->line("  [1;32m● STATUS           :[0m [1;42;30m AKTIF (ON) [0m");
            $this->line("  [1;37m● PEMAIN TERPILIH  :[0m [1;33m" . ($surge['player_name'] ?? 'None') . "[0m");
            $this->line("  [1;37m● LONJAKAN CUAN    :[0m [1;32m+" . ($surge['boost_percent'] ?? 0) . "% (Puncak Hijau Pekat)[0m");
            $this->line("  [1;37m● MULTIPLIER       :[0m [1;36m" . number_format($surge['multiplier'] ?? 1.0, 2) . "x[0m");
            $this->line(sprintf("  [1;37m● SISA WAKTU SURGE :[0m [1;33m%02d Menit %02d Detik[0m [0;90m(Otomatis kembali normal saat habis)[0m", $remM, $remS));
            $this->line(sprintf("  [1;37m● NEXT SURGE DALAM :[0m [0;36m%02d Menit %02d Detik[0m", floor($nextSec / 60), $nextSec % 60));
        } else {
            $nextSec = max(0, ($surge['next_eligible_at'] ?? 0) - time());
            $this->line("  [1;31m● STATUS           :[0m [1;41;37m STANDBY (OFF) [0m");
            $this->line("  [0;90m● Seluruh pemain saat ini sedang berada pada pergerakan pasar normal.[0m");
            $this->line(sprintf("  [1;37m● NEXT SURGE DALAM :[0m [1;36m%02d Menit %02d Detik[0m", floor($nextSec / 60), $nextSec % 60));
        }
        $this->line(str_repeat("─", 78));
    }

    protected function getComputedData(): array
    {
        $prices = InvestMarketEngine::getCurrentPrices();
        $surge = InvestMarketEngine::getLuckySurgeState();
        $users = InvestUser::with('portfolios')->get();
        $allPlayers = [];

        foreach ($users as $u) {
            $totalCost = 0.0;
            $totalValue = 0.0;
            $holdings = [];
            $isDzakiri = strtolower($u->player_name) === 'dzakiri';
            $isSurge = (!empty($surge['active']) && strtolower($u->player_name) === strtolower($surge['player_name'] ?? ''));

            foreach ($u->portfolios as $p) {
                $amt = (float) $p->amount;
                if ($amt <= 0.0001) continue;

                $sym = strtoupper($p->asset);
                $spot = (float) ($prices[$sym] ?? 100.0);
                $avgBuy = (float) $p->avg_buy_price;

                if ($isDzakiri) {
                    $maxAvgBuy = round($spot / 1.75, 2);
                    $avgBuy = min($avgBuy, $maxAvgBuy);
                } elseif ($isSurge) {
                    $maxAvgBuy = round($spot / ($surge['multiplier'] ?? 1.85), 2);
                    $avgBuy = min($avgBuy, $maxAvgBuy);
                }

                $cost = $amt * $avgBuy;
                $val = $amt * $spot;
                $pnl = $val - $cost;
                $pct = $cost > 0 ? ($pnl / $cost) * 100 : 0;

                $totalCost += $cost;
                $totalValue += $val;
                $holdings[$sym] = [
                    'amount' => $amt,
                    'avg_buy' => $avgBuy,
                    'spot' => $spot,
                    'value' => $val,
                    'cost' => $cost,
                    'pnl' => $pnl,
                    'pct' => $pct
                ];
            }

            if ($totalValue > 0) {
                $netPnl = $totalValue - $totalCost;
                $netPct = $totalCost > 0 ? ($netPnl / $totalCost) * 100 : 0;

                $allPlayers[] = [
                    'player' => $u->player_name,
                    'uuid' => $u->uuid,
                    'cash' => (float) $u->cash_balance,
                    'total_value' => $totalValue,
                    'total_cost' => $totalCost,
                    'net_pnl' => $netPnl,
                    'net_pct' => $netPct,
                    'holdings' => $holdings,
                    'is_dzakiri' => $isDzakiri,
                    'is_surge' => $isSurge
                ];
            }
        }

        return [$prices, $surge, $allPlayers];
    }

    protected function showPnL(): void
    {
        [$prices, $surge, $allPlayers] = $this->getComputedData();

        $profitList = array_values(array_filter($allPlayers, fn($p) => $p['net_pnl'] >= 0));
        $lossList = array_values(array_filter($allPlayers, fn($p) => $p['net_pnl'] < 0));

        usort($profitList, fn($a, $b) => $b['net_pnl'] <=> $a['net_pnl']);
        usort($lossList, fn($a, $b) => $a['net_pnl'] <=> $b['net_pnl']);

        $this->line(sprintf("
[1;32m▶ 🟢 DAFTAR INVESTOR PROFIT (+) [%d PEMAIN][0m", count($profitList)));
        $this->line(str_repeat("─", 78));
        $this->line(sprintf("  %-4s │ %-18s │ %-15s │ %-14s │ %-16s", "NO", "PEMAIN", "TOTAL ASET", "MODAL MASUK", "PNL CUAN (%)"));
        $this->line(str_repeat("─", 78));

        foreach ($profitList as $idx => $p) {
            $tag = '';
            if ($p['is_dzakiri']) $tag = ' 👑';
            if ($p['is_surge']) $tag = ' ⚡';

            $this->line(sprintf("  #%-3d │ %-16s%s │ $%13s │ $%12s │ [1;32m+$%10s (+%6.2f%%)[0m",
                $idx + 1,
                $p['player'], $tag,
                number_format($p['total_value'], 2),
                number_format($p['total_cost'], 2),
                number_format($p['net_pnl'], 2),
                $p['net_pct']
            ));
        }
        $this->line(str_repeat("─", 78));

        $this->line(sprintf("
[1;31m▶ 🔻 TOP 15 INVESTOR MINUS (-) [TOTAL: %d PEMAIN][0m", count($lossList)));
        $this->line(str_repeat("─", 78));
        $this->line(sprintf("  %-4s │ %-18s │ %-15s │ %-14s │ %-16s", "NO", "PEMAIN", "TOTAL ASET", "MODAL MASUK", "PNL RUGI (%)"));
        $this->line(str_repeat("─", 78));

        foreach (array_slice($lossList, 0, 15) as $idx => $p) {
            $this->line(sprintf("  #%-3d │ %-18s │ $%13s │ $%12s │ [1;31m-$%10s (%6.2f%%)[0m",
                $idx + 1,
                $p['player'],
                number_format($p['total_value'], 2),
                number_format($p['total_cost'], 2),
                number_format(abs($p['net_pnl']), 2),
                $p['net_pct']
            ));
        }
        $this->line(str_repeat("─", 78));
    }

    protected function showTop(): void
    {
        [$prices, $surge, $allPlayers] = $this->getComputedData();

        usort($allPlayers, fn($a, $b) => $b['total_value'] <=> $a['total_value']);

        $this->line("
[1;33m▶ 🏆 TOP 10 INVESTOR HALL OF FAME (BERDASARKAN TOTAL NILAI ASET)[0m");
        $this->line(str_repeat("─", 78));
        $this->line(sprintf("  %-6s │ %-16s │ %-16s │ %-15s │ %-14s", "RANK", "PEMAIN", "TOTAL ASET", "SALDO KAS", "RETURN (%)"));
        $this->line(str_repeat("─", 78));

        foreach (array_slice($allPlayers, 0, 10) as $idx => $p) {
            $badge = match ($idx) {
                0 => "[1;33m🥇 1ST[0m",
                1 => "[1;37m🥈 2ND[0m",
                2 => "[1;38;5;208m🥉 3RD[0m",
                default => sprintf("#%02d  ", $idx + 1)
            };
            $pnlColor = $p['net_pnl'] >= 0 ? "[1;32m" : "[1;31m";
            $pnlSign = $p['net_pnl'] >= 0 ? "+" : "-";

            $this->line(sprintf("  %s │ %-16s │ $%14s │ $%13s │ %s%s%6.2f%%[0m",
                $badge,
                $p['player'],
                number_format($p['total_value'], 2),
                number_format($p['cash'], 2),
                $pnlColor, $pnlSign, abs($p['net_pct'])
            ));
        }
        $this->line(str_repeat("─", 78));
    }

    protected function showPlayer(string $playerName): void
    {
        $user = InvestUser::whereRaw('LOWER(player_name) = ?', [strtolower($playerName)])->first();
        if (!$user) {
            $this->error("Pemain '$playerName' tidak ditemukan di database investasi.");
            return;
        }

        [$prices, $surge, $allPlayers] = $this->getComputedData();
        $playerData = null;
        foreach ($allPlayers as $p) {
            if (strtolower($p['player']) === strtolower($playerName)) {
                $playerData = $p;
                break;
            }
        }

        $this->line("
[1;36m▶ 👤 RINCIAN PORTOFOLIO INVESTOR: [1;33m{$user->player_name}[0m");
        $this->line(str_repeat("─", 78));
        $this->line("  ● UUID Pemain      : " . ($user->uuid ?? 'N/A'));
        $this->line("  ● Saldo Kas Vault  : [1;32m$" . number_format($user->cash_balance, 2) . "[0m");
        $this->line("  ● Status PIN       : " . ($user->hasPin() ? "[1;32mTERKUNCI (AMAN)[0m" : "[1;31mBELUM DISET[0m"));

        if ($playerData) {
            $pnlColor = $playerData['net_pnl'] >= 0 ? "[1;32m" : "[1;31m";
            $pnlSign = $playerData['net_pnl'] >= 0 ? "+" : "-";
            $this->line("  ● Total Nilai Aset : [1;37m$" . number_format($playerData['total_value'], 2) . "[0m");
            $this->line("  ● Total Modal Beli : [1;37m$" . number_format($playerData['total_cost'], 2) . "[0m");
            $this->line("  ● Net PnL (Return) : {$pnlColor}{$pnlSign}$" . number_format(abs($playerData['net_pnl']), 2) . " ({$pnlSign}" . number_format(abs($playerData['net_pct']), 2) . "%)[0m");

            if ($playerData['is_dzakiri']) {
                $this->line("  ● Status VIP       : [1;33m👑 VIP WHALE ENGINE (PROFIT HIJAU PEKAT TERPROTEKSI)[0m");
            }
            if ($playerData['is_surge']) {
                $this->line("  ● Status Surge     : [1;32m⚡ GOLDEN BULL SURGE ACTIVE (+" . ($surge['boost_percent'] ?? 0) . "%)[0m");
            }

            $this->line("
  [1;37mRINCIAN KEPEMILIKAN ASET:[0m");
            $this->line("  " . str_repeat("─", 74));
            $this->line(sprintf("  %-6s │ %-12s │ %-12s │ %-12s │ %-16s", "ASET", "JUMLAH", "AVG BELI", "NILAI SPOT", "PNL ($ / %)"));
            $this->line("  " . str_repeat("─", 74));

            foreach ($playerData['holdings'] as $sym => $h) {
                $hColor = $h['pnl'] >= 0 ? "[1;32m" : "[1;31m";
                $hSign = $h['pnl'] >= 0 ? "+" : "-";
                $this->line(sprintf("  %-6s │ %10.2f │ $%10.2f │ $%10.2f │ %s%s$%8.2f (%s%.2f%%)[0m",
                    $sym,
                    $h['amount'],
                    $h['avg_buy'],
                    $h['value'],
                    $hColor, $hSign, abs($h['pnl']), $hSign, abs($h['pct'])
                ));
            }
            $this->line("  " . str_repeat("─", 74));
        } else {
            $this->line("  [0;90m● Pemain ini belum memiliki kepemilikan aset aktif (Aset: $0.00).[0m");
        }

        // Recent 5 Trades
        $trades = InvestTrade::where('player_name', $user->player_name)->latest()->limit(5)->get();
        if ($trades->isNotEmpty()) {
            $this->line("
  [1;37mRIWAYAT 5 TRANSAKSI TERAKHIR:[0m");
            $this->line("  " . str_repeat("─", 74));
            foreach ($trades as $t) {
                $tColor = $t->trade_type === 'BUY' ? "[1;32m" : "[1;31m";
                $this->line(sprintf("  %s │ %s%-4s[0m %8.2f %-4s @ $%8.2f │ Total: $%9.2f (Tax: $%6.2f)",
                    $t->created_at->format('Y-m-d H:i:s'),
                    $tColor, $t->trade_type,
                    $t->amount, $t->asset,
                    $t->price,
                    $t->total, $t->tax
                ));
            }
            $this->line("  " . str_repeat("─", 74));
        }
    }

    protected function triggerSurge(?string $playerName): void
    {
        $surge = InvestMarketEngine::triggerRandomLuckySurge($playerName);
        $this->info("✔ Golden Bull Surge berhasil diaktifkan secara manual!");
        $this->line("  ● Pemain  : " . $surge['player_name']);
        $this->line("  ● Boost   : +" . $surge['boost_percent'] . "%");
        $this->line("  ● Durasi  : 30 Menit");
    }

    protected function watchDashboard(): void
    {
        $this->info("Memulai mode LIVE WATCH (Ctrl+C untuk keluar)...");
        while (true) {
            echo "[2J[;H";
            $this->showHeader();
            $this->showPrices();
            $this->showSurge();
            $this->showTop();
            $this->line("
[0;90m[Live Auto-Refresh setiap 2 detik. Tekan Ctrl+C untuk berhenti][0m");
            sleep(2);
        }
    }

    protected function showAll(): void
    {
        $this->showHeader();
        $this->showPrices();
        $this->showSurge();
        $this->showPnL();
        $this->showTop();

        $this->line("
[1;36m💡 PERINTAH CEPAT CLI LAINNYA:[0m");
        $this->line("  • [1;33minvest prices[0m              : Lihat harga spot pasar saja");
        $this->line("  • [1;33minvest surge[0m               : Lihat status event Golden Bull Surge");
        $this->line("  • [1;33minvest pnl[0m                 : Lihat daftar lengkap pemain Profit (+) & Minus (-)");
        $this->line("  • [1;33minvest top[0m                 : Lihat Top 10 Investor Leaderboard");
        $this->line("  • [1;33minvest player <nama>[0m       : Cek portofolio & riwayat pemain tertentu");
        $this->line("  • [1;33minvest trigger <nama>[0m      : Paksa aktifkan Golden Bull Surge ke pemain tertentu");
        $this->line("  • [1;33minvest watch[0m               : Dashboard live auto-refresh realtime");
        $this->line(str_repeat("═", 78) . "
");
    }
}
