<!doctype html>
<html lang="id" class="bg-[#0f0f0f] text-neutral-200 selection:bg-purple-600 selection:text-white">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GenzSMP Trading Terminal — Portal Investasi Crypto & Komoditas In-Game</title>
    <meta name="description" content="Terminal trading crypto dan komoditas in-game resmi GenzSMP. Terhubung langsung secara real-time dua arah dengan akun Minecraft dan Vault Economy." />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@0.475.0/dist/umd/lucide.min.js"></script>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Tailwind Configuration -->
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: '#a855f7',
              secondary: '#f97316',
              accent: '#8B3DFF',
              'dark-bg': '#0f0f0f',
              'panel-bg': '#141414',
              'panel-border': 'rgba(168, 85, 247, 0.15)',
              success: '#10B981',
              danger: '#EF4444',
            },
            fontFamily: {
              sans: ['"Plus Jakarta Sans"', 'sans-serif'],
              display: ['"Space Grotesk"', 'sans-serif'],
              mono: ['"JetBrains Mono"', 'monospace'],
            }
          }
        }
      }
    </script>

    <!-- Custom Styles Matching GenzSMP Design System -->
    <style>
      /* Custom Scrollbar */
      ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
      }
      ::-webkit-scrollbar-track {
        background: #0f0f0f;
      }
      ::-webkit-scrollbar-thumb {
        background: #262626;
        border-radius: 4px;
      }
      ::-webkit-scrollbar-thumb:hover {
        background: #a855f7;
      }

      .glass-panel {
        background: rgba(18, 18, 18, 0.75);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(168, 85, 247, 0.12);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
      }

      .glass-panel-hover {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .glass-panel-hover:hover {
        border-color: rgba(168, 85, 247, 0.3);
        background: rgba(22, 22, 22, 0.9);
      }

      .text-glow-purple {
        text-shadow: 0 0 16px rgba(168, 85, 247, 0.45);
      }

      .text-glow-green {
        text-shadow: 0 0 14px rgba(16, 185, 129, 0.45);
      }

      .text-glow-red {
        text-shadow: 0 0 14px rgba(239, 68, 68, 0.45);
      }

      /* Ticker animation */
      @keyframes tickerSlide {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
      }

      .animate-ticker {
        display: inline-flex;
        white-space: nowrap;
        animation: tickerSlide 35s linear infinite;
      }

      .animate-ticker:hover {
        animation-play-state: paused;
      }

      /* Pulse Ring Animation */
      .pulse-ring {
        position: relative;
      }
      .pulse-ring::before {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: inherit;
        background: inherit;
        opacity: 0.4;
        animation: pulseRing 2s cubic-bezier(0.24, 0, 0.38, 1) infinite;
      }
      @keyframes pulseRing {
        0% { transform: scale(0.95); opacity: 0.6; }
        100% { transform: scale(1.6); opacity: 0; }
      }
    </style>
  </head>
  <body class="min-h-screen bg-[#0f0f0f] font-sans text-neutral-200 overflow-x-hidden">

    <!-- Glowing Background Ambient Blobs -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
      <div class="absolute top-[5%] left-[10%] h-[600px] w-[600px] rounded-full bg-purple-600/5 blur-[140px]"></div>
      <div class="absolute bottom-[10%] right-[5%] h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"></div>
    </div>

    <!-- MAIN APP CONTAINER -->
    <div id="trading-app" class="relative z-10 min-h-screen flex flex-col">

      <!-- NAVBAR HEADER -->
      <header class="glass-panel sticky top-0 z-40 border-b border-purple-500/10 px-4 lg:px-8 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-6">
          <!-- Logo & Brand -->
          <a href="{{ route('home') }}" class="flex items-center gap-3 group focus:outline-none">
            <img 
              src="{{ asset('images/logo.png') }}" 
              alt="GenzSMP Logo" 
              class="h-9 w-9 rounded-xl border border-purple-500/30 object-cover shadow-lg group-hover:scale-105 transition-transform"
            />
            <div class="flex flex-col">
              <span class="font-display text-lg font-black tracking-wider uppercase text-white leading-tight">
                {{ $serverInfo['name'] }}<span class="text-primary">{{ $serverInfo['suffix'] }}</span>
              </span>
              <span class="text-[10px] font-bold uppercase tracking-widest text-primary/80">Trading Terminal Pro</span>
            </div>
          </a>

          <!-- Quick Navigation Link back to portal -->
          <nav class="hidden md:flex items-center gap-2 border-l border-neutral-800 pl-6">
            <a href="{{ route('home') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white hover:bg-neutral-900 transition">
              Portal Home
            </a>
            <a href="{{ route('home') }}#rank-money" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white hover:bg-neutral-900 transition">
              Web Store
            </a>
            <a href="{{ route('home') }}#fitur" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white hover:bg-neutral-900 transition">
              Fitur Server
            </a>
          </nav>
        </div>

        <!-- Right Header: Player Vault Balance & Session Indicator -->
        <div class="flex items-center gap-3">
          <!-- Live WebSocket Status Indicator -->
          <div id="ws-status-badge" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[11px] font-bold bg-neutral-900 border border-neutral-800">
            <span id="ws-status-dot" class="h-2 w-2 rounded-full bg-yellow-400 animate-pulse"></span>
            <span id="ws-status-text" class="text-neutral-300">Menghubungkan...</span>
          </div>

          <!-- Player Account Info Card -->
          <div class="flex items-center gap-3 bg-neutral-950/80 border border-purple-500/20 rounded-2xl px-4 py-2 shadow-inner">
            <div class="h-8 w-8 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-primary shrink-0">
              <i data-lucide="wallet" class="h-4 w-4"></i>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase font-bold text-neutral-400">Vault Balance (Cash)</span>
              <span id="player-cash-display" class="font-mono text-sm font-black text-emerald-400">$0.00</span>
            </div>
          </div>

          <!-- Session Countdown Badge -->
          <div class="hidden lg:flex flex-col items-end bg-neutral-950/80 border border-neutral-800 rounded-2xl px-3.5 py-2">
            <span class="text-[10px] uppercase font-bold text-neutral-500">Sesi Berlaku</span>
            <span id="session-timer-display" class="font-mono text-xs font-bold text-primary">15:00</span>
          </div>
        </div>
      </header>

      <!-- GENZNEWS LIVE SENTIMENT TICKER -->
      <div class="bg-neutral-950 border-b border-neutral-900 overflow-hidden py-2 px-4 flex items-center gap-4 text-xs font-mono">
        <div class="flex items-center gap-2 shrink-0 text-primary font-bold uppercase tracking-wider bg-purple-500/10 border border-purple-500/20 rounded-lg px-2.5 py-1">
          <span class="h-2 w-2 rounded-full bg-purple-400 animate-ping"></span>
          <span>GenzNews</span>
        </div>
        <div class="overflow-hidden relative flex-1">
          <div id="news-ticker" class="animate-ticker text-neutral-300">
            <span class="mx-6">🚀 BTC Market Bullish: Sentimen positif mendorong kenaikan harga +4.2%</span>
            <span class="mx-6">💎 Diamond Mining Spike: Pasokan DIA stabil di level $245</span>
            <span class="mx-6">⚡ ETH Smart Economy: Likuiditas likuid di pasar in-game GenzSMP</span>
            <span class="mx-6">👑 Protokol Transaksi: 2% burn tax diterapkan otomatis pada setiap penjualan</span>
            <span class="mx-6">🛡️ Anti-Whale Engine: Rate-limit 5 detik melindungi volatilitas likuiditas</span>
          </div>
        </div>
      </div>

      <!-- MAIN TRADING WORKSPACE -->
      <main class="flex-1 p-4 lg:p-6 grid grid-cols-1 lg:grid-cols-12 gap-5 max-w-[1600px] w-full mx-auto">

        <!-- COLUMN 1: ASSET WATCHLIST & SELECTOR (3 Cols on Desktop) -->
        <div class="lg:col-span-3 space-y-4">
          <div class="glass-panel rounded-2xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-neutral-800/80">
              <div class="flex items-center gap-2">
                <i data-lucide="trending-up" class="h-4 w-4 text-primary"></i>
                <h2 class="text-xs font-bold uppercase tracking-wider text-white">Pasar Aset In-Game</h2>
              </div>
              <span class="text-[10px] text-neutral-500 font-mono">5 Aset Aktif</span>
            </div>

            <!-- Asset List -->
            <div id="asset-list-container" class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
              <!-- Dynamically populated via JS -->
            </div>
          </div>

          <!-- Market Summary Widget -->
          <div class="glass-panel rounded-2xl p-4 space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400 flex items-center gap-2">
              <i data-lucide="activity" class="h-4 w-4 text-primary"></i> Info Protokol Finansial
            </h3>
            <div class="space-y-2 text-xs font-mono">
              <div class="flex justify-between py-1 border-b border-neutral-900">
                <span class="text-neutral-500">Protocol Tax</span>
                <span class="text-white font-bold">2.0% (Burn)</span>
              </div>
              <div class="flex justify-between py-1 border-b border-neutral-900">
                <span class="text-neutral-500">Anti-Whale Cooldown</span>
                <span class="text-primary font-bold">5 Detik</span>
              </div>
              <div class="flex justify-between py-1 border-b border-neutral-900">
                <span class="text-neutral-500">Max Order Limit</span>
                <span class="text-white font-bold">1,000 Unit</span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-neutral-500">Engine Sinkronisasi</span>
                <span class="text-emerald-400 font-bold">HikariCP + Vault</span>
              </div>
            </div>
          </div>
        </div>

        <!-- COLUMN 2: CHART & REALTIME TELEMETRY (6 Cols on Desktop) -->
        <div class="lg:col-span-6 space-y-4">

          <!-- Active Asset Header & Chart Card -->
          <div class="glass-panel rounded-2xl p-5 space-y-4">
            
            <!-- Asset Stat Bar -->
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-neutral-800/80">
              <div class="flex items-center gap-3">
                <div id="active-asset-icon" class="h-11 w-11 rounded-2xl bg-gradient-to-br from-primary to-purple-600 p-0.5 shadow-lg flex items-center justify-center text-white font-black text-sm">
                  BTC
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h1 id="active-asset-title" class="font-display text-xl font-black text-white">Bitcoin (BTC)</h1>
                    <span id="active-asset-category" class="px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-purple-500/10 text-primary border border-purple-500/20">
                      Crypto
                    </span>
                  </div>
                  <p id="active-asset-desc" class="text-xs text-neutral-400 max-w-sm line-clamp-1">
                    Aset crypto terkemuka dengan kapitalisasi pasar terbesar.
                  </p>
                </div>
              </div>

              <!-- Price & 24h Change -->
              <div class="text-right">
                <div id="active-asset-price" class="font-mono text-2xl font-black text-emerald-400">$1,020.00</div>
                <div id="active-asset-change" class="text-xs font-mono font-bold text-emerald-400 flex items-center justify-end gap-1">
                  <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i> +4.08% (24h)
                </div>
              </div>
            </div>

            <!-- Timeframe & Chart Type Controls -->
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-1 bg-neutral-950 p-1 rounded-xl border border-neutral-900 text-xs font-mono">
                <button onclick="setTimeframe('1M')" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">1M</button>
                <button onclick="setTimeframe('5M')" class="tf-btn active px-2.5 py-1 rounded-lg bg-primary text-white font-bold transition">5M</button>
                <button onclick="setTimeframe('15M')" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">15M</button>
                <button onclick="setTimeframe('1H')" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">1H</button>
                <button onclick="setTimeframe('1D')" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">1D</button>
              </div>

              <!-- High / Low / Volume Mini Stats -->
              <div class="flex items-center gap-4 text-xs font-mono text-neutral-400">
                <div>24h High: <span id="stat-high" class="text-white font-bold">$1,080</span></div>
                <div>24h Low: <span id="stat-low" class="text-white font-bold">$950</span></div>
                <div>Vol: <span id="stat-vol" class="text-primary font-bold">$124.5K</span></div>
              </div>
            </div>

            <!-- Chart Canvas Container -->
            <div class="relative h-[360px] w-full pt-2">
              <canvas id="tradingChart"></canvas>
            </div>
          </div>

          <!-- Bottom Tabs: Orderbook & Portfolio & Logs -->
          <div class="glass-panel rounded-2xl p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-800/80 pb-3">
              <div class="flex gap-2">
                <button onclick="switchBottomTab('portfolio')" id="tab-btn-portfolio" class="bottom-tab-btn active px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider bg-primary text-white transition flex items-center gap-1.5">
                  <i data-lucide="pie-chart" class="h-3.5 w-3.5"></i> Portofolio Saya
                </button>
                <button onclick="switchBottomTab('orderbook')" id="tab-btn-orderbook" class="bottom-tab-btn px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5">
                  <i data-lucide="book-open" class="h-3.5 w-3.5"></i> Orderbook
                </button>
                <button onclick="switchBottomTab('history')" id="tab-btn-history" class="bottom-tab-btn px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5">
                  <i data-lucide="history" class="h-3.5 w-3.5"></i> Riwayat Transaksi
                </button>
              </div>
            </div>

            <!-- Tab Content 1: My Portfolio -->
            <div id="tab-content-portfolio" class="space-y-3">
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                  <thead>
                    <tr class="border-b border-neutral-800 text-neutral-400 font-sans uppercase">
                      <th class="py-2 px-3">Aset</th>
                      <th class="py-2 px-3">Jumlah Dimiliki</th>
                      <th class="py-2 px-3">Harga Beli Rata-Rata</th>
                      <th class="py-2 px-3">Nilai Sekarang</th>
                      <th class="py-2 px-3">PnL (Untung/Rugi)</th>
                    </tr>
                  </thead>
                  <tbody id="portfolio-table-body" class="divide-y divide-neutral-900 text-neutral-300">
                    <!-- Populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Tab Content 2: Orderbook (Bids / Asks) -->
            <div id="tab-content-orderbook" class="hidden grid grid-cols-2 gap-4 text-xs font-mono">
              <!-- Bids (Buy Orders) -->
              <div class="space-y-2">
                <div class="flex justify-between text-neutral-500 font-sans uppercase font-bold text-[10px] pb-1 border-b border-neutral-900">
                  <span class="text-emerald-400">BIDS (BELI)</span>
                  <span>JUMLAH</span>
                </div>
                <div id="orderbook-bids" class="space-y-1"></div>
              </div>

              <!-- Asks (Sell Orders) -->
              <div class="space-y-2">
                <div class="flex justify-between text-neutral-500 font-sans uppercase font-bold text-[10px] pb-1 border-b border-neutral-900">
                  <span class="text-red-400">ASKS (JUAL)</span>
                  <span>JUMLAH</span>
                </div>
                <div id="orderbook-asks" class="space-y-1"></div>
              </div>
            </div>

            <!-- Tab Content 3: Trade History Logs -->
            <div id="tab-content-history" class="hidden space-y-2 max-h-[220px] overflow-y-auto font-mono text-xs">
              <div id="trade-history-list" class="space-y-1.5">
                <div class="text-center py-6 text-neutral-500 font-sans">Belum ada transaksi pada sesi ini.</div>
              </div>
            </div>

          </div>

        </div>

        <!-- COLUMN 3: ORDER EXECUTION TERMINAL (3 Cols on Desktop) -->
        <div class="lg:col-span-3 space-y-4">
          <div class="glass-panel rounded-2xl p-5 space-y-5 sticky top-24">

            <!-- BUY / SELL Switcher -->
            <div class="grid grid-cols-2 gap-2 bg-neutral-950 p-1.5 rounded-2xl border border-neutral-900">
              <button 
                id="trade-tab-buy" 
                onclick="setTradeType('BUY')" 
                class="py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition bg-emerald-600 text-white shadow-lg shadow-emerald-600/20"
              >
                Beli (BUY)
              </button>
              <button 
                id="trade-tab-sell" 
                onclick="setTradeType('SELL')" 
                class="py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition text-neutral-400 hover:text-white"
              >
                Jual (SELL)
              </button>
            </div>

            <!-- Trade Form -->
            <form id="trade-form" onsubmit="handleTradeSubmit(event)" class="space-y-4">
              
              <!-- Available Balance / Holdings Counter -->
              <div class="flex items-center justify-between text-xs font-mono">
                <span class="text-neutral-400" id="balance-label">Saldo Kas:</span>
                <span id="form-available-balance" class="text-primary font-bold">$0.00</span>
              </div>

              <!-- Order Type Selector -->
              <div class="space-y-1.5">
                <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-400">Tipe Order</label>
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <button type="button" class="py-2 rounded-xl bg-purple-500/10 text-primary border border-purple-500/30 font-bold">
                    Market (Instan)
                  </button>
                  <button type="button" disabled class="py-2 rounded-xl bg-neutral-900 border border-neutral-800 text-neutral-500 opacity-50 cursor-not-allowed">
                    Limit (Segera)
                  </button>
                </div>
              </div>

              <!-- Quantity Input -->
              <div class="space-y-1.5">
                <div class="flex justify-between items-center text-[11px] font-bold uppercase tracking-wider text-neutral-400">
                  <label>Jumlah Unit</label>
                  <span id="max-unit-hint" class="font-mono text-neutral-500">Max: 1000</span>
                </div>
                <div class="relative">
                  <input 
                    type="number" 
                    id="trade-amount-input" 
                    step="any" 
                    min="0.01" 
                    max="1000" 
                    placeholder="0.00" 
                    required
                    oninput="calculateTradeCost()"
                    class="w-full bg-neutral-900/80 border border-neutral-800 focus:border-primary rounded-xl px-4 py-3 text-white font-mono text-base focus:outline-none placeholder-neutral-600"
                  />
                  <span id="trade-unit-symbol" class="absolute right-4 top-3.5 font-mono text-xs font-bold text-neutral-400">BTC</span>
                </div>
              </div>

              <!-- Percentage Shortcut Buttons -->
              <div class="grid grid-cols-4 gap-2 text-xs font-mono font-bold">
                <button type="button" onclick="setPercentageAmount(0.25)" class="py-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white border border-neutral-800 transition">25%</button>
                <button type="button" onclick="setPercentageAmount(0.50)" class="py-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white border border-neutral-800 transition">50%</button>
                <button type="button" onclick="setPercentageAmount(0.75)" class="py-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white border border-neutral-800 transition">75%</button>
                <button type="button" onclick="setPercentageAmount(1.00)" class="py-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-primary hover:text-white border border-purple-500/30 transition">100%</button>
              </div>

              <!-- Order Summary Breakdown -->
              <div class="p-3.5 rounded-xl bg-neutral-950/90 border border-neutral-900 space-y-2 text-xs font-mono">
                <div class="flex justify-between">
                  <span class="text-neutral-500">Harga Spot:</span>
                  <span id="summary-unit-price" class="text-white">$1,020.00</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-neutral-500">Estimasi Subtotal:</span>
                  <span id="summary-subtotal" class="text-white">$0.00</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-neutral-500">Protocol Tax (2%):</span>
                  <span id="summary-tax" class="text-yellow-400">$0.00</span>
                </div>
                <div class="h-px bg-neutral-900 my-1"></div>
                <div class="flex justify-between text-sm font-bold">
                  <span class="text-neutral-300">Total Transaksi:</span>
                  <span id="summary-total" class="text-primary">$0.00</span>
                </div>
              </div>

              <!-- Submit Button -->
              <button 
                type="submit" 
                id="trade-submit-btn" 
                class="w-full py-4 rounded-xl font-bold uppercase text-xs tracking-wider transition-all cursor-pointer bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-xl shadow-emerald-500/20 hover:brightness-110 active:scale-95 flex items-center justify-center gap-2"
              >
                <i data-lucide="arrow-right-left" class="h-4 w-4"></i>
                <span id="trade-btn-text">Eksekusi Order Beli (BUY)</span>
              </button>
            </form>

            <!-- Anti-Whale Cooldown Visual Indicator -->
            <div id="cooldown-indicator" class="hidden p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-mono flex items-center gap-2">
              <i data-lucide="timer" class="h-4 w-4 animate-spin shrink-0"></i>
              <span>Anti-Whale Cooldown: <strong id="cooldown-seconds">5s</strong></span>
            </div>

          </div>
        </div>

      </main>

      <!-- ACCESS DENIED FULLSCREEN OVERLAY (Shown if no token/player provided) -->
      <div id="access-denied-modal" class="{{ $isValidAccess ? 'hidden' : 'flex' }} fixed inset-0 z-50 items-center justify-center p-4 bg-black/90 backdrop-blur-xl">
        <div class="glass-panel max-w-lg w-full rounded-3xl p-8 border border-purple-500/20 text-center space-y-6 shadow-2xl relative overflow-hidden">
          <div class="absolute -top-24 -left-24 h-48 w-48 rounded-full bg-purple-600/20 blur-3xl"></div>
          
          <div class="mx-auto h-20 w-20 rounded-2xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-primary shadow-xl">
            <i data-lucide="shield-alert" class="h-10 w-10"></i>
          </div>

          <div class="space-y-2">
            <span class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-500/10 text-red-400 border border-red-500/20">
              Zero-Trust Security
            </span>
            <h2 class="font-display text-2xl font-black text-white uppercase">Akses Trading Terkunci</h2>
            <p class="text-xs text-neutral-400 leading-relaxed">
              Portal Trading GenzSMP menggunakan enkripsi Zero-Trust session token yang hanya dapat digenerate secara langsung dari dalam in-game Minecraft.
            </p>
          </div>

          <!-- Walkthrough Box -->
          <div class="text-left bg-neutral-950/80 border border-neutral-900 rounded-2xl p-4 space-y-3 text-xs">
            <h4 class="font-bold text-white uppercase tracking-wider flex items-center gap-2">
              <i data-lucide="terminal" class="h-4 w-4 text-primary"></i> Cara Membuka Sesi Trading:
            </h4>
            <ol class="space-y-2 text-neutral-300">
              <li class="flex items-start gap-2.5">
                <span class="h-5 w-5 rounded bg-purple-500/20 text-primary text-[10px] font-mono font-bold flex items-center justify-center shrink-0 mt-0.5">1</span>
                <span>Masuk ke server Minecraft: <strong class="text-white font-mono">genzsmp.site</strong></span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="h-5 w-5 rounded bg-purple-500/20 text-primary text-[10px] font-mono font-bold flex items-center justify-center shrink-0 mt-0.5">2</span>
                <span>Ketik perintah di chat in-game: <strong class="text-primary font-mono font-bold">/invest web</strong></span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="h-5 w-5 rounded bg-purple-500/20 text-primary text-[10px] font-mono font-bold flex items-center justify-center shrink-0 mt-0.5">3</span>
                <span>Klik link aman yang dikirimkan oleh server di chat in-game untuk login otomatis.</span>
              </li>
            </ol>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-col sm:flex-row gap-3 pt-2">
            <button 
              onclick="startDemoMode()" 
              class="flex-1 py-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs uppercase tracking-wider transition cursor-pointer"
            >
              Jelajahi Mode Demo (Preview)
            </button>
            <a 
              href="{{ route('home') }}" 
              class="flex-1 py-3 rounded-xl bg-neutral-900 border border-neutral-800 text-neutral-300 hover:text-white font-bold text-xs uppercase tracking-wider transition text-center"
            >
              Kembali ke Home
            </a>
          </div>
        </div>
      </div>

      <!-- SESSION TERMINATED OVERLAY -->
      <div id="session-terminated-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4 bg-black/90 backdrop-blur-xl">
        <div class="glass-panel max-w-md w-full rounded-3xl p-8 border border-red-500/20 text-center space-y-6 shadow-2xl">
          <div class="mx-auto h-20 w-20 rounded-2xl bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400">
            <i data-lucide="lock" class="h-10 w-10"></i>
          </div>

          <div class="space-y-2">
            <h2 class="font-display text-2xl font-black text-white uppercase">Sesi Trading Berakhir</h2>
            <p id="terminated-reason-text" class="text-xs text-neutral-400 leading-relaxed">
              Sesi web trading telah ditutup oleh server in-game atau waktu sesi (15 menit) telah habis.
            </p>
          </div>

          <div class="flex gap-3">
            <a 
              href="{{ route('home') }}" 
              class="w-full py-3.5 rounded-xl bg-primary hover:bg-purple-600 text-white font-bold text-xs uppercase tracking-wider transition"
            >
              Kembali ke Web Portal
            </a>
          </div>
        </div>
      </div>

      <!-- TOAST NOTIFICATION CONTAINER -->
      <div id="toast-container" class="fixed bottom-6 right-6 z-50 space-y-2 pointer-events-none"></div>

    </div>

    <!-- Inject Server & Trading Configuration -->
    <script>
      window.TRADING_CONFIG = {
        token: "{{ $token }}",
        player: "{{ $player }}",
        isValidAccess: {{ $isValidAccess ? 'true' : 'false' }},
        wsPort: {{ $tradingConfig['ws_port'] }},
        wsHost: "{{ $tradingConfig['ws_host'] }}",
        taxPercent: {{ $tradingConfig['tax_percent'] }},
        cooldownSeconds: {{ $tradingConfig['cooldown_seconds'] }},
        sessionTtlSeconds: {{ $tradingConfig['session_ttl_seconds'] }},
        assets: @json($tradingConfig['assets']),
        csrfToken: "{{ csrf_token() }}"
      };
    </script>

    <!-- Trading Controller Script -->
    <script src="{{ asset('js/trading.js') }}"></script>
  </body>
</html>
