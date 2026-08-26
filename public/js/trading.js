// =======================================================
//   GENZSMP WEB TRADING CONTROLLER & ENGINE PRO
// =======================================================

const CONFIG = window.TRADING_CONFIG || {};

const state = {
  session: {
    isLoggedIn: false,
    playerName: '',
    uuid: '',
    isBedrock: false,
    hasPin: false,
    cashBalance: 0.00,
    expireSeconds: CONFIG.sessionTtlSeconds || 900
  },
  activeAsset: 'btc',
  activeTradeType: 'BUY', // BUY or SELL
  orderMode: 'MARKET',    // MARKET or LIMIT
  chartStyle: 'candle',   // candle or line
  timeframe: '5m',        // 1m, 5m, 15m, 1h, 1d
  cooldownActive: false,
  cooldownRemaining: 0,
  pendingTrade: null,
  assets: {
    btc: { symbol: 'BTC', name: 'Bitcoin', category: 'Crypto', price: 1020.00, openPrice: 1020.00, high: 1080.00, low: 950.00, volume: 124500, tax_percent: 8.0, changePercent: 0.00 },
    eth: { symbol: 'ETH', name: 'Ethereum', category: 'Crypto', price: 510.00, openPrice: 510.00, high: 540.00, low: 480.00, volume: 62100, tax_percent: 8.0, changePercent: 0.00 },
    gld: { symbol: 'GLD', name: 'Gold Ingot', category: 'Commodity', price: 105.00, openPrice: 105.00, high: 110.00, low: 98.00, volume: 18400, tax_percent: 8.0, changePercent: 0.00 },
    dia: { symbol: 'DIA', name: 'Diamond Gem', category: 'Commodity', price: 245.00, openPrice: 245.00, high: 260.00, low: 240.00, volume: 34200, tax_percent: 8.0, changePercent: 0.00 },
    emd: { symbol: 'EMD', name: 'Emerald Shard', category: 'Commodity', price: 175.00, openPrice: 175.00, high: 190.00, low: 160.00, volume: 28900, tax_percent: 8.0, changePercent: 0.00 }
  },
  portfolio: {
    btc: { amount: 0, avgBuyPrice: 0 },
    eth: { amount: 0, avgBuyPrice: 0 },
    gld: { amount: 0, avgBuyPrice: 0 },
    dia: { amount: 0, avgBuyPrice: 0 },
    emd: { amount: 0, avgBuyPrice: 0 }
  },
  candles: {},
  limitOrders: [],
  priceAlerts: [],
  tradeLogs: []
};

let tvChart = null;
let tvCandleSeries = null;
let tvVolumeSeries = null;
let chartInstance = null; // Chart.js fallback
let cooldownTimer = null;

// =======================================================
//   INITIALIZATION
// =======================================================
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) {
    lucide.createIcons();
  }

  renderAssetList();
  updateActiveAssetDisplay();
  initTradingViewChart();
  renderPortfolioTable();
  renderOrderbook();
  calculateTradeCost();

  // Check login: URL query param or saved localStorage
  const urlParams = new URLSearchParams(window.location.search);
  const playerParam = urlParams.get('player');
  const savedPlayer = localStorage.getItem('genzsmp_trading_player');

  if (playerParam) {
    loginPlayer(playerParam);
  } else if (savedPlayer) {
    loginPlayer(savedPlayer);
  }

  // Load saved chart style preference (Candle or Classic Line Chart)
  const savedChartStyle = localStorage.getItem('genzsmp_chart_style') || 'candle';
  if (savedChartStyle === 'line') {
    setChartStyle('line');
  }

  // Poll server market data every 3 seconds for uniform server prices & candles
  fetchMarketData();
  setInterval(fetchMarketData, 3000);
});

// =======================================================
//   MARKET DATA & LIVE SERVER SYNC
// =======================================================
async function fetchMarketData() {
  try {
    const res = await fetch('/api/trading/market-data?timeframe=' + state.timeframe);
    const json = await res.json();
    if (json.success && json.data) {
      const data = json.data;
      
      // Update spot prices and 24h stats
      if (data.prices) {
        for (const [sym, price] of Object.entries(data.prices)) {
          const key = sym.toLowerCase();
          if (state.assets[key]) {
            state.assets[key].price = price;
            if (data.stats && data.stats[sym]) {
              const st = data.stats[sym];
              state.assets[key].openPrice = st.open;
              state.assets[key].high = st.high;
              state.assets[key].low = st.low;
              state.assets[key].volume = st.volume;
              state.assets[key].changePercent = st.change_pct;
            }
          }
        }
      }

      // Update candles
      if (data.candles) {
        state.candles = data.candles;
        updateChartData();
      }

      // Update Golden Bull Surge Banner
      if (data.lucky_surge) {
        updateLuckySurgeBanner(data.lucky_surge);
      }

      renderAssetList();
      updateActiveAssetDisplay();
      calculateTradeCost();
      renderPortfolioTable();
      renderOrderbook();
    }
  } catch (e) {
    // console.warn('Market data fetch error:', e);
  }
}

// =======================================================
//   TRADINGVIEW LIGHTWEIGHT CHARTS / CHART.JS
// =======================================================
function initTradingViewChart() {
  const container = document.getElementById('tv-chart-container');
  if (!container) return;

  if (window.LightweightCharts) {
    try {
      container.innerHTML = '';
      tvChart = LightweightCharts.createChart(container, {
        width: container.clientWidth || 600,
        height: 370,
        layout: {
          background: { color: 'transparent' },
          textColor: '#9ca3af',
          fontSize: 11,
          fontFamily: 'JetBrains Mono, monospace',
        },
        grid: {
          vertLines: { color: 'rgba(255, 255, 255, 0.04)' },
          horzLines: { color: 'rgba(255, 255, 255, 0.04)' },
        },
        crosshair: {
          mode: LightweightCharts.CrosshairMode.Normal,
        },
        rightPriceScale: {
          borderColor: 'rgba(255, 255, 255, 0.1)',
        },
        timeScale: {
          borderColor: 'rgba(255, 255, 255, 0.1)',
          timeVisible: true,
          secondsVisible: false,
        },
      });

      tvCandleSeries = tvChart.addCandlestickSeries({
        upColor: '#10B981',
        downColor: '#EF4444',
        borderDownColor: '#EF4444',
        borderUpColor: '#10B981',
        wickDownColor: '#EF4444',
        wickUpColor: '#10B981',
      });

      tvVolumeSeries = tvChart.addHistogramSeries({
        color: '#8b5cf6',
        priceFormat: { type: 'volume' },
        priceScaleId: '',
        scaleMargins: { top: 0.8, bottom: 0 },
      });

      window.addEventListener('resize', () => {
        if (tvChart && container) {
          tvChart.applyOptions({ width: container.clientWidth });
        }
      });
    } catch (err) {
      console.warn('LightweightCharts init failed, using Chart.js fallback', err);
      initChartJsFallback();
    }
  } else {
    initChartJsFallback();
  }
}

