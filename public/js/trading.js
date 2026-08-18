// =======================================================
//   GENZSMP WEB TRADING CONTROLLER & WS ENGINE
// =======================================================

const CONFIG = window.TRADING_CONFIG || {};

const state = {
  session: {
    active: false,
    isDemo: false,
    token: CONFIG.token || '',
    playerName: CONFIG.player || 'Investor',
    uuid: '',
    cashBalance: 0.00,
    expireSeconds: CONFIG.sessionTtlSeconds || 900
  },
  activeAsset: 'btc',
  activeTradeType: 'BUY',
  timeframe: '5M',
  cooldownActive: false,
  cooldownRemaining: 0,
  assets: {
    btc: {
      symbol: 'BTC',
      name: 'Bitcoin',
      category: 'Crypto',
      price: 1020.00,
      openPrice: 980.00,
      high: 1080.00,
      low: 950.00,
      volume: 124500,
      changePercent: 4.08,
      history: [950, 965, 960, 980, 1005, 1020]
    },
    eth: {
      symbol: 'ETH',
      name: 'Ethereum',
      category: 'Crypto',
      price: 510.00,
      openPrice: 520.00,
      high: 540.00,
      low: 480.00,
      volume: 62100,
      changePercent: -1.92,
      history: [490, 500, 515, 508, 512, 510]
    },
    gld: {
      symbol: 'GLD',
      name: 'Gold Ingot',
      category: 'Commodity',
      price: 105.00,
      openPrice: 100.00,
      high: 110.00,
      low: 98.00,
      volume: 18400,
      changePercent: 5.00,
      history: [98, 100, 101, 103, 104, 105]
    },
    dia: {
      symbol: 'DIA',
      name: 'Diamond Gem',
      category: 'Commodity',
      price: 245.00,
      openPrice: 250.00,
      high: 260.00,
      low: 240.00,
      volume: 34200,
      changePercent: -2.00,
      history: [260, 255, 252, 250, 248, 245]
    },
    emd: {
      symbol: 'EMD',
      name: 'Emerald Shard',
      category: 'Commodity',
      price: 175.00,
      openPrice: 168.00,
      high: 190.00,
      low: 160.00,
      volume: 28900,
      changePercent: 4.16,
      history: [165, 168, 170, 172, 174, 175]
    }
  },
  portfolio: {
    btc: { amount: 0, avgBuyPrice: 0 },
    eth: { amount: 0, avgBuyPrice: 0 },
    gld: { amount: 0, avgBuyPrice: 0 },
    dia: { amount: 0, avgBuyPrice: 0 },
    emd: { amount: 0, avgBuyPrice: 0 }
  },
  tradeLogs: []
};

let chartInstance = null;
let wsClient = null;
let sessionTimer = null;
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
  initTradingChart();
  renderPortfolioTable();
  renderOrderbook();
  calculateTradeCost();

  // If accessed with valid token, start WebSocket
  if (CONFIG.isValidAccess) {
    initWebSocket();
  }

  // Start periodic price pulse simulation
  setInterval(simulateMicroPriceMovements, 4000);
});

// =======================================================
//   WEBSOCKET CLIENT ENGINE (CONNECTS TO ARQOINVEST 8088)
// =======================================================
function initWebSocket() {
  const host = CONFIG.wsHost || window.location.hostname || '178.128.105.129';
  const port = CONFIG.wsPort || 8088;
  const wsUrl = `ws://${host}:${port}`;

  const statusBadge = document.getElementById('ws-status-badge');
  const statusDot = document.getElementById('ws-status-dot');
  const statusText = document.getElementById('ws-status-text');

  try {
    statusText.textContent = `Menghubungkan ke ${host}:${port}...`;
    wsClient = new WebSocket(wsUrl);

    wsClient.onopen = () => {
      console.log('[ArqoInvest WS] Connected to Java Server!');
      statusDot.className = 'h-2 w-2 rounded-full bg-emerald-400';
      statusText.textContent = 'Server Java Terhubung';
      statusBadge.classList.remove('hidden');

      // Send AUTH Handshake
      wsClient.send(JSON.stringify({
        type: 'AUTH',
        token: state.session.token
      }));
    };

    wsClient.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data);
        handleServerMessage(msg);
      } catch (err) {
        console.warn('[ArqoInvest WS] JSON parse error:', err);
      }
    };

    wsClient.onerror = (err) => {
      console.log('[ArqoInvest WS] Connection error or offline.');
      statusDot.className = 'h-2 w-2 rounded-full bg-red-400';
      statusText.textContent = 'WS Offline (Demo Fallback)';
    };

    wsClient.onclose = () => {
      console.log('[ArqoInvest WS] Disconnected.');
      statusDot.className = 'h-2 w-2 rounded-full bg-yellow-400';
      statusText.textContent = 'WS Terputus';
    };
  } catch (e) {
    console.log('[ArqoInvest WS] WebSocket init error:', e);
  }
}

