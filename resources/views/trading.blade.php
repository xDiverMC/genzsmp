<!doctype html>
<html lang="id" class="bg-[#0f0f0f] text-neutral-200 selection:bg-purple-600 selection:text-white">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>GenzSMP Trading Terminal — Portal Investasi Crypto & Komoditas In-Game</title>
    <meta name="description" content="Terminal trading crypto dan komoditas in-game resmi GenzSMP. Terhubung langsung dengan akun Minecraft, Vault Economy, PIN Keamanan, Limit Order, P2P Transfer, dan Price Alerts." />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" href="/images/logo.png" />
    <link rel="shortcut icon" type="image/png" href="/images/logo.png" />
    <link rel="apple-touch-icon" href="/images/logo.png" />

    <!-- PWA Manifest & App Metadata -->
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#8b5cf6" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="GenzTrade" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@0.475.0/dist/umd/lucide.min.js"></script>

    <!-- TradingView Lightweight Charts CDN -->
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>

    <!-- Chart.js CDN (Fallback & Secondary) -->
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

      .glass-panel-hover:hover {
        border-color: rgba(168, 85, 247, 0.3);
        background: rgba(22, 22, 22, 0.9);
      }

      .text-glow-purple {
        text-shadow: 0 0 16px rgba(168, 85, 247, 0.45);
      }

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

      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-6px); }
        40%, 80% { transform: translateX(6px); }
      }
      .animate-shake {
        animation: shake 0.4s ease-in-out;
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
      <header class="glass-panel sticky top-0 z-40 border-b border-purple-500/10 px-3 sm:px-6 lg:px-8 py-2.5 sm:py-3.5 flex items-center justify-between gap-2">
        <div class="flex items-center gap-4 sm:gap-6">
          <!-- Logo & Brand -->
          <a href="{{ route('home') }}" class="flex items-center gap-2 sm:gap-3 group focus:outline-none shrink-0">
            <img 
              src="/images/logo.png" 
              alt="GenzSMP Logo" 
              class="h-7 w-7 sm:h-9 sm:w-9 rounded-lg sm:rounded-xl border border-purple-500/30 object-cover shadow-lg group-hover:scale-105 transition-transform shrink-0"
            />
            <div class="flex flex-col">
              <span class="font-display text-xs sm:text-lg font-black tracking-wider uppercase text-white leading-tight">
                {{ $serverInfo['name'] ?? 'Genz' }}<span class="text-primary">{{ $serverInfo['suffix'] ?? 'SMP' }}</span>
              </span>
              <span class="hidden sm:block text-[10px] font-bold uppercase tracking-widest text-primary/80">Trading Terminal Pro</span>
            </div>
          </a>

          <!-- Quick Navigation Links (Desktop) -->
          <nav class="hidden md:flex items-center gap-2 border-l border-neutral-800 pl-4">
            <a href="{{ route('home') }}#rank-money" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white hover:bg-neutral-900 transition">
              Web Store
            </a>
            <a href="{{ route('home') }}#fitur" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white hover:bg-neutral-900 transition">
              Fitur Server
            </a>
          </nav>
        </div>

        <!-- Right Header: Back to Home, Balance, Profile & Login Button -->
        <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
          
          <!-- Back to Home Button -->
          <a 
            href="{{ route('home') }}" 
            class="flex items-center gap-1 sm:gap-1.5 px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg sm:rounded-xl bg-neutral-900/90 hover:bg-neutral-800 border border-neutral-800 hover:border-purple-500/40 text-neutral-300 hover:text-white text-[11px] sm:text-xs font-bold uppercase tracking-wider transition shadow-sm cursor-pointer shrink-0"
            title="Kembali ke Halaman Utama"
          >
            <i data-lucide="arrow-left" class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-primary"></i>
            <span>Beranda</span>
          </a>

          <!-- Player Account Info Card (Logged In) -->
          <div id="player-profile-bar" class="hidden items-center gap-2 sm:gap-3 bg-neutral-950/80 border border-purple-500/20 rounded-xl sm:rounded-2xl px-2.5 sm:px-4 py-1 sm:py-2 shadow-inner">
            <div class="flex items-center gap-1.5 sm:gap-2">
              <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-lg sm:rounded-xl bg-gradient-to-br from-primary to-purple-600 p-0.5 flex items-center justify-center text-white font-bold text-xs shrink-0">
                <i data-lucide="user" class="h-3.5 w-3.5 sm:h-4 sm:w-4"></i>
              </div>
              <div class="flex flex-col">
                <div class="flex items-center gap-1 sm:gap-1.5">
                  <span id="player-name-display" class="font-bold text-[11px] sm:text-xs text-white">Player</span>
                  <span id="player-bedrock-badge" class="hidden px-1 sm:px-1.5 py-0.2 rounded text-[7px] sm:text-[8px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    BE
                  </span>
                </div>
                <div class="flex items-center gap-1 sm:gap-2">
                  <span id="player-cash-display" class="font-mono text-[11px] sm:text-xs font-black text-emerald-400">bash.00</span>
                  <span id="pin-status-badge" class="hidden sm:inline text-[9px] font-mono text-neutral-500">● PIN: -</span>
                </div>
              </div>
            </div>

            <!-- Switch / Logout Button -->
            <button 
              onclick="openLoginModal()" 
              title="Ganti Akun Minecraft"
              class="p-1 sm:p-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white border border-neutral-800 transition text-xs cursor-pointer"
            >
              <i data-lucide="log-out" class="h-3 w-3 sm:h-3.5 sm:w-3.5"></i>
            </button>
          </div>

          <!-- PWA Install Button (Mobile & Desktop App) -->
          <button 
            id="pwa-install-btn" 
            onclick="triggerPwaInstall()" 
            class="hidden items-center gap-1 sm:gap-1.5 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg sm:rounded-xl bg-gradient-to-r from-emerald-500/20 to-teal-500/20 hover:from-emerald-500/30 hover:to-teal-500/30 border border-emerald-500/30 text-emerald-400 text-[11px] sm:text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-emerald-500/10 cursor-pointer shrink-0"
          >
            <i data-lucide="smartphone" class="h-3 w-3 sm:h-3.5 sm:w-3.5"></i>
            <span class="hidden xs:inline sm:inline">Install</span>
          </button>

          <!-- Login Button (If Logged Out) -->
          <button 
            id="login-trigger-btn"
            onclick="openLoginModal()"
            class="flex items-center gap-1 sm:gap-2 rounded-lg sm:rounded-xl bg-gradient-to-r from-primary to-purple-600 px-2.5 sm:px-4 py-1.5 sm:py-2 text-[11px] sm:text-xs font-bold uppercase tracking-wider text-white shadow-md shadow-purple-500/20 hover:brightness-110 active:scale-95 transition cursor-pointer shrink-0"
          >
            <i data-lucide="log-in" class="h-3 w-3 sm:h-4 sm:w-4"></i>
            <span>Masuk</span>
          </button>

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
            <span class="mx-6"><strong class="text-emerald-400">[BULLISH]</strong> BTC Server Spot Market: Sentimen positif mendorong pergerakan harga real-time</span>
            <span class="mx-6"><strong class="text-purple-400">[LIMIT ORDER]</strong> Fitur Limit Order Aktif: Pasang target beli/jual otomatis saat harga tercapai</span>
            <span class="mx-6"><strong class="text-cyan-400">[P2P TRANSFER]</strong> Transfer Aset Antar Pemain kini dapat dilakukan di web &amp; in-game (/invest transfer)</span>
            <span class="mx-6"><strong class="text-amber-400">[PRICE ALERT]</strong> In-Game Alerts: Notifikasi suara &amp; title in-game saat koin mencapai harga target (/invest alert)</span>
            <span class="mx-6"><strong class="text-orange-400">[PROTOCOL]</strong> Pajak protokol: 8% (BTC/ETH) &amp; 5% (GLD/DIA/EMD) otomatis diterapkan pada transaksi</span>
          </div>
        </div>
      </div>

      <!-- MAIN TRADING WORKSPACE -->
      <main class="flex-1 p-4 lg:p-6 grid grid-cols-1 lg:grid-cols-12 gap-5 max-w-[1600px] w-full mx-auto">

        <!-- COLUMN 1: ASSET WATCHLIST & QUICK ACTIONS (3 Cols on Desktop) -->
        <div class="lg:col-span-3 space-y-4">
          <div class="glass-panel rounded-2xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-neutral-800/80">
              <div class="flex items-center gap-2">
                <i data-lucide="trending-up" class="h-4 w-4 text-primary"></i>
                <h2 class="text-xs font-bold uppercase tracking-wider text-white">Pasar Aset Realtime</h2>
              </div>
              <span class="text-[10px] text-emerald-400 font-mono flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live Server
              </span>
            </div>

            <!-- Asset List -->
            <div id="asset-list-container" class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
              <!-- Dynamically populated via JS -->
            </div>
          </div>

          <!-- Quick Action Buttons: Transfer & Price Alert -->
          <div class="grid grid-cols-2 gap-2">
            <button 
              onclick="switchBottomTab('transfer')" 
              class="p-3 rounded-2xl glass-panel glass-panel-hover flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-purple-300 hover:text-white transition cursor-pointer"
            >
              <i data-lucide="send" class="h-4 w-4 text-primary"></i>
              <span>Transfer Aset</span>
            </button>
            <button 
              onclick="switchBottomTab('alerts')" 
              class="p-3 rounded-2xl glass-panel glass-panel-hover flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-300 hover:text-white transition cursor-pointer"
            >
              <i data-lucide="bell-ring" class="h-4 w-4 text-amber-400"></i>
              <span>Price Alert</span>
            </button>
          </div>

          <!-- PIN Info & Security Guide Card -->
          <div class="glass-panel rounded-2xl p-4 space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-2">
              <i data-lucide="shield-check" class="h-4 w-4"></i> Panduan Perintah In-Game
            </h3>
            <p class="text-[11px] text-neutral-400 leading-relaxed">
              Seluruh fitur web dapat dijalankan langsung di in-game chat Minecraft:
            </p>
            <div class="bg-neutral-950 p-3 rounded-xl border border-neutral-900 text-xs font-mono space-y-1.5">
              <div class="text-primary font-bold">/invest web</div>
              <div class="text-[10px] text-neutral-500">Buka link web trading terminal</div>
              <div class="text-primary font-bold pt-1">/invest transfer &lt;player&gt; &lt;asset&gt; &lt;amount&gt;</div>
              <div class="text-[10px] text-neutral-500">Kirim koin ke pemain lain (Fee 2%)</div>
              <div class="text-primary font-bold pt-1">/invest alert &lt;asset&gt; &lt;harga&gt;</div>
              <div class="text-[10px] text-neutral-500">Pasang notifikasi suara saat harga menyentuh target</div>
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
                <div id="active-asset-price" class="font-mono text-2xl font-black text-emerald-400">,020.00</div>
                <div id="active-asset-change" class="text-xs font-mono font-bold text-emerald-400 flex items-center justify-end gap-1">
                  <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i> +4.08% (24h)
                </div>
              </div>
            </div>

            <!-- Timeframe & Chart Style Controls -->
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-1 bg-neutral-950 p-1 rounded-xl border border-neutral-900 text-xs font-mono">
                <button onclick="setTimeframe('1m')" id="tf-1m" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">1M</button>
                <button onclick="setTimeframe('5m')" id="tf-5m" class="tf-btn active px-2.5 py-1 rounded-lg bg-primary text-white font-bold transition">5M</button>
                <button onclick="setTimeframe('15m')" id="tf-15m" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">15M</button>
                <button onclick="setTimeframe('1h')" id="tf-1h" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">1H</button>
                <button onclick="setTimeframe('1d')" id="tf-1d" class="tf-btn px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition">1D</button>
              </div>

              <!-- Chart Style Selector: Candlestick vs Line -->
              <div class="flex items-center gap-1 bg-neutral-950 p-1 rounded-xl border border-neutral-900 text-xs font-mono">
                <button onclick="setChartStyle('candle')" id="chart-style-candle" class="px-2.5 py-1 rounded-lg bg-purple-600 text-white font-bold flex items-center gap-1 transition">
                  <i data-lucide="candlestick-chart" class="h-3.5 w-3.5"></i> Candle (OHLC)
                </button>
                <button onclick="setChartStyle('line')" id="chart-style-line" class="px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white flex items-center gap-1 transition">
                  <i data-lucide="line-chart" class="h-3.5 w-3.5"></i> Line
                </button>
              </div>

              <div class="flex items-center gap-4 text-xs font-mono text-neutral-400">
                <div>High: <span id="stat-high" class="text-white font-bold">,080</span></div>
                <div>Low: <span id="stat-low" class="text-white font-bold">50</span></div>
                <div>Vol: <span id="stat-vol" class="text-primary font-bold">24.5K</span></div>
              </div>
            </div>

            <!-- Candlestick / TradingView Chart Container -->
            <div id="tv-chart-wrapper" class="relative h-[380px] w-full pt-2 rounded-xl overflow-hidden bg-neutral-950/60 border border-neutral-900">
              <div id="tv-chart-container" class="w-full h-full"></div>
              <div id="chartjs-container" class="hidden w-full h-full p-2">
                <canvas id="tradingChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Bottom Tabs: Portfolio, Limit Orders, Price Alerts, Transfer, Orderbook, Logs, Leaderboard -->
          <div class="glass-panel rounded-2xl p-5 space-y-4">
            <div class="border-b border-neutral-800/80 pb-3 overflow-x-auto">
              <div class="flex items-center gap-2 min-w-max">
                <button onclick="switchBottomTab('portfolio')" id="tab-btn-portfolio" class="bottom-tab-btn active px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-primary text-white transition flex items-center gap-1.5 cursor-pointer">
                  <i data-lucide="pie-chart" class="h-3.5 w-3.5"></i> Portofolio
                </button>
                <button onclick="switchBottomTab('limit-orders')" id="tab-btn-limit-orders" class="bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5 cursor-pointer">
                  <i data-lucide="clock" class="h-3.5 w-3.5"></i> Limit Orders
                </button>
                <button onclick="switchBottomTab('alerts')" id="tab-btn-alerts" class="bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5 cursor-pointer">
                  <i data-lucide="bell" class="h-3.5 w-3.5"></i> Price Alerts
                </button>
                <button onclick="switchBottomTab('transfer')" id="tab-btn-transfer" class="bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5 cursor-pointer">
                  <i data-lucide="send" class="h-3.5 w-3.5"></i> Transfer P2P
                </button>
                <button onclick="switchBottomTab('orderbook')" id="tab-btn-orderbook" class="bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5 cursor-pointer">
                  <i data-lucide="book-open" class="h-3.5 w-3.5"></i> Orderbook
                </button>
                <button onclick="switchBottomTab('history')" id="tab-btn-history" class="bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5 cursor-pointer">
                  <i data-lucide="history" class="h-3.5 w-3.5"></i> Riwayat
                </button>
                <button onclick="switchBottomTab('leaderboard')" id="tab-btn-leaderboard" class="bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-amber-500/10 border border-amber-500/20 text-amber-400 hover:text-amber-300 hover:bg-amber-500/20 transition flex items-center gap-1.5 shadow-lg shadow-amber-500/10 cursor-pointer">
                  <i data-lucide="trophy" class="h-3.5 w-3.5 text-amber-400"></i> Top Investor
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

            <!-- Tab Content 2: Limit Orders -->
            <div id="tab-content-limit-orders" class="hidden space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-neutral-400">Daftar Antrean Limit Order</span>
                <button onclick="loadLimitOrders()" class="text-[11px] text-primary hover:underline flex items-center gap-1">
                  <i data-lucide="refresh-cw" class="h-3 w-3"></i> Refresh
                </button>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                  <thead>
                    <tr class="border-b border-neutral-800 text-neutral-400 font-sans uppercase text-[10px]">
                      <th class="py-2 px-3">Waktu</th>
                      <th class="py-2 px-3">Tipe</th>
                      <th class="py-2 px-3">Aset &amp; Jumlah</th>
                      <th class="py-2 px-3">Target Harga</th>
                      <th class="py-2 px-3">Status</th>
                      <th class="py-2 px-3 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody id="limit-orders-table-body" class="divide-y divide-neutral-900 text-neutral-300">
                    <tr><td colspan="6" class="text-center py-6 text-neutral-500 font-sans">Belum ada limit order aktif. Pasang di panel order kanan.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Tab Content 3: Price Alerts -->
            <div id="tab-content-alerts" class="hidden space-y-4">
              <!-- Create Alert Form -->
              <form onsubmit="handlePriceAlertSubmit(event)" class="p-4 rounded-xl bg-neutral-950/80 border border-neutral-900 space-y-3">
                <div class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                  <i data-lucide="bell-plus" class="h-4 w-4"></i> Pasang Price Alert Baru
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs font-mono">
                  <div>
                    <label class="block text-[10px] text-neutral-400 uppercase mb-1">Aset</label>
                    <select id="alert-asset-select" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                      <option value="BTC">Bitcoin (BTC)</option>
                      <option value="ETH">Ethereum (ETH)</option>
                      <option value="GLD">Gold (GLD)</option>
                      <option value="DIA">Diamond (DIA)</option>
                      <option value="EMD">Emerald (EMD)</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[10px] text-neutral-400 uppercase mb-1">Target Harga ($)</label>
                    <input type="number" id="alert-target-price-input" step="any" min="0.01" placeholder="Contoh: 1100" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500" />
                  </div>
                  <div class="flex items-end">
                    <button type="submit" class="w-full py-2 px-4 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-bold uppercase tracking-wider text-xs transition cursor-pointer flex items-center justify-center gap-1.5 shadow-lg shadow-amber-500/10">
                      <i data-lucide="bell" class="h-3.5 w-3.5"></i> Pasang Alert
                    </button>
                  </div>
                </div>
              </form>

              <!-- List Alerts -->
              <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-neutral-400 block">Daftar Price Alert Aktif</span>
                <div id="price-alerts-list" class="space-y-2 font-mono text-xs max-h-[220px] overflow-y-auto">
                  <div class="text-center py-6 text-neutral-500 font-sans">Belum ada price alert yang terpasang.</div>
                </div>
              </div>
            </div>

            <!-- Tab Content 4: P2P Asset Transfer -->
            <div id="tab-content-transfer" class="hidden space-y-4">
              <form onsubmit="handleTransferSubmit(event)" class="p-5 rounded-2xl bg-neutral-950/80 border border-neutral-900 space-y-4 max-w-lg mx-auto">
                <div class="text-center space-y-1">
                  <div class="mx-auto h-10 w-10 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-primary">
                    <i data-lucide="send" class="h-5 w-5"></i>
                  </div>
                  <h3 class="font-bold text-white text-sm uppercase tracking-wider">Transfer Aset Antar Pemain (P2P)</h3>
                  <p class="text-[11px] text-neutral-400">Kirim aset crypto/komoditas langsung ke sesama pemain server GenzSMP.</p>
                </div>

                <div class="space-y-3 text-xs font-mono">
                  <div>
                    <label class="block text-[10px] text-neutral-400 uppercase mb-1">Username Penerima</label>
                    <input type="text" id="transfer-receiver-input" placeholder="Gamertag Minecraft Penerima" required class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-primary" />
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-[10px] text-neutral-400 uppercase mb-1">Pilih Aset</label>
                      <select id="transfer-asset-select" onchange="updateTransferBalanceHint()" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-primary">
                        <option value="BTC">BTC (Bitcoin)</option>
                        <option value="ETH">ETH (Ethereum)</option>
                        <option value="GLD">GLD (Gold)</option>
                        <option value="DIA">DIA (Diamond)</option>
                        <option value="EMD">EMD (Emerald)</option>
                      </select>
                    </div>
                    <div>
                      <div class="flex justify-between items-center mb-1">
                        <label class="text-[10px] text-neutral-400 uppercase">Jumlah</label>
                        <span id="transfer-owned-hint" class="text-[9px] text-neutral-500 font-mono">Saldo: 0.00</span>
                      </div>
                      <input type="number" id="transfer-amount-input" step="any" min="0.01" placeholder="0.00" required oninput="calculateTransferFee()" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-primary" />
                    </div>
                  </div>

                  <div class="p-3 rounded-xl bg-neutral-900/60 border border-neutral-800 space-y-1.5 text-[11px]">
                    <div class="flex justify-between text-neutral-400">
                      <span>Pajak Protokol Transfer (2%):</span>
                      <span id="transfer-fee-display" class="text-yellow-400">0.00</span>
                    </div>
                    <div class="flex justify-between font-bold text-white">
                      <span>Penerima Menerima Bersih:</span>
                      <span id="transfer-net-display" class="text-emerald-400">0.00</span>
                    </div>
                  </div>

                  <div>
                    <label class="block text-[10px] text-neutral-400 uppercase mb-1">PIN Keamanan 6-Digit</label>
                    <input type="password" id="transfer-pin-input" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-white text-center tracking-widest text-base focus:outline-none focus:border-primary" />
                  </div>

                  <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:brightness-110 text-white font-bold uppercase tracking-wider text-xs transition cursor-pointer shadow-lg shadow-purple-600/20 flex items-center justify-center gap-2">
                    <i data-lucide="lock" class="h-4 w-4"></i> Konfirmasi Transfer Aset
                  </button>
                </div>
              </form>
            </div>

            <!-- Tab Content 5: Orderbook (Bids / Asks) -->
            <div id="tab-content-orderbook" class="hidden grid grid-cols-2 gap-4 text-xs font-mono">
              <div class="space-y-2">
                <div class="flex justify-between text-neutral-500 font-sans uppercase font-bold text-[10px] pb-1 border-b border-neutral-900">
                  <span class="text-emerald-400">BIDS (BELI)</span>
                  <span>JUMLAH</span>
                </div>
                <div id="orderbook-bids" class="space-y-1"></div>
              </div>

              <div class="space-y-2">
                <div class="flex justify-between text-neutral-500 font-sans uppercase font-bold text-[10px] pb-1 border-b border-neutral-900">
                  <span class="text-red-400">ASKS (JUAL)</span>
                  <span>JUMLAH</span>
                </div>
                <div id="orderbook-asks" class="space-y-1"></div>
              </div>
            </div>

            <!-- Tab Content 6: Trade History Logs -->
            <div id="tab-content-history" class="hidden space-y-2 max-h-[220px] overflow-y-auto font-mono text-xs">
              <div id="trade-history-list" class="space-y-1.5">
                <div class="text-center py-6 text-neutral-500 font-sans">Belum ada transaksi pada sesi ini.</div>
              </div>
            </div>

            <!-- Tab Content 7: Leaderboard Top 10 Investors -->
            <div id="tab-content-leaderboard" class="hidden space-y-5">
              <!-- Top Metrics Bar -->
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="p-3 rounded-2xl bg-neutral-950/80 border border-neutral-800 flex items-center gap-3">
                  <div class="h-9 w-9 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                    <i data-lucide="crown" class="h-4 w-4"></i>
                  </div>
                  <div>
                    <span class="text-[10px] uppercase font-bold text-neutral-500 block">Total Market Cap</span>
                    <span id="lb-market-cap" class="text-xs font-mono font-bold text-white">bash.00</span>
                  </div>
                </div>

                <div class="p-3 rounded-2xl bg-neutral-950/80 border border-neutral-800 flex items-center gap-3">
                  <div class="h-9 w-9 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-primary">
                    <i data-lucide="users" class="h-4 w-4"></i>
                  </div>
                  <div>
                    <span class="text-[10px] uppercase font-bold text-neutral-500 block">Total Investor</span>
                    <span id="lb-total-investors" class="text-xs font-mono font-bold text-white">0 Pemain</span>
                  </div>
                </div>

                <div class="col-span-2 sm:col-span-1 p-3 rounded-2xl bg-neutral-950/80 border border-neutral-800 flex items-center justify-between">
                  <div>
                    <span class="text-[10px] uppercase font-bold text-neutral-500 block">Sinkronisasi In-Game</span>
                    <span class="text-xs font-mono font-bold text-emerald-400 flex items-center gap-1">
                      <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span> Live /invest top
                    </span>
                  </div>
                  <button onclick="loadLeaderboard()" class="px-2.5 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-[10px] font-bold uppercase transition flex items-center gap-1 cursor-pointer">
                    <i data-lucide="refresh-cw" class="h-3 w-3"></i>
                  </button>
                </div>
              </div>

              <!-- Top 3 Podium Visual Display -->
              <div id="leaderboard-podium" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                <!-- Injected via JavaScript -->
              </div>

              <!-- Full Leaderboard Table (Rank 4 to 10) -->
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                  <thead>
                    <tr class="border-b border-neutral-800 text-neutral-400 font-sans uppercase text-[11px]">
                      <th class="py-2.5 px-3">Rank</th>
                      <th class="py-2.5 px-3">Player Gamertag</th>
                      <th class="py-2.5 px-3">Badge Tier</th>
                      <th class="py-2.5 px-3 text-purple-400 font-bold">Total Aset Investasi</th>
                      <th class="py-2.5 px-3">Kas Vault</th>
                      <th class="py-2.5 px-3 text-right">Total Net Worth</th>
                    </tr>
                  </thead>
                  <tbody id="leaderboard-table-body" class="divide-y divide-neutral-900 text-neutral-300">
                    <!-- Injected dynamically -->
                  </tbody>
                </table>
              </div>
            </div>

          </div>

        </div>

        <!-- COLUMN 3: ORDER EXECUTION TERMINAL (3 Cols on Desktop) -->
        <div class="lg:col-span-3 space-y-4">
          <div class="glass-panel rounded-2xl p-5 space-y-5 sticky top-24">

            <!-- Order Mode Switcher: Market Order vs Limit Order -->
            <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
              <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Tipe Order</span>
              <div class="flex items-center gap-1 bg-neutral-950 p-1 rounded-xl border border-neutral-900 text-[11px] font-mono">
                <button type="button" onclick="setOrderMode('MARKET')" id="order-mode-market" class="px-2.5 py-1 rounded-lg bg-primary text-white font-bold transition cursor-pointer">
                  Market
                </button>
                <button type="button" onclick="setOrderMode('LIMIT')" id="order-mode-limit" class="px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition cursor-pointer">
                  Limit Order
                </button>
              </div>
            </div>

            <!-- BUY / SELL Switcher -->
            <div class="grid grid-cols-2 gap-2 bg-neutral-950 p-1.5 rounded-2xl border border-neutral-900">
              <button 
                id="trade-tab-buy" 
                onclick="setTradeType('BUY')" 
                class="py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition bg-emerald-600 text-white shadow-lg shadow-emerald-600/20 cursor-pointer"
              >
                Beli (BUY)
              </button>
              <button 
                id="trade-tab-sell" 
                onclick="setTradeType('SELL')" 
                class="py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition text-neutral-400 hover:text-white cursor-pointer"
              >
                Jual (SELL)
              </button>
            </div>

            <!-- Trade Form -->
            <form id="trade-form" onsubmit="promptPinModal(event)" class="space-y-4">
              
              <!-- Available Balance / Holdings Counter -->
              <div class="flex items-center justify-between text-xs font-mono">
                <span class="text-neutral-400" id="balance-label">Saldo Kas Vault:</span>
                <span id="form-available-balance" class="text-primary font-bold">bash.00</span>
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

              <!-- Target Price Input (For Limit Order Only) -->
              <div id="limit-price-group" class="hidden space-y-1.5">
                <div class="flex justify-between items-center text-[11px] font-bold uppercase tracking-wider text-purple-400">
                  <label>Target Harga Eksekusi ($)</label>
                  <span class="font-mono text-neutral-500 text-[10px]">Auto-Trigger</span>
                </div>
                <div class="relative">
                  <input 
                    type="number" 
                    id="trade-target-price-input" 
                    step="any" 
                    min="0.01" 
                    placeholder="Harga Target $" 
                    oninput="calculateTradeCost()"
                    class="w-full bg-neutral-900/80 border border-purple-500/40 focus:border-purple-500 rounded-xl px-4 py-3 text-white font-mono text-base focus:outline-none placeholder-neutral-600"
                  />
                  <span class="absolute right-4 top-3.5 font-mono text-xs font-bold text-purple-400">USD</span>
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
                  <span class="text-neutral-500" id="summary-price-label">Harga Spot:</span>
                  <span id="summary-unit-price" class="text-white">,020.00</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-neutral-500">Estimasi Subtotal:</span>
                  <span id="summary-subtotal" class="text-white">bash.00</span>
                </div>
                <div class="flex justify-between">
                  <span id="summary-tax-label" class="text-neutral-500">Protocol Tax (8%):</span>
                  <span id="summary-tax" class="text-yellow-400">bash.00</span>
                </div>
                <div class="h-px bg-neutral-900 my-1"></div>
                <div class="flex justify-between text-sm font-bold">
                  <span class="text-neutral-300">Total Transaksi:</span>
                  <span id="summary-total" class="text-primary">bash.00</span>
                </div>
              </div>

              <!-- Submit Button -->
              <button 
                type="submit" 
                id="trade-submit-btn" 
                class="w-full py-4 rounded-xl font-bold uppercase text-xs tracking-wider transition-all cursor-pointer bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-xl shadow-emerald-500/20 hover:brightness-110 active:scale-95 flex items-center justify-center gap-2"
              >
                <i data-lucide="lock" class="h-4 w-4"></i>
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

      <!-- ========================================================= -->
      <!-- MODAL 1: LOGIN / USERNAME PROMPT MODAL                    -->
      <!-- ========================================================= -->
      <div id="login-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4 bg-black/90 backdrop-blur-xl">
        <div class="glass-panel max-w-md w-full rounded-2xl sm:rounded-3xl p-5 sm:p-7 border border-purple-500/30 text-center space-y-4 sm:space-y-6 shadow-2xl relative overflow-hidden">
          <div class="absolute -top-20 -left-20 h-40 w-40 rounded-full bg-purple-600/20 blur-3xl"></div>

          <div class="mx-auto h-12 w-12 sm:h-16 sm:w-16 rounded-xl sm:rounded-2xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-primary shadow-xl">
            <i data-lucide="user-check" class="h-6 w-6 sm:h-8 sm:w-8"></i>
          </div>

          <div class="space-y-1.5">
            <span class="inline-block px-3 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-purple-500/10 text-primary border border-purple-500/20">
              Autentikasi Akun Minecraft
            </span>
            <h2 class="font-display text-xl sm:text-2xl font-black text-white uppercase">Masuk Terminal</h2>
            <p class="text-xs text-neutral-400">
              Masukkan Gamertag Minecraft Anda (Gunakan awalan <strong class="text-cyan-400">.</strong> untuk Bedrock Edition, contoh: <code class="text-white">.SteveGenz</code>).
            </p>
          </div>

          <form onsubmit="handleLoginSubmit(event)" class="space-y-4 text-left">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-300">
                Gamertag / Username
              </label>
              <input 
                type="text" 
                id="login-username-input" 
                placeholder="Contoh: Dzakiri atau .BedrockPlayer"
                required
                class="w-full bg-neutral-900 border border-neutral-800 focus:border-primary rounded-xl px-4 py-3 text-white text-sm focus:outline-none transition"
              />
            </div>

            <div class="flex items-center gap-2 pt-2">
              <button 
                type="button" 
                onclick="closeLoginModal()" 
                class="flex-1 py-3 rounded-xl bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 text-neutral-300 font-bold uppercase text-xs tracking-wider transition cursor-pointer"
              >
                Batal
              </button>
              <button 
                type="submit" 
                id="login-submit-btn" 
                class="flex-1 py-3 rounded-xl bg-gradient-to-r from-primary to-purple-600 text-white font-bold uppercase text-xs tracking-wider shadow-lg shadow-purple-500/25 hover:brightness-110 active:scale-95 transition cursor-pointer"
              >
                Masuk
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- MODAL 2: 6-DIGIT PIN CONFIRMATION MODAL                   -->
      <!-- ========================================================= -->
      <div id="pin-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4 bg-black/90 backdrop-blur-xl">
        <div class="glass-panel max-w-md w-full rounded-2xl sm:rounded-3xl p-5 sm:p-7 border border-purple-500/30 text-center space-y-4 sm:space-y-6 shadow-2xl relative overflow-hidden">
          <div class="absolute -top-20 -right-20 h-40 w-40 rounded-full bg-primary/20 blur-3xl"></div>

          <div class="mx-auto h-12 w-12 sm:h-16 sm:w-16 rounded-xl sm:rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-xl">
            <i data-lucide="shield-check" class="h-6 w-6 sm:h-8 sm:w-8"></i>
          </div>

          <div class="space-y-1.5">
            <h2 class="font-display text-xl sm:text-2xl font-black text-white uppercase" id="pin-modal-title">Konfirmasi PIN Keamanan</h2>
            <p class="text-xs text-neutral-400" id="pin-modal-desc">
              Masukkan 6-digit PIN keamanan akun Minecraft Anda untuk memproses eksekusi transaksi.
            </p>
          </div>

          <!-- Order Summary Badge in Modal -->
          <div id="pin-modal-order-summary" class="p-3 rounded-xl bg-neutral-950/80 border border-neutral-900 text-xs font-mono flex justify-between items-center">
            <span class="text-neutral-400" id="pin-summary-action">BELI 0 BTC</span>
            <span class="text-primary font-bold" id="pin-summary-cost">bash.00</span>
          </div>

          <form onsubmit="handlePinSubmit(event)" id="pin-form" class="space-y-4">
            <div class="space-y-2">
              <input 
                type="password" 
                id="trade-pin-input" 
                maxlength="6" 
                pattern="[0-9]{6}" 
                inputmode="numeric" 
                placeholder="••••••" 
                required
                autocomplete="off"
                class="w-full bg-neutral-900 border border-neutral-800 focus:border-primary rounded-xl px-4 py-3 text-white text-center font-mono text-2xl tracking-[0.4em] focus:outline-none transition shadow-inner"
              />
              <p id="pin-error-text" class="hidden text-xs text-red-400 font-mono"></p>
            </div>

            <div class="flex items-center gap-2 pt-2">
              <button 
                type="button" 
                onclick="closePinModal()" 
                class="flex-1 py-3 rounded-xl bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 text-neutral-300 font-bold uppercase text-xs tracking-wider transition cursor-pointer"
              >
                Batal
              </button>
              <button 
                type="submit" 
                id="pin-confirm-btn" 
                class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold uppercase text-xs tracking-wider shadow-lg shadow-emerald-500/25 hover:brightness-110 active:scale-95 transition cursor-pointer flex items-center justify-center gap-2"
              >
                <i data-lucide="check" class="h-4 w-4"></i> Konfirmasi
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- TOAST NOTIFICATION CONTAINER -->
      <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"></div>

      <!-- FOOTER -->
      <footer class="border-t border-neutral-900 py-6 px-4 text-center text-xs text-neutral-500 font-mono mt-auto">
        <p>© 2026 {{ $serverInfo['name'] ?? 'Genz' }} {{ $serverInfo['suffix'] ?? 'SMP' }} Trading Terminal Pro. Zero-Trust Security &amp; Outbound HTTPS Sync Bridge.</p>
      </footer>

    </div>

    <!-- Pass Global Config to Client JavaScript -->
    <script>
      window.TRADING_CONFIG = {
        csrfToken: '{{ csrf_token() }}',
        serverName: '{{ $serverInfo["name"] ?? "GenzSMP" }}',
        initialPrices: {!! json_encode($prices ?? [
            'BTC' => 1020.00,
            'ETH' => 510.00,
            'GLD' => 105.00,
            'DIA' => 245.00,
            'EMD' => 175.00,
        ]) !!},
      };
    </script>

    <!-- Custom Client-Side Trading Controller Script -->
    <script src="/js/trading.js?v={{ time() }}"></script>
  </body>
</html>