function initChartJsFallback() {
  const chartWrapper = document.getElementById('chartjs-container');
  const tvWrapper = document.getElementById('tv-chart-container');
  if (chartWrapper) chartWrapper.classList.remove('hidden');
  if (tvWrapper) tvWrapper.classList.add('hidden');

  const ctx = document.getElementById('tradingChart');
  if (!ctx) return;

  if (chartInstance) {
    chartInstance.destroy();
  }

  const asset = state.assets[state.activeAsset];
  const isUp = (asset && asset.changePercent >= 0);
  const gradientColor = isUp ? 'rgba(16, 185, 129, 0.25)' : 'rgba(239, 68, 68, 0.25)';
  const lineColor = isUp ? '#10B981' : '#EF4444';

  const sym = asset ? asset.symbol : 'BTC';
  const rawCandles = state.candles[sym] || [];
  let labels = [];
  let dataPoints = [];

  if (rawCandles.length > 0) {
    labels = rawCandles.map(c => {
      const d = new Date(c.time * 1000);
      return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
    });
    dataPoints = rawCandles.map(c => c.close);
  } else {
    labels = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    dataPoints = [asset.price * 0.98, asset.price * 0.99, asset.price * 0.985, asset.price * 1.01, asset.price];
  }

  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: asset.symbol + ' Price ($)',
        data: dataPoints,
        borderColor: lineColor,
        backgroundColor: gradientColor,
        borderWidth: 2.5,
        fill: true,
        tension: 0.35,
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBackgroundColor: lineColor,
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        intersect: false,
        mode: 'index',
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0a0a0a',
          titleColor: '#fff',
          bodyColor: isUp ? '#34d399' : '#f87171',
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          padding: 10,
          displayColors: false,
          callbacks: {
            label: function(context) {
              return asset.symbol + ': $' + parseFloat(context.parsed.y).toFixed(2);
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,0.03)' },
          ticks: { color: '#9ca3af', font: { family: 'JetBrains Mono', size: 10 } }
        },
        y: {
          grid: { color: 'rgba(255,255,255,0.03)' },
          ticks: {
            color: '#9ca3af',
            font: { family: 'JetBrains Mono', size: 10 },
            callback: function(value) { return '$' + value; }
          }
        }
      }
    }
  });
}

function updateChartData() {
  const sym = state.assets[state.activeAsset].symbol;
  const rawCandles = state.candles[sym] || [];

  if (state.chartStyle === 'line') {
    if (chartInstance && rawCandles.length > 0) {
      const labels = rawCandles.map(c => {
        const d = new Date(c.time * 1000);
        return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
      });
      const dataPoints = rawCandles.map(c => c.close);
      const isUp = (state.assets[state.activeAsset] && state.assets[state.activeAsset].changePercent >= 0);
      const lineColor = isUp ? '#10B981' : '#EF4444';
      const gradientColor = isUp ? 'rgba(16, 185, 129, 0.25)' : 'rgba(239, 68, 68, 0.25)';

      chartInstance.data.labels = labels;
      chartInstance.data.datasets[0].data = dataPoints;
      chartInstance.data.datasets[0].borderColor = lineColor;
      chartInstance.data.datasets[0].backgroundColor = gradientColor;
      chartInstance.data.datasets[0].pointHoverBackgroundColor = lineColor;
      chartInstance.update('none');
    } else {
      initChartJsFallback();
    }
    return;
  }

  if (tvCandleSeries && rawCandles.length > 0) {
    try {
      const candleData = rawCandles.map(c => ({
        time: c.time,
        open: c.open,
        high: c.high,
        low: c.low,
        close: c.close
      }));
      tvCandleSeries.setData(candleData);

      if (tvVolumeSeries) {
        const volumeData = rawCandles.map(c => ({
          time: c.time,
          value: c.volume,
          color: c.close >= c.open ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)'
        }));
        tvVolumeSeries.setData(volumeData);
      }
    } catch (e) {}
  }
}

function setTimeframe(tf) {
  state.timeframe = tf;
  document.querySelectorAll('.tf-btn').forEach(btn => {
    btn.classList.remove('active', 'bg-primary', 'text-white', 'font-bold');
    btn.classList.add('text-neutral-400');
  });
  const activeBtn = document.getElementById('tf-' + tf.toLowerCase());
  if (activeBtn) {
    activeBtn.classList.add('active', 'bg-primary', 'text-white', 'font-bold');
    activeBtn.classList.remove('text-neutral-400');
  }
  fetchMarketData();
}

function setChartStyle(style) {
  state.chartStyle = style;
  localStorage.setItem('genzsmp_chart_style', style);
  const candleBtn = document.getElementById('chart-style-candle');
  const lineBtn = document.getElementById('chart-style-line');
  const tvWrapper = document.getElementById('tv-chart-container');
  const chartJsWrapper = document.getElementById('chartjs-container');

  if (style === 'candle') {
    if (candleBtn) {
      candleBtn.className = 'px-3 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold flex items-center gap-1.5 transition shadow-md shadow-purple-500/20 cursor-pointer';
    }
    if (lineBtn) {
      lineBtn.className = 'px-3 py-1.5 rounded-lg text-neutral-400 hover:text-white flex items-center gap-1.5 transition cursor-pointer';
    }
    if (tvWrapper) tvWrapper.classList.remove('hidden');
    if (chartJsWrapper) chartJsWrapper.classList.add('hidden');
    if (tvCandleSeries) updateChartData();
  } else {
    if (lineBtn) {
      lineBtn.className = 'px-3 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold flex items-center gap-1.5 transition shadow-md shadow-purple-500/20 cursor-pointer';
    }
    if (candleBtn) {
      candleBtn.className = 'px-3 py-1.5 rounded-lg text-neutral-400 hover:text-white flex items-center gap-1.5 transition cursor-pointer';
    }
    if (tvWrapper) tvWrapper.classList.add('hidden');
    if (chartJsWrapper) chartJsWrapper.classList.remove('hidden');
    initChartJsFallback();
  }
  if (window.lucide) lucide.createIcons();
}

// =======================================================
//   AUTHENTICATION & LOGIN
// =======================================================
function openLoginModal() {
  const modal = document.getElementById('login-modal');
  const input = document.getElementById('login-username-input');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (input) {
      input.value = state.session.playerName || '';
      input.focus();
    }
  }
}