function handleServerMessage(msg) {
  if (!msg || !msg.type) return;

  switch (msg.type) {
    case 'AUTH_SUCCESS':
      state.session.active = true;
      state.session.playerName = msg.playerName || state.session.playerName;
      state.session.uuid = msg.uuid || '';
      state.session.cashBalance = msg.cashBalance !== undefined ? msg.cashBalance : state.session.cashBalance;
      state.session.expireSeconds = msg.remainingSeconds || 900;

      if (msg.portfolio) {
        Object.keys(msg.portfolio).forEach(k => {
          if (state.portfolio[k]) {
            state.portfolio[k].amount = msg.portfolio[k][0] || 0;
            state.portfolio[k].avgBuyPrice = msg.portfolio[k][1] || 0;
          }
        });
      }

      startSessionCountdown();
      updateBalanceDisplays();
      renderPortfolioTable();
      showToast(`Selamat datang ${state.session.playerName}! Sesi trading aktif.`, 'success');
      break;

    case 'TRADE_SUCCESS':
      state.session.cashBalance = msg.newBalance;
      updateBalanceDisplays();
      renderPortfolioTable();
      showToast(`Transaksi ${msg.tradeType} ${msg.amount} Berhasil! Saldo Baru: $${msg.newBalance.toLocaleString()}`, 'success');
      break;

    case 'NEWS_UPDATE':
      if (msg.headline) {
        updateTickerHeadline(msg.headline, msg.asset, msg.changePercent);
        showToast(`📰 GenzNews: ${msg.headline}`, 'info');
      }
      break;

    case 'SESSION_TERMINATED':
      showSessionTerminatedModal(msg.reason || 'Sesi web trading telah dimatikan.');
      break;

    case 'ERROR':
      showToast(`⚠️ ${msg.message || 'Terjadi kesalahan transaksi.'}`, 'danger');
      break;
  }
}

// =======================================================
//   SESSION & DEMO MODE
// =======================================================
function startDemoMode() {
  state.session.active = true;
  state.session.isDemo = true;
  state.session.playerName = 'DemoTrader';
  state.session.cashBalance = 50000.00;
  state.session.expireSeconds = 1800;

  // Add initial demo portfolio
  state.portfolio.btc = { amount: 1.5, avgBuyPrice: 980 };
  state.portfolio.eth = { amount: 4.0, avgBuyPrice: 505 };

  document.getElementById('access-denied-modal').classList.add('hidden');
  document.getElementById('access-denied-modal').classList.remove('flex');

  const statusBadge = document.getElementById('ws-status-badge');
  const statusDot = document.getElementById('ws-status-dot');
  const statusText = document.getElementById('ws-status-text');

  statusDot.className = 'h-2 w-2 rounded-full bg-purple-400';
  statusText.textContent = 'Mode Demo (Preview)';
  statusBadge.classList.remove('hidden');

  startSessionCountdown();
  updateBalanceDisplays();
  renderPortfolioTable();
  calculateTradeCost();
  showToast('Mode Demo Aktif! Saldo virtual: $50,000.00', 'info');
}

