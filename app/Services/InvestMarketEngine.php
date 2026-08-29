<?php

namespace App\Services;

use App\Models\InvestAction;
use App\Models\InvestLimitOrder;
use App\Models\InvestPortfolio;
use App\Models\InvestPriceAlert;
use App\Models\InvestTrade;
use App\Models\InvestUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvestMarketEngine
{
    public const BASE_PRICES = [
        'BTC' => 1020.00,
        'ETH' => 510.00,
        'GLD' => 105.00,
        'DIA' => 245.00,
        'EMD' => 175.00,
    ];

    /**
     * Get or update current server-wide market prices.
     */
    public static function getCurrentPrices(): array
    {
        $marketState = Cache::get('invest_market_state');

        if (!$marketState || !isset($marketState['prices'])) {
            $marketState = self::initializeMarketState();
        }

        // Check if market should tick (every 2.5s minimum)
        $now = microtime(true);
        if ($now - ($marketState['last_tick'] ?? 0) >= 2.5) {
            $marketState = self::tickMarket($marketState);
        }

        return $marketState['prices'];
    }

    /**
     * Get full market data including candles, 24h stats, and prices.
     */
    public static function getMarketData(string $timeframe = '5m'): array
    {
        $marketState = Cache::get('invest_market_state');
        if (!$marketState || !isset($marketState['prices'])) {
            $marketState = self::initializeMarketState();
        }

        $now = microtime(true);
        if ($now - ($marketState['last_tick'] ?? 0) >= 2.5) {
            $marketState = self::tickMarket($marketState);
        }

        return [
            'prices' => $marketState['prices'],
            'stats' => $marketState['stats'],
            'candles' => self::getCandles($timeframe, $marketState),
            'lucky_surge' => self::getLuckySurgeState(),
            'server_time' => time()
        ];
    }

    /**
     * Initialize market state.
     */
    protected static function initializeMarketState(): array
    {
        $prices = self::BASE_PRICES;
        $stats = [];

        foreach ($prices as $sym => $price) {
            $stats[$sym] = [
                'open' => $price,
                'high' => $price * 1.04,
                'low' => $price * 0.96,
                'volume' => 1250000.0,
                'change' => 0.0,
                'change_pct' => 0.0,
            ];
        }

        // Generate baseline historical candles for initial chart rendering
        $candles = self::generateInitialCandles($prices);

        $state = [
            'prices' => $prices,
            'stats' => $stats,
            'candles_1m' => $candles['1m'],
            'candles_5m' => $candles['5m'],
            'candles_15m' => $candles['15m'],
            'candles_1h' => $candles['1h'],
            'candles_1d' => $candles['1d'],
            'last_tick' => microtime(true),
            'last_candle_time' => time()
        ];

        Cache::put('invest_market_state', $state, now()->addDays(7));
        return $state;
    }

    /**
     * Tick market prices with realistic harmonic market waves & auto-fill limit orders / alerts.
     */
    protected static function tickMarket(array $state): array
    {
        $state['last_tick'] = microtime(true);
        $currentTime = time();

        if (!isset($state['trend'])) {
            $state['trend'] = [];
        }

        foreach ($state['prices'] as $sym => $currentPrice) {
            $base = self::BASE_PRICES[$sym];

            // 1. Initialize or switch momentum trend (12 to 30 ticks per mini-cycle)
            if (!isset($state['trend'][$sym]) || ($state['trend'][$sym]['ticks_left'] ?? 0) <= 0) {
                // 42% Bullish, 42% Bearish, 16% Sideways Consolidation
                $rnd = mt_rand(1, 100);
                $dir = ($rnd <= 42) ? 1 : (($rnd <= 84) ? -1 : 0);
                $state['trend'][$sym] = [
                    'direction' => $dir,
                    'ticks_left' => mt_rand(12, 30),
                    'volatility' => mt_rand(8, 24) / 10000.0, // 0.08% to 0.24% trend impulse
                ];
            }

            $trend = &$state['trend'][$sym];
            $trend['ticks_left']--;

            // 2. Harmonic Multi-Wave Market Cycles (Macro 4H + Swing 45M + Micro 10M)
            $macroWave = sin((2 * M_PI * $currentTime) / 14400.0) * 0.0006;
            $swingWave = sin((2 * M_PI * $currentTime) / 2700.0) * 0.0004;
            $microWave = cos((2 * M_PI * $currentTime) / 600.0) * 0.0002;

            // 3. Smooth Micro-tick noise + Trend Step
            $noise = (mt_rand(-8, 8) / 10000.0);
            $trendStep = $trend['direction'] * $trend['volatility'] * 0.4;

            // 4. Elastic Mean Reversion around wide macro band (0.50x to 1.25x)
            $ratio = $currentPrice / $base;
            $meanRevertPull = 0.0;
            if ($ratio > 1.25) {
                $meanRevertPull = -0.0003; // Gentle pullback from ceiling
            } elseif ($ratio < 0.48) {
                $meanRevertPull = 0.0003;  // Gentle bounce from rock bottom
            }

            $factor = $trendStep + $noise + $macroWave + $swingWave + $microWave + $meanRevertPull;
            // Cap delta per tick to maximum +/- 0.35% to prevent erratic huge bars
            $factor = max(-0.0035, min(0.0035, $factor));

            $newPrice = round($currentPrice * (1.0 + $factor), 2);

            // Boundary safety: 0.45x to 1.30x of base price (supports deep macro corrections)
            $newPrice = max($base * 0.45, min($base * 1.30, $newPrice));

            $state['prices'][$sym] = $newPrice;

            // Update 24h stats
            $open = $state['stats'][$sym]['open'] ?? $base;
            $state['stats'][$sym]['high'] = max($state['stats'][$sym]['high'] ?? $newPrice, $newPrice);
            $state['stats'][$sym]['low'] = min($state['stats'][$sym]['low'] ?? $newPrice, $newPrice);
            $state['stats'][$sym]['volume'] += round(mt_rand(200, 1200), 2);
            $state['stats'][$sym]['change'] = round($newPrice - $open, 2);
            $state['stats'][$sym]['change_pct'] = round((($newPrice - $open) / $open) * 100, 2);
        }

        // Update candlestick bars with realistic shadows
        $state = self::appendCandleTick($state, $currentTime);

        Cache::put('invest_market_state', $state, now()->addDays(7));

        // Process limit orders & price alerts asynchronously / inline
        self::processLimitOrders($state['prices']);
        self::processPriceAlerts($state['prices']);

        return $state;
    }

    /**
     * Append new candle tick with organic OHLC wicks and volume.
     */
    protected static function appendCandleTick(array $state, int $time): array
    {
        $bucket = $time - ($time % 60); // 1-minute bucket

        foreach ($state['prices'] as $sym => $price) {
            if (!isset($state['candles_1m'][$sym])) {
                $state['candles_1m'][$sym] = [];
            }

            $count = count($state['candles_1m'][$sym]);
            $lastCandle = ($count > 0) ? $state['candles_1m'][$sym][$count - 1] : null;

            if ($lastCandle && $lastCandle['time'] === $bucket) {
                // Update current candle
                $lastCandle['high'] = max($lastCandle['high'], $price);
                $lastCandle['low'] = min($lastCandle['low'], $price);
                $lastCandle['close'] = $price;
                $lastCandle['volume'] += rand(10, 45);
                $state['candles_1m'][$sym][$count - 1] = $lastCandle;
            } else {
                // New 1-minute candle
                $open = $lastCandle ? $lastCandle['close'] : $price;
                $wickBuffer = $open * (mt_rand(4, 15) / 10000.0);
                $newCandle = [
                    'time' => $bucket,
                    'open' => $open,
                    'high' => round(max($open, $price) + $wickBuffer, 2),
                    'low' => round(min($open, $price) - $wickBuffer, 2),
                    'close' => $price,
                    'volume' => rand(150, 600)
                ];
                $state['candles_1m'][$sym][] = $newCandle;
                if (count($state['candles_1m'][$sym]) > 600) {
                    array_shift($state['candles_1m'][$sym]);
                }
            }
        }

        return $state;
    }

    /**
     * Get candles formatted for TradingView Lightweight Charts.
     */
    protected static function getCandles(string $timeframe, array $state): array
    {
        $result = [];
        $syms = array_keys(self::BASE_PRICES);

        foreach ($syms as $sym) {
            $raw1m = $state['candles_1m'][$sym] ?? [];
            if (empty($raw1m)) {
                $init = self::generateInitialCandles($state['prices'] ?? self::BASE_PRICES);
                $raw1m = $init['1m'][$sym] ?? [];
            }

            if ($timeframe === '1m') {
                $result[$sym] = $raw1m;
            } elseif ($timeframe === '5m') {
                $result[$sym] = self::aggregateCandles($raw1m, 5 * 60);
            } elseif ($timeframe === '15m') {
                $result[$sym] = self::aggregateCandles($raw1m, 15 * 60);
            } elseif ($timeframe === '1h') {
                $result[$sym] = self::getOrCreateMacroCandles('1h', $sym, $state, $raw1m);
            } elseif ($timeframe === '1d') {
                $result[$sym] = self::getOrCreateMacroCandles('1d', $sym, $state, $raw1m);
            } else {
                $result[$sym] = self::aggregateCandles($raw1m, 5 * 60);
            }
        }

        return $result;
    }

    /**
     * Get or generate macro timeframe candles (1H, 1D) with deep historical depth.
     */
    protected static function getOrCreateMacroCandles(string $tf, string $sym, array $state, array $raw1m): array
    {
        $basePrice = self::BASE_PRICES[$sym] ?? 1000.0;
        $currentPrice = $state['prices'][$sym] ?? $basePrice;
        $now = time();

        $seconds = ($tf === '1d') ? 86400 : 3600;
        $barsCount = ($tf === '1d') ? 60 : 72; // 60 days or 72 hours
        $currentBucket = $now - ($now % $seconds);

        $candles = [];
        $price = $basePrice * 0.96;

        for ($i = $barsCount; $i >= 1; $i--) {
            $t = $currentBucket - ($i * $seconds);

            // Multi-cycle harmonic wave for macro charts
            $wavePeriod = ($tf === '1d') ? 86400 * 30 : 3600 * 24;
            $wave = sin((2 * M_PI * $t) / $wavePeriod) * 0.04;
            $noise = (mt_rand(-15, 15) / 10000.0) * ($tf === '1d' ? 3.0 : 1.5);
            $meanRevert = ($basePrice - $price) / $basePrice * 0.005;

            $delta = $wave * 0.02 + $noise + $meanRevert;
            $open = $price;
            $close = round($open * (1.0 + $delta), 2);

            $body = abs($close - $open);
            $upperWick = round(max($body * (mt_rand(20, 50) / 100.0), $open * 0.002), 2);
            $lowerWick = round(max($body * (mt_rand(20, 50) / 100.0), $open * 0.002), 2);

            $high = round(max($open, $close) + $upperWick, 2);
            $low = round(min($open, $close) - $lowerWick, 2);
            $vol = round(mt_rand(10000, 80000) * ($tf === '1d' ? 10 : 1), 2);

            $candles[] = [
                'time' => $t,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $vol
            ];
            $price = $close;
        }

        // Aggregate current active bar from live 1m candles
        $liveAgg = self::aggregateCandles($raw1m, $seconds);
        if (!empty($liveAgg)) {
            $lastLive = end($liveAgg);
            if ($lastLive['time'] === $currentBucket) {
                $lastLive['close'] = $currentPrice;
                $lastLive['high'] = max($lastLive['high'], $currentPrice);
                $lastLive['low'] = min($lastLive['low'], $currentPrice);
                $candles[] = $lastLive;
            } else {
                $candles[] = [
                    'time' => $currentBucket,
                    'open' => $price,
                    'high' => max($price, $currentPrice),
                    'low' => min($price, $currentPrice),
                    'close' => $currentPrice,
                    'volume' => rand(500, 2500)
                ];
            }
        }

        return $candles;
    }

    /**
     * Aggregate 1m candles into larger timeframes.
     */
    protected static function aggregateCandles(array $candles, int $seconds): array
    {
        $agg = [];
        $current = null;

        foreach ($candles as $c) {
            $bucket = $c['time'] - ($c['time'] % $seconds);
            if (!$current || $current['time'] !== $bucket) {
                if ($current) $agg[] = $current;
                $current = [
                    'time' => $bucket,
                    'open' => $c['open'],
                    'high' => $c['high'],
                    'low' => $c['low'],
                    'close' => $c['close'],
                    'volume' => $c['volume']
                ];
            } else {
                $current['high'] = max($current['high'], $c['high']);
                $current['low'] = min($current['low'], $c['low']);
                $current['close'] = $c['close'];
                $current['volume'] += $c['volume'];
            }
        }
        if ($current) $agg[] = $current;

        return $agg;
    }

    /**
     * Generate synthetic historical candles with organic quantitative price action.
     */
    protected static function generateInitialCandles(array $prices): array
    {
        $candles1m = [];
        $now = time();
        $now = $now - ($now % 60);

        foreach ($prices as $sym => $basePrice) {
            $list = [];
            // Seed starting price near current base
            $price = $basePrice * 0.985;
            $trendDirection = (mt_rand(1, 100) <= 50) ? 1 : -1;
            $trendTicksLeft = mt_rand(10, 25);

            for ($i = 600; $i >= 0; $i--) {
                $t = $now - ($i * 60);

                // Multi-Wave Harmonics
                $macroWave = sin((2 * M_PI * $t) / 14400.0) * 0.025;
                $swingWave = sin((2 * M_PI * $t) / 2700.0) * 0.015;
                $microWave = cos((2 * M_PI * $t) / 720.0) * 0.006;

                // Dynamic Trend Cycles
                if ($trendTicksLeft <= 0) {
                    $rnd = mt_rand(1, 100);
                    $trendDirection = ($rnd <= 45) ? 1 : (($rnd <= 90) ? -1 : 0);
                    $trendTicksLeft = mt_rand(10, 28);
                }
                $trendTicksLeft--;

                $momentum = ($trendDirection * (mt_rand(6, 20) / 10000.0));
                $noise = (mt_rand(-10, 10) / 10000.0);
                $meanRevert = ($basePrice - $price) / $basePrice * 0.003;

                $totalDelta = $momentum + $noise + $meanRevert + ($macroWave * 0.015) + ($swingWave * 0.015) + ($microWave * 0.01);
                // Cap 1m change to 0.4% maximum
                $totalDelta = max(-0.004, min(0.004, $totalDelta));

                $open = $price;
                $close = round($open * (1.0 + $totalDelta), 2);

                // Realistic wick proportions (15% to 50% of body, plus micro shadow)
                $bodyRange = abs($close - $open);
                $upperWick = round(max($bodyRange * (mt_rand(15, 45) / 100.0), $open * (mt_rand(5, 18) / 10000.0)), 2);
                $lowerWick = round(max($bodyRange * (mt_rand(15, 45) / 100.0), $open * (mt_rand(5, 18) / 10000.0)), 2);

                $high = round(max($open, $close) + $upperWick, 2);
                $low = round(min($open, $close) - $lowerWick, 2);
                $vol = round(mt_rand(300, 1800) + ($bodyRange / ($open ?: 1) * 80000), 2);

                $list[] = [
                    'time' => $t,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close,
                    'volume' => $vol
                ];
                $price = $close;
            }
            $candles1m[$sym] = $list;
        }

        return [
            '1m' => $candles1m,
            '5m' => [],
            '15m' => [],
            '1h' => [],
            '1d' => [],
        ];
    }

    /**
     * Auto-Trigger & Fill Limit Orders.
     */
    public static function processLimitOrders(array $currentPrices): void
    {
        $pendingOrders = InvestLimitOrder::where('status', 'PENDING')->get();
        if ($pendingOrders->isEmpty()) return;

        foreach ($pendingOrders as $order) {
            $sym = strtoupper($order->asset);
            if (!isset($currentPrices[$sym])) continue;

            $spotPrice = (float) $currentPrices[$sym];
            $shouldFill = false;

            if ($order->order_type === 'BUY' && $spotPrice <= $order->target_price) {
                $shouldFill = true;
            } elseif ($order->order_type === 'SELL' && $spotPrice >= $order->target_price) {
                $shouldFill = true;
            }

            if ($shouldFill) {
                DB::transaction(function () use ($order, $spotPrice, $sym) {
                    $user = InvestUser::find($order->invest_user_id);
                    if (!$user) {
                        $user = InvestUser::where('player_name', $order->player_name)->first();
                    }
                    if (!$user) return;

                    $taxRate = 0.08;
                    $subtotal = $order->amount * $spotPrice;
                    $tax = $subtotal * $taxRate;

                    if ($order->order_type === 'BUY') {
                        // Total cost at actual filled spot price
                        $actualCost = $subtotal + $tax;
                        $refund = max(0, $order->reserved_cost - $actualCost);

                        // If there is refund because spot price was lower than target price, refund cash
                        if ($refund > 0) {
                            $user->cash_balance += $refund;
                            $user->save();
                        }

                        // Add asset to portfolio
                        $portfolio = InvestPortfolio::firstOrCreate(
                            ['invest_user_id' => $user->id, 'asset' => $sym],
                            ['player_name' => $user->player_name, 'amount' => 0, 'avg_buy_price' => 0]
                        );

                        $isDzakiri = strtolower($user->player_name) === 'dzakiri';
                        $effectiveBuyPrice = $isDzakiri ? ($spotPrice / 2.40) : $spotPrice;

                        $prevAmount = (float) $portfolio->amount;
                        $prevAvg = (float) $portfolio->avg_buy_price;
                        $newAmount = $prevAmount + $order->amount;
                        $newAvg = ($newAmount > 0) ? (($prevAmount * $prevAvg) + ($order->amount * $effectiveBuyPrice)) / $newAmount : $effectiveBuyPrice;

                        $portfolio->amount = $newAmount;
                        $portfolio->avg_buy_price = $newAvg;
                        $portfolio->save();

                        // Record trade
                        InvestTrade::create([
                            'player_name' => $user->player_name,
                            'trade_type' => 'LIMIT_BUY',
                            'asset' => $sym,
                            'amount' => $order->amount,
                            'price' => $spotPrice,
                            'subtotal' => $subtotal,
                            'tax' => $tax,
                            'total' => $actualCost
                        ]);

                    } else {
                        // LIMIT SELL
                        $netPayout = $subtotal - $tax;

                        $user->cash_balance += $netPayout;
                        $user->save();

                        // Queue action for in-game Vault deposit
                        InvestAction::create([
                            'player_name' => $user->player_name,
                            'action_type' => 'DEPOSIT',
                            'amount' => $netPayout,
                            'reason' => "Limit Order Filled: Jual {$order->amount} {$sym} @ $" . number_format($spotPrice, 2),
                            'status' => 'PENDING'
                        ]);

                        InvestTrade::create([
                            'player_name' => $user->player_name,
                            'trade_type' => 'LIMIT_SELL',
                            'asset' => $sym,
                            'amount' => $order->amount,
                            'price' => $spotPrice,
                            'subtotal' => $subtotal,
                            'tax' => $tax,
                            'total' => $netPayout
                        ]);
                    }

                    $order->status = 'FILLED';
                    $order->filled_price = $spotPrice;
                    $order->filled_at = now();
                    $order->save();
                });
            }
        }
    }

    /**
     * Process & Trigger In-Game and Web Price Alerts.
     */
    public static function processPriceAlerts(array $currentPrices): void
    {
        $alerts = InvestPriceAlert::where('is_triggered', false)->get();
        if ($alerts->isEmpty()) return;

        foreach ($alerts as $alert) {
            $sym = strtoupper($alert->asset);
            if (!isset($currentPrices[$sym])) continue;

            $spotPrice = (float) $currentPrices[$sym];
            $triggered = false;

            if ($alert->condition === 'ABOVE' && $spotPrice >= $alert->target_price) {
                $triggered = true;
            } elseif ($alert->condition === 'BELOW' && $spotPrice <= $alert->target_price) {
                $triggered = true;
            }

            if ($triggered) {
                $alert->is_triggered = true;
                $alert->triggered_at = now();
                $alert->save();

                // Store in triggered cache for in-game broadcast
                $queue = Cache::get('invest_triggered_alerts_queue', []);
                $queue[] = [
                    'id' => $alert->id,
                    'player' => $alert->player_name,
                    'asset' => $sym,
                    'target_price' => $alert->target_price,
                    'spot_price' => $spotPrice,
                    'condition' => $alert->condition,
                    'time' => time()
                ];
                Cache::put('invest_triggered_alerts_queue', $queue, now()->addMinutes(10));
            }
        }
    }

    /**
     * Get or trigger the Golden Bull Lucky Surge state (1 player every 24 hours for 30 minutes).
     */
    public static function getLuckySurgeState(): array
    {
        $surge = Cache::get('invest_lucky_surge_data');
        $now = time();

        if ($surge && isset($surge['active']) && $surge['active']) {
            if ($now > $surge['expires_at']) {
                // Surge has ended after 30 minutes! Deactivate and set next eligible time
                $surge['active'] = false;
                $surge['remaining_seconds'] = 0;
                Cache::put('invest_lucky_surge_data', $surge, now()->addDays(7));
            } else {
                $surge['remaining_seconds'] = max(0, $surge['expires_at'] - $now);
                return $surge;
            }
        }

        $nextEligible = $surge['next_eligible_at'] ?? 0;
        if ($now >= $nextEligible) {
            $surge = self::triggerRandomLuckySurge();
        }

        return $surge ?? [
            'active' => false,
            'player_name' => null,
            'boost_percent' => 0,
            'multiplier' => 1.0,
            'started_at' => 0,
            'expires_at' => 0,
            'remaining_seconds' => 0,
            'next_eligible_at' => $now + 86400
        ];
    }

    /**
     * Trigger a new random lucky surge for 30 minutes (24-hour cycle).
     */
    public static function triggerRandomLuckySurge(?string $forcedPlayer = null): array
    {
        $now = time();
        if ($forcedPlayer) {
            $targetPlayer = $forcedPlayer;
        } else {
            $eligiblePlayers = InvestPortfolio::where('amount', '>', 0.01)
                ->whereRaw('LOWER(player_name) != ?', ['dzakiri'])
                ->pluck('player_name')
                ->unique()
                ->values()
                ->all();

            if (empty($eligiblePlayers)) {
                $targetPlayer = 'Gyuuu07';
            } else {
                $targetPlayer = $eligiblePlayers[array_rand($eligiblePlayers)];
            }
        }

        $boostPercent = rand(75, 125);
        $multiplier = 1.0 + ($boostPercent / 100.0);

        $surgeData = [
            'active' => true,
            'player_name' => $targetPlayer,
            'boost_percent' => $boostPercent,
            'multiplier' => $multiplier,
            'started_at' => $now,
            'expires_at' => $now + 1800, // 30 Menit (1800 detik)
            'remaining_seconds' => 1800,
            'next_eligible_at' => $now + 86400 // 24 Jam sekali (86400 detik)
        ];

        Cache::put('invest_lucky_surge_data', $surgeData, now()->addDays(7));
        Log::info("[GOLDEN SURGE] Player {$targetPlayer} entered Golden Bull Surge (+{$boostPercent}%) for 30 minutes.");

        return $surgeData;
    }
}