function closeLoginModal() {
  const modal = document.getElementById('login-modal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

async function handleLoginSubmit(e) {
  e.preventDefault();
  const input = document.getElementById('login-username-input');
  if (!input) return;
  const username = input.value.trim();
  if (!username) return;

  const btn = document.getElementById('login-submit-btn');
  if (btn) btn.disabled = true;

  await loginPlayer(username);
  if (btn) btn.disabled = false;
  closeLoginModal();
}

async function loginPlayer(username) {
  try {
    const res = await fetch('/api/trading/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({ player_name: username })
    });

    const json = await res.json();
    if (json.success && json.data) {
      const u = json.data.user;
      state.session.isLoggedIn = true;
      state.session.playerName = u.player_name;
      state.session.uuid = u.uuid || '';
      state.session.isBedrock = u.is_bedrock;
      state.session.hasPin = u.has_pin;
      state.session.cashBalance = u.cash_balance;

      localStorage.setItem('genzsmp_trading_player', u.player_name);

      // Populate portfolio
      if (json.data.portfolio) {
        for (const [assetKey, val] of Object.entries(json.data.portfolio)) {
          if (state.portfolio[assetKey]) {
            state.portfolio[assetKey].amount = parseFloat(val[0]) || 0;
            state.portfolio[assetKey].avgBuyPrice = parseFloat(val[1]) || 0;
          }
        }
      }

      // Populate trade logs
      if (json.data.trades) {
        state.tradeLogs = json.data.trades;
        renderTradeHistory();
      }

      updateUserUI();
      renderPortfolioTable();
      calculateTradeCost();
      loadLimitOrders();
      loadPriceAlerts();
      showToast('success', 'Selamat datang di Trading Terminal, ' + u.player_name + '!');
    } else {
      showToast('error', json.message || 'Gagal login.');
    }
  } catch (err) {
    showToast('error', 'Koneksi ke backend server gagal.');
  }
}

function updateUserUI() {
  const profileBar = document.getElementById('player-profile-bar');
  const loginTrigger = document.getElementById('login-trigger-btn');
  const nameDisplay = document.getElementById('player-name-display');
  const cashDisplay = document.getElementById('player-cash-display');
  const bedrockBadge = document.getElementById('player-bedrock-badge');
  const pinBadge = document.getElementById('pin-status-badge');

  if (state.session.isLoggedIn) {
    if (profileBar) { profileBar.classList.remove('hidden'); profileBar.classList.add('flex'); }
    if (loginTrigger) loginTrigger.classList.add('hidden');
    if (nameDisplay) nameDisplay.textContent = state.session.playerName;
    if (cashDisplay) cashDisplay.textContent = formatCurrency(state.session.cashBalance);
    if (bedrockBadge) {
      if (state.session.isBedrock) bedrockBadge.classList.remove('hidden');
      else bedrockBadge.classList.add('hidden');
    }
    if (pinBadge) {
      pinBadge.textContent = state.session.hasPin ? '● PIN: Aktif' : '● PIN: Belum Diatur';
      pinBadge.className = state.session.hasPin ? 'hidden sm:inline text-[9px] font-mono text-emerald-400' : 'hidden sm:inline text-[9px] font-mono text-amber-400';
    }
  } else {
    if (profileBar) { profileBar.classList.add('hidden'); profileBar.classList.remove('flex'); }
    if (loginTrigger) loginTrigger.classList.remove('hidden');
  }
}

// =======================================================
//   WATCHLIST & ASSET SELECTION
// =======================================================
function renderAssetList() {
  const container = document.getElementById('asset-list-container');
  if (!container) return;

  container.innerHTML = Object.entries(state.assets).map(([key, a]) => {
    const isSelected = state.activeAsset === key;
    const isUp = a.changePercent >= 0;
    const changeClass = isUp ? 'text-emerald-400' : 'text-red-400';
    const bgClass = isSelected ? 'bg-purple-950/40 border-primary/50 shadow-lg' : 'bg-neutral-950/60 border-neutral-800/80 hover:border-neutral-700';

    return `
      <div onclick="selectAsset('${key}')" class="p-3 rounded-xl border ${bgClass} transition flex items-center justify-between cursor-pointer group">
        <div class="flex items-center gap-2.5">
          <div class="h-8 w-8 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center font-bold text-xs text-primary group-hover:scale-105 transition">
            ${a.symbol}
          </div>
          <div>
            <div class="font-bold text-xs text-white">${a.name}</div>
            <div class="text-[10px] text-neutral-400 font-mono">${a.category} • Pajak ${a.tax_percent}%</div>
          </div>
        </div>
        <div class="text-right">
          <div class="font-mono text-xs font-bold text-white">$${a.price.toFixed(2)}</div>
          <div class="text-[10px] font-mono font-semibold ${changeClass}">${isUp ? '+' : ''}${a.changePercent.toFixed(2)}%</div>
        </div>
      </div>
    `;
  }).join('');
}

function selectAsset(key) {
  if (!state.assets[key]) return;
  state.activeAsset = key;
  renderAssetList();
  updateActiveAssetDisplay();
  calculateTradeCost();
  updateChartData();
}

function updateActiveAssetDisplay() {
  const a = state.assets[state.activeAsset];
  if (!a) return;

  const icon = document.getElementById('active-asset-icon');
  const title = document.getElementById('active-asset-title');
  const category = document.getElementById('active-asset-category');
  const price = document.getElementById('active-asset-price');
  const change = document.getElementById('active-asset-change');
  const symbolHint = document.getElementById('trade-unit-symbol');
  const summaryUnitPrice = document.getElementById('summary-unit-price');
  const summaryTaxLabel = document.getElementById('summary-tax-label');

  if (icon) icon.textContent = a.symbol;
  if (title) title.textContent = `${a.name} (${a.symbol})`;
  if (category) category.textContent = a.category;
  if (price) price.textContent = `$${a.price.toFixed(2)}`;

  const isUp = a.changePercent >= 0;
  if (change) {
    change.className = `text-xs font-mono font-bold flex items-center justify-end gap-1 ${isUp ? 'text-emerald-400' : 'text-red-400'}`;
    change.innerHTML = `<i data-lucide="${isUp ? 'arrow-up-right' : 'arrow-down-right'}" class="h-3.5 w-3.5"></i> ${isUp ? '+' : ''}${a.changePercent.toFixed(2)}% (24h)`;
  }

  if (symbolHint) symbolHint.textContent = a.symbol;
  if (summaryUnitPrice) summaryUnitPrice.textContent = `$${a.price.toFixed(2)}`;
  if (summaryTaxLabel) summaryTaxLabel.textContent = `Protocol Tax (${a.tax_percent}%):`;

  const high = document.getElementById('stat-high');
  const low = document.getElementById('stat-low');
  const vol = document.getElementById('stat-vol');
  if (high) high.textContent = `$${a.high.toFixed(2)}`;
  if (low) low.textContent = `$${a.low.toFixed(2)}`;
  if (vol) vol.textContent = `$${(a.volume / 1000).toFixed(1)}K`;

  if (window.lucide) lucide.createIcons();
}

// =======================================================
//   ORDER EXECUTION (MARKET & LIMIT ORDER)
// =======================================================
function setOrderMode(mode) {
  state.orderMode = mode;
  const marketBtn = document.getElementById('order-mode-market');
  const limitBtn = document.getElementById('order-mode-limit');
  const limitPriceGroup = document.getElementById('limit-price-group');
  const tradeBtnText = document.getElementById('trade-btn-text');
  const priceLabel = document.getElementById('summary-price-label');

  if (mode === 'LIMIT') {
    if (limitBtn) limitBtn.className = 'px-2.5 py-1 rounded-lg bg-primary text-white font-bold transition cursor-pointer';
    if (marketBtn) marketBtn.className = 'px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition cursor-pointer';
    if (limitPriceGroup) limitPriceGroup.classList.remove('hidden');
    if (priceLabel) priceLabel.textContent = 'Target Limit:';
    if (tradeBtnText) tradeBtnText.textContent = 'Pasang Limit Order (' + state.activeTradeType + ')';
  } else {
    if (marketBtn) marketBtn.className = 'px-2.5 py-1 rounded-lg bg-primary text-white font-bold transition cursor-pointer';
    if (limitBtn) limitBtn.className = 'px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white transition cursor-pointer';
    if (limitPriceGroup) limitPriceGroup.classList.add('hidden');
    if (priceLabel) priceLabel.textContent = 'Harga Spot:';
    if (tradeBtnText) tradeBtnText.textContent = 'Eksekusi Order ' + (state.activeTradeType === 'BUY' ? 'Beli (BUY)' : 'Jual (SELL)');
  }

  calculateTradeCost();
}

function setTradeType(type) {
  state.activeTradeType = type;
  const buyBtn = document.getElementById('trade-tab-buy');
  const sellBtn = document.getElementById('trade-tab-sell');
  const submitBtn = document.getElementById('trade-submit-btn');
  const balanceLabel = document.getElementById('balance-label');
  const availableBal = document.getElementById('form-available-balance');
  const tradeBtnText = document.getElementById('trade-btn-text');

  if (type === 'BUY') {
    if (buyBtn) buyBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition bg-emerald-600 text-white shadow-lg shadow-emerald-600/20 cursor-pointer';
    if (sellBtn) sellBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition text-neutral-400 hover:text-white cursor-pointer';
    if (submitBtn) submitBtn.className = 'w-full py-4 rounded-xl font-bold uppercase text-xs tracking-wider transition-all cursor-pointer bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-xl shadow-emerald-500/20 hover:brightness-110 active:scale-95 flex items-center justify-center gap-2';
    if (balanceLabel) balanceLabel.textContent = 'Saldo Kas Vault:';
    if (availableBal) availableBal.textContent = formatCurrency(state.session.cashBalance);
    if (tradeBtnText) tradeBtnText.textContent = state.orderMode === 'LIMIT' ? 'Pasang Limit Buy' : 'Eksekusi Order Beli (BUY)';
  } else {
    if (sellBtn) sellBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition bg-red-600 text-white shadow-lg shadow-red-600/20 cursor-pointer';
    if (buyBtn) buyBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition text-neutral-400 hover:text-white cursor-pointer';
    if (submitBtn) submitBtn.className = 'w-full py-4 rounded-xl font-bold uppercase text-xs tracking-wider transition-all cursor-pointer bg-gradient-to-r from-red-500 to-rose-600 text-white shadow-xl shadow-red-500/20 hover:brightness-110 active:scale-95 flex items-center justify-center gap-2';
    if (balanceLabel) balanceLabel.textContent = 'Aset ' + state.assets[state.activeAsset].symbol + ' Dimiliki:';
    const owned = state.portfolio[state.activeAsset] ? state.portfolio[state.activeAsset].amount : 0;
    if (availableBal) availableBal.textContent = owned.toFixed(2) + ' ' + state.assets[state.activeAsset].symbol;
    if (tradeBtnText) tradeBtnText.textContent = state.orderMode === 'LIMIT' ? 'Pasang Limit Sell' : 'Eksekusi Order Jual (SELL)';
  }

  calculateTradeCost();
}

function calculateTradeCost() {
  const amountInput = document.getElementById('trade-amount-input');
  const targetPriceInput = document.getElementById('trade-target-price-input');
  const summarySubtotal = document.getElementById('summary-subtotal');
  const summaryTax = document.getElementById('summary-tax');
  const summaryTotal = document.getElementById('summary-total');
  const summaryUnitPrice = document.getElementById('summary-unit-price');

  const asset = state.assets[state.activeAsset];
  const amount = parseFloat(amountInput ? amountInput.value : 0) || 0;
  
  let unitPrice = asset.price;
  if (state.orderMode === 'LIMIT' && targetPriceInput && parseFloat(targetPriceInput.value) > 0) {
    unitPrice = parseFloat(targetPriceInput.value);
  }

  if (summaryUnitPrice) summaryUnitPrice.textContent = '$' + unitPrice.toFixed(2);

  const subtotal = amount * unitPrice;
  const tax = subtotal * (asset.tax_percent / 100.0);
  const total = state.activeTradeType === 'BUY' ? (subtotal + tax) : (subtotal - tax);

  if (summarySubtotal) summarySubtotal.textContent = formatCurrency(subtotal);
  if (summaryTax) summaryTax.textContent = formatCurrency(tax);
  if (summaryTotal) summaryTotal.textContent = formatCurrency(total);
}

function setPercentageAmount(pct) {
  const amountInput = document.getElementById('trade-amount-input');
  const asset = state.assets[state.activeAsset];
  if (!amountInput) return;

  if (state.activeTradeType === 'BUY') {
    const cash = state.session.cashBalance;
    const taxMultiplier = 1.0 + (asset.tax_percent / 100.0);
    const targetPriceInput = document.getElementById('trade-target-price-input');
    const unitPrice = (state.orderMode === 'LIMIT' && targetPriceInput && parseFloat(targetPriceInput.value) > 0)
      ? parseFloat(targetPriceInput.value) : asset.price;

    const maxUnits = (cash * pct) / (unitPrice * taxMultiplier);
    amountInput.value = Math.min(1000000, Math.max(0, Math.floor(maxUnits * 100) / 100)).toFixed(2);
  } else {
    const owned = state.portfolio[state.activeAsset] ? state.portfolio[state.activeAsset].amount : 0;
    amountInput.value = Math.min(1000000, owned * pct).toFixed(2);
  }

  calculateTradeCost();
}

function promptPinModal(e) {
  e.preventDefault();
  if (!state.session.isLoggedIn) {
    openLoginModal();
    return;
  }

  const amountInput = document.getElementById('trade-amount-input');
  const targetPriceInput = document.getElementById('trade-target-price-input');
  const amount = parseFloat(amountInput ? amountInput.value : 0);
  const asset = state.assets[state.activeAsset];

  if (!amount || amount <= 0) {
    showToast('error', 'Masukkan jumlah unit transaksi yang valid.');
    return;
  }

  let targetPrice = asset.price;
  if (state.orderMode === 'LIMIT') {
    targetPrice = parseFloat(targetPriceInput ? targetPriceInput.value : 0);
    if (!targetPrice || targetPrice <= 0) {
      showToast('error', 'Masukkan target harga limit order yang valid.');
      return;
    }
  }

  state.pendingTrade = {
    orderMode: state.orderMode,
    tradeType: state.activeTradeType,
    asset: asset.symbol,
    amount: amount,
    price: targetPrice
  };

  const modal = document.getElementById('pin-modal');
  const summaryAction = document.getElementById('pin-summary-action');
  const summaryCost = document.getElementById('pin-summary-cost');
  const pinInput = document.getElementById('trade-pin-input');
  const errText = document.getElementById('pin-error-text');

  if (summaryAction) summaryAction.textContent = (state.orderMode === 'LIMIT' ? 'LIMIT ' : '') + state.activeTradeType + ' ' + amount + ' ' + asset.symbol + ' @ $' + targetPrice.toFixed(2);
  const subtotal = amount * targetPrice;
  const tax = subtotal * (asset.tax_percent / 100.0);
  const total = state.activeTradeType === 'BUY' ? (subtotal + tax) : (subtotal - tax);
  if (summaryCost) summaryCost.textContent = formatCurrency(total);

  if (errText) errText.classList.add('hidden');
  if (pinInput) pinInput.value = '';

  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (pinInput) pinInput.focus();
  }
}