function startSessionCountdown() {
  if (sessionTimer) clearInterval(sessionTimer);

  const display = document.getElementById('session-timer-display');
  sessionTimer = setInterval(() => {
    state.session.expireSeconds--;
    if (state.session.expireSeconds <= 0) {
      clearInterval(sessionTimer);
      showSessionTerminatedModal('Waktu sesi trading 15 menit telah habis.');
      return;
    }

    const mins = Math.floor(state.session.expireSeconds / 60);
    const secs = state.session.expireSeconds % 60;
    if (display) {
      display.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
  }, 1000);
}

function showSessionTerminatedModal(reason) {
  const modal = document.getElementById('session-terminated-modal');
  const reasonText = document.getElementById('terminated-reason-text');
  if (reasonText) reasonText.textContent = reason;
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

// =======================================================
//   ASSET SELECTOR & WATCHLIST
// =======================================================
function renderAssetList() {
  const container = document.getElementById('asset-list-container');
  if (!container) return;

  container.innerHTML = Object.keys(state.assets).map(key => {
    const asset = state.assets[key];
    const isSelected = state.activeAsset === key;
    const isPositive = asset.changePercent >= 0;

    return `
      <div 
        onclick="selectAsset('${key}')"
        class="glass-panel-hover rounded-xl p-3 cursor-pointer border ${isSelected ? 'border-primary bg-neutral-900/90 shadow-lg shadow-purple-500/10' : 'border-neutral-900 bg-neutral-950/60'} flex items-center justify-between transition-all"
      >
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center font-bold text-xs text-primary shrink-0">
            ${asset.symbol}
          </div>
          <div>
            <div class="font-bold text-xs text-white">${asset.name}</div>
            <div class="text-[10px] text-neutral-500 font-mono">${asset.category}</div>
          </div>
        </div>
        <div class="text-right font-mono">
          <div class="text-xs font-black text-white">$${asset.price.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
          <div class="text-[10px] font-bold ${isPositive ? 'text-emerald-400' : 'text-red-400'}">
            ${isPositive ? '+' : ''}${asset.changePercent.toFixed(2)}%
          </div>
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
  updateChartData();
  renderOrderbook();
  calculateTradeCost();
}

function updateActiveAssetDisplay() {
  const asset = state.assets[state.activeAsset];
  if (!asset) return;

  const isPositive = asset.changePercent >= 0;

  document.getElementById('active-asset-icon').textContent = asset.symbol;
  document.getElementById('active-asset-title').textContent = `${asset.name} (${asset.symbol})`;
  document.getElementById('active-asset-category').textContent = asset.category;
  document.getElementById('active-asset-price').textContent = `$${asset.price.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  
  const changeEl = document.getElementById('active-asset-change');
  changeEl.className = `text-xs font-mono font-bold flex items-center justify-end gap-1 ${isPositive ? 'text-emerald-400' : 'text-red-400'}`;
  changeEl.innerHTML = `
    <i data-lucide="${isPositive ? 'arrow-up-right' : 'arrow-down-right'}" class="h-3.5 w-3.5"></i>
    ${isPositive ? '+' : ''}${asset.changePercent.toFixed(2)}% (24h)
  `;

  document.getElementById('stat-high').textContent = `$${asset.high.toLocaleString()}`;
  document.getElementById('stat-low').textContent = `$${asset.low.toLocaleString()}`;
  document.getElementById('stat-vol').textContent = `$${(asset.volume / 1000).toFixed(1)}K`;
  document.getElementById('trade-unit-symbol').textContent = asset.symbol;
  document.getElementById('summary-unit-price').textContent = `$${asset.price.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

  if (window.lucide) {
    lucide.createIcons();
  }
}

// =======================================================
//   CHART.JS LIVE RENDERING
// =======================================================
function initTradingChart() {
  const canvas = document.getElementById('tradingChart');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const asset = state.assets[state.activeAsset];

  // Create purple gradient
  const gradient = ctx.createLinearGradient(0, 0, 0, 360);
  gradient.addColorStop(0, 'rgba(168, 85, 247, 0.4)');
  gradient.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: generateChartLabels(asset.history.length),
      datasets: [{
        label: asset.name,
        data: [...asset.history],
        borderColor: '#a855f7',
        borderWidth: 2.5,
        backgroundColor: gradient,
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointBackgroundColor: '#a855f7',
        pointBorderColor: '#ffffff',
        pointHoverRadius: 6,
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
          backgroundColor: 'rgba(15, 15, 15, 0.95)',
          titleColor: '#a855f7',
          bodyColor: '#ffffff',
          borderColor: 'rgba(168, 85, 247, 0.3)',
          borderWidth: 1,
          padding: 10,
          displayColors: false,
          callbacks: {
            label: (ctx) => `Harga: $${ctx.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 })}`
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255, 255, 255, 0.04)' },
          ticks: { color: '#737373', font: { family: 'JetBrains Mono', size: 10 } }
        },
        y: {
          position: 'right',
          grid: { color: 'rgba(255, 255, 255, 0.04)' },
          ticks: {
            color: '#737373',
            font: { family: 'JetBrains Mono', size: 10 },
            callback: (val) => `$${val}`
          }
        }
      }
    }
  });
}

function generateChartLabels(count) {
  const labels = [];
  const now = new Date();
  for (let i = count - 1; i >= 0; i--) {
    const t = new Date(now.getTime() - i * 5 * 60000);
    labels.push(`${String(t.getHours()).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')}`);
  }
  return labels;
}

function updateChartData() {
  if (!chartInstance) return;
  const asset = state.assets[state.activeAsset];
  chartInstance.data.labels = generateChartLabels(asset.history.length);
  chartInstance.data.datasets[0].label = asset.name;
  chartInstance.data.datasets[0].data = [...asset.history];
  chartInstance.update();
}

function setTimeframe(tf) {
  state.timeframe = tf;
  document.querySelectorAll('.tf-btn').forEach(b => {
    b.classList.remove('bg-primary', 'text-white', 'font-bold', 'active');
    b.classList.add('text-neutral-400');
  });
  event.target.classList.add('bg-primary', 'text-white', 'font-bold', 'active');
  event.target.classList.remove('text-neutral-400');

  // Regenerate history points based on timeframe
  const asset = state.assets[state.activeAsset];
  const count = tf === '1M' ? 12 : tf === '5M' ? 10 : tf === '15M' ? 8 : tf === '1H' ? 6 : 5;
  const base = asset.price;
  asset.history = Array.from({ length: count }, (_, i) => {
    const delta = (Math.random() - 0.5) * (base * 0.04);
    return Math.round((base + delta) * 100) / 100;
  });
  asset.history[asset.history.length - 1] = asset.price;
  updateChartData();
}

// =======================================================
//   ORDER EXECUTION & CALCULATIONS
// =======================================================
function setTradeType(type) {
  state.activeTradeType = type;
  const buyBtn = document.getElementById('trade-tab-buy');
  const sellBtn = document.getElementById('trade-tab-sell');
  const submitBtn = document.getElementById('trade-submit-btn');
  const btnText = document.getElementById('trade-btn-text');
  const balLabel = document.getElementById('balance-label');

  if (type === 'BUY') {
    buyBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition bg-emerald-600 text-white shadow-lg shadow-emerald-600/20';
    sellBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition text-neutral-400 hover:text-white';
    submitBtn.className = 'w-full py-4 rounded-xl font-bold uppercase text-xs tracking-wider transition-all cursor-pointer bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-xl shadow-emerald-500/20 hover:brightness-110 active:scale-95 flex items-center justify-center gap-2';
    btnText.textContent = `Eksekusi Order Beli (${state.assets[state.activeAsset].symbol})`;
    balLabel.textContent = 'Saldo Kas Vault:';
  } else {
    sellBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition bg-red-600 text-white shadow-lg shadow-red-600/20';
    buyBtn.className = 'py-2.5 rounded-xl font-bold uppercase text-xs tracking-wider transition text-neutral-400 hover:text-white';
    submitBtn.className = 'w-full py-4 rounded-xl font-bold uppercase text-xs tracking-wider transition-all cursor-pointer bg-gradient-to-r from-red-500 to-rose-600 text-white shadow-xl shadow-red-500/20 hover:brightness-110 active:scale-95 flex items-center justify-center gap-2';
    btnText.textContent = `Eksekusi Order Jual (${state.assets[state.activeAsset].symbol})`;
    balLabel.textContent = 'Aset Dimiliki:';
  }

  updateBalanceDisplays();
  calculateTradeCost();
}

function updateBalanceDisplays() {
  document.getElementById('player-cash-display').textContent = `$${state.session.cashBalance.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  
  const formBal = document.getElementById('form-available-balance');
  if (state.activeTradeType === 'BUY') {
    formBal.textContent = `$${state.session.cashBalance.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  } else {
    const owned = state.portfolio[state.activeAsset]?.amount || 0;
    formBal.textContent = `${owned.toFixed(2)} ${state.assets[state.activeAsset].symbol}`;
  }
}

function setPercentageAmount(pct) {
  const asset = state.assets[state.activeAsset];
  const input = document.getElementById('trade-amount-input');

  if (state.activeTradeType === 'BUY') {
    const totalCash = state.session.cashBalance;
    const maxBuyAmount = totalCash / (asset.price * 1.02); // accounting for 2% tax
    const amount = Math.floor(maxBuyAmount * pct * 100) / 100;
    input.value = Math.min(amount, 1000) > 0 ? Math.min(amount, 1000) : '';
  } else {
    const owned = state.portfolio[state.activeAsset]?.amount || 0;
    const amount = Math.floor(owned * pct * 100) / 100;
    input.value = amount > 0 ? amount : '';
  }

  calculateTradeCost();
}

function calculateTradeCost() {
  const input = document.getElementById('trade-amount-input');
  const amount = parseFloat(input.value) || 0;
  const asset = state.assets[state.activeAsset];

  const subtotal = amount * asset.price;
  const tax = subtotal * 0.02;
  const total = state.activeTradeType === 'BUY' ? subtotal + tax : subtotal - tax;

  document.getElementById('summary-subtotal').textContent = `$${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  document.getElementById('summary-tax').textContent = `$${tax.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  document.getElementById('summary-total').textContent = `$${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}

function handleTradeSubmit(e) {
  e.preventDefault();

  if (state.cooldownActive) {
    showToast(`Tunggu Anti-Whale cooldown selesai (${state.cooldownRemaining}s)!`, 'danger');
    return;
  }

  const input = document.getElementById('trade-amount-input');
  const amount = parseFloat(input.value);
  const assetKey = state.activeAsset;
  const asset = state.assets[assetKey];

  if (!amount || amount <= 0) {
    showToast('Masukkan jumlah unit transaksi yang valid!', 'danger');
    return;
  }

  if (amount > 1000) {
    showToast('Maksimal order 1,000 unit per transaksi!', 'danger');
    return;
  }

  const subtotal = amount * asset.price;
  const tax = subtotal * 0.02;

  if (state.activeTradeType === 'BUY') {
    const totalCost = subtotal + tax;
    if (state.session.cashBalance < totalCost) {
      showToast('Saldo kas Vault tidak mencukupi untuk order ini!', 'danger');
      return;
    }

    // Execute via WS or Demo Mode
    if (state.session.isDemo) {
      state.session.cashBalance -= totalCost;
      const prevAmount = state.portfolio[assetKey].amount;
      const prevAvg = state.portfolio[assetKey].avgBuyPrice;
      const newAmount = prevAmount + amount;
      const newAvg = ((prevAmount * prevAvg) + (amount * asset.price)) / newAmount;

      state.portfolio[assetKey].amount = newAmount;
      state.portfolio[assetKey].avgBuyPrice = newAvg;

      recordTradeLog('BUY', assetKey, amount, asset.price, totalCost);
      updateBalanceDisplays();
      renderPortfolioTable();
      startAntiWhaleCooldown();
      input.value = '';
      calculateTradeCost();
      showToast(`✅ [DEMO] Berhasil membeli ${amount} ${asset.symbol}!`, 'success');
    } else if (wsClient && wsClient.readyState === WebSocket.OPEN) {
      wsClient.send(JSON.stringify({
        type: 'TRADE_EXECUTE',
        tradeType: 'BUY',
        asset: asset.symbol,
        amount: amount,
        price: asset.price,
        totalCost: totalCost
      }));
      startAntiWhaleCooldown();
      input.value = '';
      calculateTradeCost();
    } else {
      showToast('Gagal terhubung ke WebSocket Server.', 'danger');
    }

  } else {
    // SELL
    const owned = state.portfolio[assetKey]?.amount || 0;
    if (owned < amount) {
      showToast(`Kamu hanya memiliki ${owned.toFixed(2)} ${asset.symbol}!`, 'danger');
      return;
    }

    const netPayout = subtotal - tax;

    if (state.session.isDemo) {
      state.session.cashBalance += netPayout;
      state.portfolio[assetKey].amount -= amount;

      recordTradeLog('SELL', assetKey, amount, asset.price, netPayout);
      updateBalanceDisplays();
      renderPortfolioTable();
      startAntiWhaleCooldown();
      input.value = '';
      calculateTradeCost();
      showToast(`🔴 [DEMO] Berhasil menjual ${amount} ${asset.symbol}!`, 'success');
    } else if (wsClient && wsClient.readyState === WebSocket.OPEN) {
      wsClient.send(JSON.stringify({
        type: 'TRADE_EXECUTE',
        tradeType: 'SELL',
        asset: asset.symbol,
        amount: amount,
        price: asset.price,
        netPayout: netPayout
      }));
      startAntiWhaleCooldown();
      input.value = '';
      calculateTradeCost();
    } else {
      showToast('Gagal terhubung ke WebSocket Server.', 'danger');
    }
  }
}

function startAntiWhaleCooldown() {
  state.cooldownActive = true;
  state.cooldownRemaining = 5;

  const indicator = document.getElementById('cooldown-indicator');
  const timerText = document.getElementById('cooldown-seconds');
  const submitBtn = document.getElementById('trade-submit-btn');

  if (indicator) indicator.classList.remove('hidden');
  if (submitBtn) submitBtn.disabled = true;

  if (cooldownTimer) clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    state.cooldownRemaining--;
    if (timerText) timerText.textContent = `${state.cooldownRemaining}s`;

    if (state.cooldownRemaining <= 0) {
      clearInterval(cooldownTimer);
      state.cooldownActive = false;
      if (indicator) indicator.classList.add('hidden');
      if (submitBtn) submitBtn.disabled = false;
    }
  }, 1000);
}

// =======================================================
//   ORDERBOOK & PORTFOLIO TABLES
// =======================================================
function renderOrderbook() {
  const asset = state.assets[state.activeAsset];
  const bidsContainer = document.getElementById('orderbook-bids');
  const asksContainer = document.getElementById('orderbook-asks');

  if (!bidsContainer || !asksContainer) return;

  const p = asset.price;
  const bids = [
    { price: p * 0.998, amount: 4.2 },
    { price: p * 0.995, amount: 8.5 },
    { price: p * 0.991, amount: 12.0 },
    { price: p * 0.985, amount: 25.4 }
  ];

  const asks = [
    { price: p * 1.002, amount: 3.8 },
    { price: p * 1.006, amount: 7.1 },
    { price: p * 1.011, amount: 14.5 },
    { price: p * 1.018, amount: 22.0 }
  ];

  bidsContainer.innerHTML = bids.map(b => `
    <div class="flex justify-between py-1 px-2 rounded bg-emerald-500/5 hover:bg-emerald-500/10">
      <span class="text-emerald-400 font-bold">$${b.price.toFixed(2)}</span>
      <span class="text-neutral-400">${b.amount.toFixed(2)}</span>
    </div>
  `).join('');

  asksContainer.innerHTML = asks.map(a => `
    <div class="flex justify-between py-1 px-2 rounded bg-red-500/5 hover:bg-red-500/10">
      <span class="text-red-400 font-bold">$${a.price.toFixed(2)}</span>
      <span class="text-neutral-400">${a.amount.toFixed(2)}</span>
    </div>
  `).join('');
}

function renderPortfolioTable() {
  const tbody = document.getElementById('portfolio-table-body');
  if (!tbody) return;

  const rows = Object.keys(state.portfolio).map(key => {
    const item = state.portfolio[key];
    const asset = state.assets[key];
    const currentVal = item.amount * asset.price;
    const investedVal = item.amount * item.avgBuyPrice;
    const pnl = item.amount > 0 ? currentVal - investedVal : 0;
    const pnlPercent = investedVal > 0 ? (pnl / investedVal) * 100 : 0;
    const isProfit = pnl >= 0;

    return `
      <tr class="hover:bg-neutral-900/40 transition">
        <td class="py-2.5 px-3 font-bold text-white flex items-center gap-2">
          <span class="h-2 w-2 rounded-full bg-primary"></span>
          ${asset.name} (${asset.symbol})
        </td>
        <td class="py-2.5 px-3 font-mono">${item.amount.toFixed(2)}</td>
        <td class="py-2.5 px-3 font-mono">$${item.avgBuyPrice.toFixed(2)}</td>
        <td class="py-2.5 px-3 font-mono font-bold text-white">$${currentVal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
        <td class="py-2.5 px-3 font-mono font-bold ${isProfit ? 'text-emerald-400' : 'text-red-400'}">
          ${isProfit ? '+' : ''}$${pnl.toFixed(2)} (${isProfit ? '+' : ''}${pnlPercent.toFixed(2)}%)
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = rows.join('');
}

function recordTradeLog(type, assetKey, amount, price, total) {
  const now = new Date();
  const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
  
  state.tradeLogs.unshift({
    time: timeStr,
    type,
    asset: state.assets[assetKey].symbol,
    amount,
    price,
    total
  });

  renderTradeHistory();
}

function renderTradeHistory() {
  const container = document.getElementById('trade-history-list');
  if (!container) return;

  if (state.tradeLogs.length === 0) {
    container.innerHTML = `<div class="text-center py-6 text-neutral-500 font-sans">Belum ada transaksi pada sesi ini.</div>`;
    return;
  }

  container.innerHTML = state.tradeLogs.map(log => `
    <div class="flex items-center justify-between p-2.5 rounded-xl bg-neutral-900/60 border border-neutral-900 text-xs font-mono">
      <div class="flex items-center gap-3">
        <span class="text-neutral-500 text-[10px]">${log.time}</span>
        <span class="px-2 py-0.5 rounded text-[10px] font-bold ${log.type === 'BUY' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}">
          ${log.type}
        </span>
        <span class="font-bold text-white">${log.amount} ${log.asset}</span>
      </div>
      <div class="text-right">
        <span class="text-white font-bold">$${log.total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
      </div>
    </div>
  `).join('');
}

function switchBottomTab(tab) {
  document.querySelectorAll('.bottom-tab-btn').forEach(b => {
    b.classList.remove('bg-primary', 'text-white');
    b.classList.add('bg-neutral-900/60', 'text-neutral-400');
  });

  const activeBtn = document.getElementById(`tab-btn-${tab}`);
  if (activeBtn) {
    activeBtn.classList.add('bg-primary', 'text-white');
    activeBtn.classList.remove('bg-neutral-900/60', 'text-neutral-400');
  }

  document.getElementById('tab-content-portfolio').classList.add('hidden');
  document.getElementById('tab-content-orderbook').classList.add('hidden');
  document.getElementById('tab-content-history').classList.add('hidden');

  const content = document.getElementById(`tab-content-${tab}`);
  if (content) content.classList.remove('hidden');
}

// =======================================================
//   SIMULATED PRICE PULSES (REALTIME VIBE)
// =======================================================
function simulateMicroPriceMovements() {
  Object.keys(state.assets).forEach(k => {
    const asset = state.assets[k];
    const fluctuation = (Math.random() - 0.49) * (asset.price * 0.005);
    const newPrice = Math.max(10, Math.round((asset.price + fluctuation) * 100) / 100);
    asset.price = newPrice;
    asset.changePercent = ((newPrice - asset.openPrice) / asset.openPrice) * 100;
  });

  const active = state.assets[state.activeAsset];
  active.history.push(active.price);
  if (active.history.length > 15) active.history.shift();

  renderAssetList();
  updateActiveAssetDisplay();
  updateChartData();
  renderOrderbook();
}

function updateTickerHeadline(headline, asset, change) {
  const ticker = document.getElementById('news-ticker');
  if (!ticker) return;
  const item = document.createElement('span');
  item.className = 'mx-6 text-primary font-bold';
  item.textContent = `🔥 BREAKING: ${headline} (${asset} ${change > 0 ? '+' : ''}${change}%)`;
  ticker.prepend(item);
}

// =======================================================
//   TOAST NOTIFICATION ENGINE
// =======================================================
function showToast(message, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  const colorClasses = type === 'success' 
    ? 'bg-emerald-950/90 border-emerald-500/40 text-emerald-200' 
    : type === 'danger' 
    ? 'bg-red-950/90 border-red-500/40 text-red-200' 
    : 'bg-purple-950/90 border-purple-500/40 text-purple-200';

  toast.className = `p-4 rounded-2xl border ${colorClasses} shadow-2xl backdrop-blur-md text-xs font-sans font-medium flex items-center gap-3 transition-all duration-300 transform translate-y-4 opacity-0 pointer-events-auto max-w-sm`;
  toast.innerHTML = `
    <span class="h-2 w-2 rounded-full ${type === 'success' ? 'bg-emerald-400' : type === 'danger' ? 'bg-red-400' : 'bg-purple-400'} shrink-0"></span>
    <span class="flex-1">${message}</span>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.remove('translate-y-4', 'opacity-0');
  }, 10);

  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-x-4');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}
