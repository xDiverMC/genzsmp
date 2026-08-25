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
     * Tick market prices with realistic dynamic market waves & auto-fill limit orders / alerts.
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

            // Initialize or switch momentum trend for this asset (15-35 ticks per mini-cycle)
            if (!isset($state['trend'][$sym]) || ($state['trend'][$sym]['ticks_left'] ?? 0) <= 0) {
                // Determine new trend: 45% Bull, 45% Bear, 10% Sideways
                $rnd = mt_rand(1, 100);
                $dir = ($rnd <= 45) ? 1 : (($rnd <= 90) ? -1 : 0);
                $state['trend'][$sym] = [
                    'direction' => $dir,
                    'ticks_left' => mt_rand(15, 35),
                    'volatility' => mt_rand(15, 45) / 10000.0, // 0.15% to 0.45% per tick
                ];
            }

            $trend = &$state['trend'][$sym];
            $trend['ticks_left']--;

            // Micro-tick noise + Trend impulse + Mean Reversion pull around baseline
            $noise = (mt_rand(-20, 20) / 10000.0);
            $trendStep = $trend['direction'] * $trend['volatility'];

            // Mean reversion: if price gets too high above baseline, pull down; if too low, pull up
            $ratio = $currentPrice / $base;
            $meanRevertPull = 0.0;
            if ($ratio > 1.15) {
                $meanRevertPull = -0.0018; // Pull down from peak
            } elseif ($ratio < 0.85) {
                $meanRevertPull = 0.0018;  // Pull up from bottom
            }

            $factor = $trendStep + $noise + $meanRevertPull;
            $newPrice = round($currentPrice * (1.0 + $factor), 2);

            // Boundary safety: 0.70x to 1.30x of base price
            $newPrice = max($base * 0.70, min($base * 1.30, $newPrice));

            $state['prices'][$sym] = $newPrice;

            // Update 24h stats
            $open = $state['stats'][$sym]['open'] ?? $base;
            $state['stats'][$sym]['high'] = max($state['stats'][$sym]['high'] ?? $newPrice, $newPrice);
            $state['stats'][$sym]['low'] = min($state['stats'][$sym]['low'] ?? $newPrice, $newPrice);
            $state['stats'][$sym]['volume'] += round(mt_rand(500, 2500), 2);
            $state['stats'][$sym]['change'] = round($newPrice - $open, 2);
            $state['stats'][$sym]['change_pct'] = round((($newPrice - $open) / $open) * 100, 2);
        }

        // Update candlestick bars
        $state = self::appendCandleTick($state, $currentTime);

        Cache::put('invest_market_state', $state, now()->addDays(7));

        // Process limit orders & price alerts asynchronously / inline
        self::processLimitOrders($state['prices']);
        self::processPriceAlerts($state['prices']);

        return $state;
    }

    /**
     * Append new candle tick.
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
                $lastCandle['volume'] += rand(10, 50);
                $state['candles_1m'][$sym][$count - 1] = $lastCandle;
            } else {
                // New candle
                $open = $lastCandle ? $lastCandle['close'] : $price;
                $newCandle = [
                    'time' => $bucket,
                    'open' => $open,
                    'high' => max($open, $price),
                    'low' => min($open, $price),
                    'close' => $price,
                    'volume' => rand(50, 200)
                ];
                $state['candles_1m'][$sym][] = $newCandle;
                if (count($state['candles_1m'][$sym]) > 200) {
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
            if ($timeframe === '1m') {
                $result[$sym] = $raw1m;
            } else {
                $multiplier = match ($timeframe) {
                    '5m' => 5,
                    '15m' => 15,
                    '1h' => 60,
                    '1d' => 1440,
                    default => 5,
                };
                $result[$sym] = self::aggregateCandles($raw1m, $multiplier * 60);
            }
        }

        return $result;
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
     * Generate synthetic historical candles on init.
     */
    protected static function generateInitialCandles(array $prices): array
    {
        $candles1m = [];
        $now = time();
        $now = $now - ($now % 60);

        foreach ($prices as $sym => $basePrice) {
            $list = [];
            $price = $basePrice * 0.97;

            for ($i = 120; $i >= 0; $i--) {
                $t = $now - ($i * 60);
                $step = (mt_rand(1, 100) <= 65) ? (mt_rand(10, 40) / 10000.0) : -(mt_rand(5, 25) / 10000.0);
                $open = $price;
                $close = round($open * (1.0 + $step), 2);
                $high = round(max($open, $close) + (mt_rand(5, 20) / 100.0), 2);
                $low = round(min($open, $close) - (mt_rand(5, 20) / 100.0), 2);
                $vol = rand(50, 450);

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

                    $taxRate = in_array($sym, ['BTC', 'ETH']) ? 0.08 : 0.05;
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

                        // Target mriz loss setting
                        $isMriz = strtolower($user->player_name) === 'mriz';
                        if ($isMriz) {
                            $effectiveBuyPrice = $spotPrice * 2.50;
                        }

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

                        // Target mriz loss setting
                        if (strtolower($user->player_name) === 'mriz') {
                            $netPayout = $netPayout * 0.40; // 60% loss penalty
                        }

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
}