function closePinModal() {
  const modal = document.getElementById('pin-modal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

async function handlePinSubmit(e) {
  e.preventDefault();
  const pinInput = document.getElementById('trade-pin-input');
  const pin = pinInput ? pinInput.value.trim() : '';

  if (!pin || pin.length !== 6 || !/^\d{6}$/.test(pin)) {
    showPinError('PIN harus 6 digit angka.');
    return;
  }

  const btn = document.getElementById('pin-confirm-btn');
  if (btn) btn.disabled = true;

  if (state.pendingTrade.orderMode === 'LIMIT') {
    await executeLimitOrder(pin);
  } else {
    await executeMarketTrade(pin);
  }

  if (btn) btn.disabled = false;
}

function showPinError(msg) {
  const errText = document.getElementById('pin-error-text');
  const pinInput = document.getElementById('trade-pin-input');
  if (errText) {
    errText.textContent = msg;
    errText.classList.remove('hidden');
  }
  if (pinInput) {
    pinInput.classList.add('animate-shake', 'border-red-500');
    setTimeout(() => pinInput.classList.remove('animate-shake', 'border-red-500'), 500);
  }
}

async function executeMarketTrade(pin) {
  try {
    const res = await fetch('/api/trading/trade', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({
        player_name: state.session.playerName,
        pin: pin,
        trade_type: state.pendingTrade.tradeType,
        asset: state.pendingTrade.asset,
        amount: state.pendingTrade.amount,
        price: state.pendingTrade.price
      })
    });

    const json = await res.json();
    if (json.success) {
      closePinModal();
      state.session.cashBalance = json.data.new_cash_balance;
      
      // Update local portfolio
      if (json.data.portfolio) {
        for (const [k, v] of Object.entries(json.data.portfolio)) {
          if (state.portfolio[k]) {
            state.portfolio[k].amount = parseFloat(v[0]);
            state.portfolio[k].avgBuyPrice = parseFloat(v[1]);
          }
        }
      }

      if (json.data.trade) {
        state.tradeLogs.unshift(json.data.trade);
        renderTradeHistory();
      }

      updateUserUI();
      renderPortfolioTable();
      calculateTradeCost();
      showToast('success', json.message);
      startCooldown(5);
    } else {
      showPinError(json.message || 'Transaksi gagal.');
    }
  } catch (err) {
    showPinError('Gagal menghubungkan ke server.');
  }
}

async function executeLimitOrder(pin) {
  try {
    const res = await fetch('/api/trading/limit-order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({
        player_name: state.session.playerName,
        pin: pin,
        order_type: state.pendingTrade.tradeType,
        asset: state.pendingTrade.asset,
        amount: state.pendingTrade.amount,
        target_price: state.pendingTrade.price
      })
    });

    const json = await res.json();
    if (json.success) {
      closePinModal();
      if (json.data.new_cash_balance !== undefined) {
        state.session.cashBalance = json.data.new_cash_balance;
      }
      if (json.data.portfolio) {
        for (const [k, v] of Object.entries(json.data.portfolio)) {
          if (state.portfolio[k]) {
            state.portfolio[k].amount = parseFloat(v[0]);
            state.portfolio[k].avgBuyPrice = parseFloat(v[1]);
          }
        }
      }

      updateUserUI();
      renderPortfolioTable();
      loadLimitOrders();
      showToast('success', json.message);
      switchBottomTab('limit-orders');
    } else {
      showPinError(json.message || 'Gagal memasang limit order.');
    }
  } catch (err) {
    showPinError('Koneksi timeout.');
  }
}

function startCooldown(sec) {
  state.cooldownActive = true;
  state.cooldownRemaining = sec;
  const indicator = document.getElementById('cooldown-indicator');
  const countSpan = document.getElementById('cooldown-seconds');
  const submitBtn = document.getElementById('trade-submit-btn');

  if (indicator) indicator.classList.remove('hidden');
  if (submitBtn) submitBtn.disabled = true;

  if (cooldownTimer) clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    state.cooldownRemaining--;
    if (countSpan) countSpan.textContent = state.cooldownRemaining + 's';
    if (state.cooldownRemaining <= 0) {
      clearInterval(cooldownTimer);
      state.cooldownActive = false;
      if (indicator) indicator.classList.add('hidden');
      if (submitBtn) submitBtn.disabled = false;
    }
  }, 1000);
}

// =======================================================
//   LIMIT ORDERS TAB & MANAGEMENT
// =======================================================
async function loadLimitOrders() {
  if (!state.session.isLoggedIn) return;
  try {
    const res = await fetch('/api/trading/limit-orders?player_name=' + encodeURIComponent(state.session.playerName));
    const json = await res.json();
    if (json.success && json.data) {
      state.limitOrders = json.data;
      renderLimitOrdersTable();
    }
  } catch (e) {}
}

function renderLimitOrdersTable() {
  const tbody = document.getElementById('limit-orders-table-body');
  if (!tbody) return;

  if (state.limitOrders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-6 text-neutral-500 font-sans">Belum ada limit order. Pasang di panel kanan dengan memilih tipe Limit Order.</td></tr>';
    return;
  }

  tbody.innerHTML = state.limitOrders.map(o => {
    const isBuy = o.order_type === 'BUY';
    const typeBadge = isBuy
      ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">LIMIT BUY</span>'
      : '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20">LIMIT SELL</span>';

    const statusBadge = o.status === 'PENDING'
      ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-primary border border-purple-500/20 animate-pulse">PENDING</span>'
      : o.status === 'FILLED'
      ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">FILLED</span>'
      : '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-800 text-neutral-400">CANCELLED</span>';

    const cancelBtn = o.status === 'PENDING'
      ? '<button onclick="cancelLimitOrder(' + o.id + ')" class="px-2.5 py-1 rounded bg-red-900/40 hover:bg-red-800 border border-red-700/50 text-red-300 text-[10px] font-bold uppercase transition">Batal</button>'
      : '-';

    return `
      <tr class="border-b border-neutral-900/60 hover:bg-neutral-900/30">
        <td class="py-2.5 px-3 text-neutral-500 text-[11px]">${o.created_at ? o.created_at.substring(11, 16) : '-'}</td>
        <td class="py-2.5 px-3">${typeBadge}</td>
        <td class="py-2.5 px-3 font-bold text-white">${parseFloat(o.amount).toFixed(2)} ${o.asset}</td>
        <td class="py-2.5 px-3 font-bold text-purple-400">$${parseFloat(o.target_price).toFixed(2)}</td>
        <td class="py-2.5 px-3">${statusBadge}</td>
        <td class="py-2.5 px-3 text-right">${cancelBtn}</td>
      </tr>
    `;
  }).join('');
}

async function cancelLimitOrder(orderId) {
  try {
    const res = await fetch('/api/trading/cancel-limit-order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({
        player_name: state.session.playerName,
        order_id: orderId
      })
    });
    const json = await res.json();
    if (json.success) {
      showToast('success', json.message);
      loginPlayer(state.session.playerName);
      loadLimitOrders();
    } else {
      showToast('error', json.message);
    }
  } catch (e) {
    showToast('error', 'Gagal membatalkan order.');
  }
}

// =======================================================
//   PRICE ALERTS TAB
// =======================================================
async function loadPriceAlerts() {
  if (!state.session.isLoggedIn) return;
  try {
    const res = await fetch('/api/trading/alerts?player_name=' + encodeURIComponent(state.session.playerName));
    const json = await res.json();
    if (json.success && json.data) {
      state.priceAlerts = json.data;
      renderPriceAlertsList();
    }
  } catch (e) {}
}

function renderPriceAlertsList() {
  const container = document.getElementById('price-alerts-list');
  if (!container) return;

  if (state.priceAlerts.length === 0) {
    container.innerHTML = '<div class="text-center py-6 text-neutral-500 font-sans">Belum ada price alert yang terpasang.</div>';
    return;
  }

  container.innerHTML = state.priceAlerts.map(a => {
    const condText = a.condition === 'ABOVE' ? '≥ (Naik Menembus)' : '≤ (Turun Menyentuh)';
    const statusText = a.is_triggered
      ? '<span class="text-emerald-400 font-bold">⚡ TRIGGERED</span>'
      : '<span class="text-amber-400 font-bold">ACTIVE</span>';

    return `
      <div class="p-3 rounded-xl bg-neutral-950/80 border border-neutral-900 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center font-bold text-amber-400 text-xs">
            ${a.asset}
          </div>
          <div>
            <div class="font-bold text-white text-xs">${a.asset} ${condText} $${parseFloat(a.target_price).toFixed(2)}</div>
            <div class="text-[10px] text-neutral-500">${statusText} • Pasang di $${parseFloat(a.initial_price).toFixed(2)}</div>
          </div>
        </div>
        <button onclick="cancelPriceAlert(${a.id})" class="p-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-red-400 text-xs font-bold transition">
          <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
        </button>
      </div>
    `;
  }).join('');
  if (window.lucide) lucide.createIcons();
}

async function handlePriceAlertSubmit(e) {
  e.preventDefault();
  if (!state.session.isLoggedIn) {
    openLoginModal();
    return;
  }

  const assetSelect = document.getElementById('alert-asset-select');
  const targetPriceInput = document.getElementById('alert-target-price-input');
  const asset = assetSelect ? assetSelect.value : 'BTC';
  const targetPrice = parseFloat(targetPriceInput ? targetPriceInput.value : 0);

  if (!targetPrice || targetPrice <= 0) {
    showToast('error', 'Target harga tidak valid.');
    return;
  }

  try {
    const res = await fetch('/api/trading/alert', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({
        player_name: state.session.playerName,
        asset: asset,
        target_price: targetPrice
      })
    });

    const json = await res.json();
    if (json.success) {
      showToast('success', json.message);
      if (targetPriceInput) targetPriceInput.value = '';
      loadPriceAlerts();
    } else {
      showToast('error', json.message);
    }
  } catch (err) {
    showToast('error', 'Gagal memasang price alert.');
  }
}

async function cancelPriceAlert(alertId) {
  try {
    const res = await fetch('/api/trading/cancel-alert', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({
        player_name: state.session.playerName,
        alert_id: alertId
      })
    });
    const json = await res.json();
    if (json.success) {
      showToast('success', json.message);
      loadPriceAlerts();
    }
  } catch (e) {}
}

// =======================================================
//   P2P ASSET TRANSFER TAB
// =======================================================
function updateTransferBalanceHint() {
  const assetSelect = document.getElementById('transfer-asset-select');
  const hint = document.getElementById('transfer-owned-hint');
  const assetKey = assetSelect ? assetSelect.value.toLowerCase() : 'btc';
  const owned = state.portfolio[assetKey] ? state.portfolio[assetKey].amount : 0;
  if (hint) hint.textContent = 'Saldo: ' + owned.toFixed(2) + ' ' + assetKey.toUpperCase();
  calculateTransferFee();
}

function calculateTransferFee() {
  const amountInput = document.getElementById('transfer-amount-input');
  const feeDisplay = document.getElementById('transfer-fee-display');
  const netDisplay = document.getElementById('transfer-net-display');
  const assetSelect = document.getElementById('transfer-asset-select');
  const sym = assetSelect ? assetSelect.value : 'BTC';

  const amount = parseFloat(amountInput ? amountInput.value : 0) || 0;
  const fee = amount * 0.02;
  const net = Math.max(0, amount - fee);

  if (feeDisplay) feeDisplay.textContent = fee.toFixed(4) + ' ' + sym;
  if (netDisplay) netDisplay.textContent = net.toFixed(4) + ' ' + sym;
}

async function handleTransferSubmit(e) {
  e.preventDefault();
  if (!state.session.isLoggedIn) {
    openLoginModal();
    return;
  }

  const receiverInput = document.getElementById('transfer-receiver-input');
  const assetSelect = document.getElementById('transfer-asset-select');
  const amountInput = document.getElementById('transfer-amount-input');
  const pinInput = document.getElementById('transfer-pin-input');

  const receiver = receiverInput ? receiverInput.value.trim() : '';
  const asset = assetSelect ? assetSelect.value : 'BTC';
  const amount = parseFloat(amountInput ? amountInput.value : 0);
  const pin = pinInput ? pinInput.value.trim() : '';

  if (!receiver) {
    showToast('error', 'Masukkan username penerima.');
    return;
  }
  if (!amount || amount <= 0) {
    showToast('error', 'Masukkan jumlah unit transfer valid.');
    return;
  }
  if (!pin || pin.length !== 6) {
    showToast('error', 'PIN harus 6 digit.');
    return;
  }

  try {
    const res = await fetch('/api/trading/transfer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({
        sender_name: state.session.playerName,
        receiver_name: receiver,
        pin: pin,
        asset: asset,
        amount: amount
      })
    });

    const json = await res.json();
    if (json.success) {
      showToast('success', json.message);
      if (amountInput) amountInput.value = '';
      if (pinInput) pinInput.value = '';
      loginPlayer(state.session.playerName);
      switchBottomTab('portfolio');
    } else {
      showToast('error', json.message || 'Transfer gagal.');
    }
  } catch (err) {
    showToast('error', 'Gagal memproses transfer.');
  }
}

// =======================================================
//   PORTFOLIO & ORDERBOOK & LOGS TABLE
// =======================================================
function renderPortfolioTable() {
  const tbody = document.getElementById('portfolio-table-body');
  if (!tbody) return;

  tbody.innerHTML = Object.entries(state.assets).map(([key, a]) => {
    const holding = state.portfolio[key] || { amount: 0, avgBuyPrice: 0 };
    const amount = holding.amount;
    const avgPrice = holding.avgBuyPrice;
    const currentVal = amount * a.price;
    const costBasis = amount * avgPrice;
    const pnlVal = currentVal - costBasis;
    const pnlPct = costBasis > 0 ? (pnlVal / costBasis) * 100 : 0;
    const isProfit = pnlVal >= 0;
    const pnlClass = isProfit ? 'text-emerald-400' : 'text-red-400';

    return `
      <tr class="border-b border-neutral-900/60 hover:bg-neutral-900/30">
        <td class="py-3 px-3 font-bold text-white flex items-center gap-2">
          <span class="h-6 w-6 rounded bg-purple-500/10 text-primary flex items-center justify-center text-[10px] font-mono font-bold">${a.symbol}</span>
          <span>${a.name}</span>
        </td>
        <td class="py-3 px-3 font-bold text-white">${amount.toFixed(2)} ${a.symbol}</td>
        <td class="py-3 px-3 text-neutral-400">$${avgPrice.toFixed(2)}</td>
        <td class="py-3 px-3 font-bold text-white">${formatCurrency(currentVal)}</td>
        <td class="py-3 px-3 font-bold ${pnlClass}">
          ${amount > 0 ? (isProfit ? '+' : '') + formatCurrency(pnlVal) + ' (' + (isProfit ? '+' : '') + pnlPct.toFixed(2) + '%)' : '-'}
        </td>
      </tr>
    `;
  }).join('');
}

function renderOrderbook() {
  const bidsContainer = document.getElementById('orderbook-bids');
  const asksContainer = document.getElementById('orderbook-asks');
  const asset = state.assets[state.activeAsset];
  if (!bidsContainer || !asksContainer || !asset) return;

  const currentPrice = asset.price;
  let bidsHtml = '';
  let asksHtml = '';

  for (let i = 1; i <= 5; i++) {
    const bPrice = (currentPrice * (1 - (i * 0.003))).toFixed(2);
    const bAmt = (Math.random() * 5 + 1).toFixed(2);
    bidsHtml += `
      <div class="flex justify-between py-1 px-2 rounded bg-emerald-950/20 border border-emerald-900/20 text-emerald-400">
        <span>$${bPrice}</span>
        <span class="text-neutral-400">${bAmt}</span>
      </div>
    `;

    const aPrice = (currentPrice * (1 + (i * 0.003))).toFixed(2);
    const aAmt = (Math.random() * 5 + 1).toFixed(2);
    asksHtml += `
      <div class="flex justify-between py-1 px-2 rounded bg-red-950/20 border border-red-900/20 text-red-400">
        <span>$${aPrice}</span>
        <span class="text-neutral-400">${aAmt}</span>
      </div>
    `;
  }

  bidsContainer.innerHTML = bidsHtml;
  asksContainer.innerHTML = asksHtml;
}

function renderTradeHistory() {
  const container = document.getElementById('trade-history-list');
  if (!container) return;

  if (state.tradeLogs.length === 0) {
    container.innerHTML = '<div class="text-center py-6 text-neutral-500 font-sans">Belum ada transaksi pada sesi ini.</div>';
    return;
  }

  container.innerHTML = state.tradeLogs.map(t => {
    const isBuy = t.trade_type.includes('BUY') || t.trade_type.includes('TRANSFER_IN');
    const color = isBuy ? 'text-emerald-400' : 'text-red-400';
    return `
      <div class="p-2 rounded-xl bg-neutral-950/80 border border-neutral-900 flex items-center justify-between text-xs font-mono">
        <div class="flex items-center gap-2">
          <span class="font-bold ${color}">${t.trade_type}</span>
          <span class="text-white">${parseFloat(t.amount).toFixed(2)} ${t.asset}</span>
        </div>
        <div class="text-right">
          <span class="text-white font-bold">$${parseFloat(t.total).toFixed(2)}</span>
        </div>
      </div>
    `;
  }).join('');
}

// =======================================================
//   LEADERBOARD TOP 10
// =======================================================
async function loadLeaderboard() {
  try {
    const res = await fetch('/api/trading/leaderboard');
    const json = await res.json();
    if (json.success && json.data) {
      const d = json.data;
      const cap = document.getElementById('lb-market-cap');
      const inv = document.getElementById('lb-total-investors');
      if (cap) cap.textContent = formatCurrency(d.total_market_cap);
      if (inv) inv.textContent = d.total_investors + ' Pemain';

      renderLeaderboard(d.top_investors || []);
    }
  } catch (e) {}
}

function renderLeaderboard(list) {
  const podium = document.getElementById('leaderboard-podium');
  const tbody = document.getElementById('leaderboard-table-body');

  if (podium && list.length >= 1) {
    podium.innerHTML = list.slice(0, 3).map((p, idx) => {
      const border = idx === 0 ? 'border-amber-500/50 bg-amber-950/20' : idx === 1 ? 'border-neutral-400/50 bg-neutral-900/30' : 'border-amber-700/50 bg-amber-950/10';
      const medal = idx === 0 ? '🥇 #1' : idx === 1 ? '🥈 #2' : '🥉 #3';
      return `
        <div class="p-4 rounded-2xl glass-panel ${border} text-center space-y-2">
          <div class="font-bold text-sm text-amber-400">${medal}</div>
          <img src="${p.avatar_url}" class="h-12 w-12 rounded-xl mx-auto border border-neutral-700 shadow-md" alt="${p.player_name}" />
          <div class="font-bold text-xs text-white">${p.player_name}</div>
          <div class="text-[10px] text-purple-400 font-mono font-bold">Aset: ${formatCurrency(p.assets_value)}</div>
        </div>
      `;
    }).join('');
  }

  if (tbody) {
    tbody.innerHTML = list.map(p => `
      <tr class="border-b border-neutral-900/60 hover:bg-neutral-900/30">
        <td class="py-2.5 px-3 font-bold text-amber-400">#${p.rank}</td>
        <td class="py-2.5 px-3 font-bold text-white flex items-center gap-2">
          <img src="${p.avatar_url}" class="h-5 w-5 rounded" alt="" />
          <span>${p.player_name}</span>
        </td>
        <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-primary border border-purple-500/20">${p.badge}</span></td>
        <td class="py-2.5 px-3 font-bold text-purple-400">${formatCurrency(p.assets_value)}</td>
        <td class="py-2.5 px-3 text-neutral-400">${formatCurrency(p.cash_balance)}</td>
        <td class="py-2.5 px-3 text-right font-bold text-white">${formatCurrency(p.total_net_worth)}</td>
      </tr>
    `).join('');
  }
}

// =======================================================
//   BOTTOM TAB SWITCHER
// =======================================================
function switchBottomTab(tabKey) {
  const allTabs = ['portfolio', 'limit-orders', 'alerts', 'transfer', 'orderbook', 'history', 'leaderboard'];

  allTabs.forEach(t => {
    const btn = document.getElementById('tab-btn-' + t);
    const content = document.getElementById('tab-content-' + t);
    if (btn) {
      btn.className = 'bottom-tab-btn px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-neutral-900/60 text-neutral-400 hover:text-white transition flex items-center gap-1.5 cursor-pointer';
    }
    if (content) {
      content.classList.add('hidden');
    }
  });

  const activeBtn = document.getElementById('tab-btn-' + tabKey);
  const activeContent = document.getElementById('tab-content-' + tabKey);

  if (activeBtn) {
    activeBtn.className = 'bottom-tab-btn active px-3 py-2 rounded-xl text-[11px] font-bold uppercase tracking-wider bg-primary text-white transition flex items-center gap-1.5 cursor-pointer';
  }
  if (activeContent) {
    activeContent.classList.remove('hidden');
  }

  if (tabKey === 'limit-orders') loadLimitOrders();
  if (tabKey === 'alerts') loadPriceAlerts();
  if (tabKey === 'transfer') updateTransferBalanceHint();
  if (tabKey === 'leaderboard') loadLeaderboard();

  if (window.lucide) lucide.createIcons();
}

// =======================================================
//   UTILITIES
// =======================================================
function formatCurrency(num) {
  return '$' + (parseFloat(num) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showToast(type, message) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const isSuccess = type === 'success';
  const toast = document.createElement('div');
  toast.className = 'p-3.5 rounded-2xl glass-panel border ' + (isSuccess ? 'border-emerald-500/40 bg-emerald-950/90 text-emerald-200' : 'border-red-500/40 bg-red-950/90 text-red-200') + ' text-xs font-mono shadow-2xl flex items-center gap-3 pointer-events-auto transition transform translate-y-2 opacity-0';
  toast.innerHTML = `
    <i data-lucide="${isSuccess ? 'check-circle' : 'alert-circle'}" class="h-5 w-5 ${isSuccess ? 'text-emerald-400' : 'text-red-400'} shrink-0"></i>
    <span class="flex-1 font-sans">${message}</span>
  `;

  container.appendChild(toast);
  if (window.lucide) lucide.createIcons();

  requestAnimationFrame(() => {
    toast.classList.remove('translate-y-2', 'opacity-0');
  });

  setTimeout(() => {
    toast.classList.add('translate-y-2', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

function triggerPwaInstall() {
  if (window.deferredPrompt) {
    window.deferredPrompt.prompt();
    window.deferredPrompt.userChoice.then(() => {
      window.deferredPrompt = null;
    });
  }
}


function updateLuckySurgeBanner(surge) {
  const banner = document.getElementById('golden-surge-banner');
  if (!banner) return;

  if (surge && surge.active && surge.remaining_seconds > 0) {
    banner.classList.remove('hidden');
    banner.classList.add('flex');

    const playerEl = document.getElementById('golden-surge-player');
    const boostEl = document.getElementById('golden-surge-boost');
    const timerEl = document.getElementById('golden-surge-timer');

    if (playerEl) playerEl.textContent = surge.player_name;
    if (boostEl) boostEl.textContent = '+' + surge.boost_percent + '%';

    if (timerEl) {
      const mins = Math.floor(surge.remaining_seconds / 60);
      const secs = surge.remaining_seconds % 60;
      timerEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
  } else {
    banner.classList.add('hidden');
    banner.classList.remove('flex');
  }
}
