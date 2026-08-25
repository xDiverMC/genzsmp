// =======================================================
//   GENZSMP WEB TRADING CONTROLLER & PIN SECURITY ENGINE
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
  activeTradeType: 'BUY',
  timeframe: '5M',
  cooldownActive: false,
  cooldownRemaining: 0,
  pendingTrade: null, // holds order details waiting for PIN confirmation
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
      tax_percent: 8.0,
      changePercent: 4.08,
      history: [960, 972, 965, 980, 975, 990, 1010, 1005, 995, 1015, 1008, 1025, 1018, 1030, 1015, 1020]
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
      tax_percent: 8.0,
      changePercent: -1.92,
      history: [530, 525, 528, 520, 515, 522, 518, 510, 505, 512, 508, 515, 510, 506, 514, 510]
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
      tax_percent: 5.0,
      changePercent: 5.00,
      history: [98, 99, 101, 100, 102, 101, 103, 102, 104, 103, 105, 104, 106, 105, 104, 105]
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
      tax_percent: 5.0,
      changePercent: -2.00,
      history: [255, 258, 252, 250, 248, 253, 249, 246, 250, 247, 244, 248, 245, 242, 246, 245]
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
      tax_percent: 5.0,
      changePercent: 4.16,
      history: [160, 162, 165, 163, 167, 165, 169, 168, 172, 170, 174, 172, 176, 173, 177, 175]
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

  // Bind login trigger button click
  const loginTrigger = document.getElementById('login-trigger-btn');
  if (loginTrigger) {
    loginTrigger.addEventListener('click', (e) => {
      e.preventDefault();
      openLoginModal();
    });
  }

  // Check login: URL query param or saved localStorage
  const urlParams = new URLSearchParams(window.location.search);
  const playerParam = urlParams.get('player');
  const savedPlayer = localStorage.getItem('genzsmp_trading_player');

  if (playerParam) {
    loginPlayer(playerParam);
  } else if (savedPlayer) {
    loginPlayer(savedPlayer);
  }

  // Start periodic price pulse simulation
  setInterval(simulateMicroPriceMovements, 4000);
});

// =======================================================
//   AUTHENTICATION & USERNAME LOGIN
// =======================================================
function openLoginModal() {
  const modal = document.getElementById('login-modal');
  const input = document.getElementById('login-username-input');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (input) {
      input.value = state.session.playerName || '';
      setTimeout(() => input.focus(), 100);
    }
  }
}
window.openLoginModal = openLoginModal;

function closeLoginModal() {
  const modal = document.getElementById('login-modal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}
window.closeLoginModal = closeLoginModal;

async function handleLoginSubmit(e) {
  e.preventDefault();
  const input = document.getElementById('login-username-input');
  const errorBox = document.getElementById('login-error-msg');
  const errorText = document.getElementById('login-error-text');
  const username = input.value.trim();

  if (!username) return;

  if (errorBox) errorBox.classList.add('hidden');

  const btn = document.getElementById('login-submit-btn');
  btn.disabled = true;
  btn.textContent = 'Memverifikasi Gamertag...';

  try {
    const success = await loginPlayer(username, true);
    if (success) {
      closeLoginModal();
    }
  } catch (err) {
    if (errorBox && errorText) {
      errorText.textContent = 'Terjadi kesalahan saat menghubungkan ke server.';
      errorBox.classList.remove('hidden');
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Masuk & Buka Portofolio';
  }
}

async function loginPlayer(playerName, fromModal = false) {
  const errorBox = document.getElementById('login-error-msg');
  const errorText = document.getElementById('login-error-text');
  const modal = document.getElementById('login-modal');

  try {
    const res = await fetch('/api/trading/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken || ''
      },
      body: JSON.stringify({ player_name: playerName })
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

      // Save to localStorage for seamless re-visits
      localStorage.setItem('genzsmp_trading_player', u.player_name);

      // Load Portfolios
      if (json.data.portfolio) {
        Object.keys(json.data.portfolio).forEach(k => {
          if (state.portfolio[k]) {
            state.portfolio[k].amount = json.data.portfolio[k][0] || 0;
            state.portfolio[k].avgBuyPrice = json.data.portfolio[k][1] || 0;
          }
        });
      }

      // Load Trade Logs
      if (json.data.trades && Array.isArray(json.data.trades)) {
        state.tradeLogs = json.data.trades.map(t => {
          const d = new Date(t.created_at);
          return {
            time: `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`,
            type: t.trade_type,
            asset: t.asset,
            amount: t.amount,
            price: t.price,
            total: t.total
          };
        });
      }

      updateUserProfileUI();
      renderPortfolioTable();
      renderTradeHistory();
      calculateTradeCost();

      if (fromModal) {
        showToast(`Selamat datang ${u.player_name}! Portofolio aktif.`, 'success');
      }
      return true;
    } else {
      // Login failed / Player not registered
      if (errorBox && errorText) {
        errorText.textContent = json.message || `Akun '${playerName}' belum terdaftar di sistem. Silakan atur PIN in-game via /invest setpin <6-digit> terlebih dahulu.`;
        errorBox.classList.remove('hidden');
      }

      if (modal) {
        modal.classList.add('animate-shake');
        setTimeout(() => modal.classList.remove('animate-shake'), 400);
      }

      if (!fromModal) {
        localStorage.removeItem('genzsmp_trading_player');
      } else {
        showToast(json.message || 'Akun tidak terdaftar!', 'danger');
      }
      return false;
    }
  } catch (err) {
    console.error('Error logging in:', err);
    if (errorBox && errorText) {
      errorText.textContent = 'Terjadi kesalahan koneksi ke backend.';
      errorBox.classList.remove('hidden');
    }
    return false;
  }
}

function updateUserProfileUI() {
  const profileBar = document.getElementById('player-profile-bar');
  const loginBtn = document.getElementById('login-trigger-btn');
  const nameDisplay = document.getElementById('player-name-display');
  const bedrockBadge = document.getElementById('player-bedrock-badge');
  const cashDisplay = document.getElementById('player-cash-display');
  const pinBadge = document.getElementById('pin-status-badge');

  if (state.session.isLoggedIn) {
    if (profileBar) {
      profileBar.classList.remove('hidden');
      profileBar.classList.add('flex');
    }
    if (loginBtn) loginBtn.classList.add('hidden');

    if (nameDisplay) nameDisplay.textContent = state.session.playerName;
    
    if (bedrockBadge) {
      if (state.session.isBedrock || state.session.playerName.startsWith('.')) {
        bedrockBadge.classList.remove('hidden');
      } else {
        bedrockBadge.classList.add('hidden');
      }
    }

    if (cashDisplay) {
      cashDisplay.textContent = `$${state.session.cashBalance.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    }

    if (pinBadge) {
      if (state.session.hasPin) {
        pinBadge.className = 'text-[9px] font-mono text-emerald-400 font-bold';
        pinBadge.textContent = '● PIN: Aktif';
      } else {
        pinBadge.className = 'text-[9px] font-mono text-amber-400 font-bold';
        pinBadge.textContent = '● PIN: Belum Diatur';
      }
    }
  } else {
    if (profileBar) {
      profileBar.classList.add('hidden');
      profileBar.classList.remove('flex');
    }
    if (loginBtn) loginBtn.classList.remove('hidden');
  }

  updateBalanceDisplays();
  if (window.lucide) {
    lucide.createIcons();
  }
}

function getAssetTaxRate(assetKey) {
  const key = (assetKey || state.activeAsset || '').toLowerCase();
  const asset = state.assets[key];
  if (asset && asset.tax_percent) return asset.tax_percent / 100;
  return (key === 'btc' || key === 'eth') ? 0.08 : 0.05;
}

// =======================================================
//   6-DIGIT PIN SECURITY MODAL & TRADE EXECUTION
// =======================================================
function promptPinModal(e) {
  e.preventDefault();

  if (!state.session.isLoggedIn) {
    showToast('Silakan masuk dengan akun Minecraft terlebih dahulu!', 'danger');
    openLoginModal();
    return;
  }

  if (state.cooldownActive) {
    showToast(`Tunggu Anti-Whale cooldown (${state.cooldownRemaining}s)!`, 'danger');
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

  const taxRate = getAssetTaxRate(assetKey);
  const subtotal = amount * asset.price;
  const tax = subtotal * taxRate;
  const total = state.activeTradeType === 'BUY' ? subtotal + tax : subtotal - tax;

  if (state.activeTradeType === 'BUY' && state.session.cashBalance < total) {
    showToast(`Saldo kas Vault tidak cukup! Total dibutuhkan: $${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`, 'danger');
    return;
  }

  if (state.activeTradeType === 'SELL') {
    const owned = state.portfolio[assetKey]?.amount || 0;
    if (owned < amount) {
      showToast(`Jumlah ${asset.symbol} tidak cukup! Kamu hanya memiliki ${owned.toFixed(2)} ${asset.symbol}.`, 'danger');
      return;
    }
  }

  // Save pending order details
  state.pendingTrade = {
    tradeType: state.activeTradeType,
    assetKey: assetKey,
    assetSymbol: asset.symbol,
    amount: amount,
    price: asset.price,
    total: total
  };

  // Populate PIN Modal UI
  const pinModal = document.getElementById('pin-modal');
  const summaryAction = document.getElementById('pin-summary-action');
  const summaryTotal = document.getElementById('pin-summary-total');
  const summaryPlayer = document.getElementById('pin-summary-player');
  const pinInput = document.getElementById('pin-input');
  const pinError = document.getElementById('pin-error-msg');
  const notSetNotice = document.getElementById('pin-not-set-notice');
  const confirmBtn = document.getElementById('pin-confirm-btn');

  summaryAction.textContent = `${state.activeTradeType} ${amount} ${asset.symbol}`;
  summaryTotal.textContent = `$${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  summaryPlayer.textContent = state.session.playerName;

  pinInput.value = '';
  pinError.classList.add('hidden');
  pinError.textContent = '';

  if (state.session.hasPin) {
    notSetNotice.classList.add('hidden');
    confirmBtn.disabled = false;
    confirmBtn.textContent = state.activeTradeType === 'BUY' ? 'Konfirmasi & Beli' : 'Konfirmasi & Jual';
    confirmBtn.className = state.activeTradeType === 'BUY'
      ? 'flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 hover:brightness-110 active:scale-95 text-white font-bold text-xs uppercase tracking-wider transition cursor-pointer shadow-lg shadow-emerald-500/20'
      : 'flex-1 py-3 rounded-xl bg-gradient-to-r from-red-500 to-rose-600 hover:brightness-110 active:scale-95 text-white font-bold text-xs uppercase tracking-wider transition cursor-pointer shadow-lg shadow-red-500/20';
  } else {
    notSetNotice.classList.remove('hidden');
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Masukkan PIN 6-Digit';
  }

  pinModal.classList.remove('hidden');
  pinModal.classList.add('flex');
  setTimeout(() => pinInput.focus(), 150);
}

function closePinModal() {
  const pinModal = document.getElementById('pin-modal');
  if (pinModal) {
    pinModal.classList.add('hidden');
    pinModal.classList.remove('flex');
  }
  state.pendingTrade = null;
}

async function handlePinSubmit(e) {
  e.preventDefault();
  const pinInput = document.getElementById('pin-input');
  const pin = pinInput.value.trim();
  const pinError = document.getElementById('pin-error-msg');
  const pinCard = document.getElementById('pin-modal-card');
  const confirmBtn = document.getElementById('pin-confirm-btn');

  if (!pin || pin.length !== 6 || !/^\d{6}$/.test(pin)) {
    pinError.textContent = 'PIN harus terdiri dari tepat 6 angka numerik (0-9).';
    pinError.classList.remove('hidden');
    pinCard.classList.add('animate-shake');
    setTimeout(() => pinCard.classList.remove('animate-shake'), 400);
    return;
  }

  if (!state.pendingTrade) {
    closePinModal();
    return;
  }

  confirmBtn.disabled = true;
  confirmBtn.textContent = 'Memverifikasi...';

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
        asset: state.pendingTrade.assetSymbol,
        amount: state.pendingTrade.amount,
        price: state.pendingTrade.price
      })
    });

    const json = await res.json();

    if (json.success && json.data) {
      // Trade Success
      state.session.cashBalance = json.data.new_balance;
      state.session.hasPin = true;

      // Update portfolios
      if (json.data.portfolio) {
        Object.keys(json.data.portfolio).forEach(k => {
          if (state.portfolio[k]) {
            state.portfolio[k].amount = json.data.portfolio[k][0] || 0;
            state.portfolio[k].avgBuyPrice = json.data.portfolio[k][1] || 0;
          }
        });
      }

      // Add trade log
      if (json.data.trade) {
        const t = json.data.trade;
        const now = new Date();
        state.tradeLogs.unshift({
          time: `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`,
          type: t.trade_type,
          asset: t.asset,
          amount: t.amount,
          price: t.price,
          total: t.total
        });
      }

      closePinModal();
      startAntiWhaleCooldown();
      updateUserProfileUI();
      renderPortfolioTable();
      renderTradeHistory();

      // Clear input
      document.getElementById('trade-amount-input').value = '';
      calculateTradeCost();

      showToast(json.message || 'Transaksi berhasil dieksekusi!', 'success');

    } else {
      // Error
      pinCard.classList.add('animate-shake');
      setTimeout(() => pinCard.classList.remove('animate-shake'), 400);

      pinError.textContent = json.message || 'PIN salah atau transaksi gagal.';
      pinError.classList.remove('hidden');

      if (json.error_code === 'PIN_NOT_SET') {
        document.getElementById('pin-not-set-notice').classList.remove('hidden');
      }
    }
  } catch (err) {
    console.error('Trade error:', err);
    pinError.textContent = 'Terjadi kesalahan komunikasi dengan server.';
    pinError.classList.remove('hidden');
  } finally {
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Konfirmasi Transaksi';
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
  const tf = state.timeframe || '5M';

  let stepMinutes = 5;
  if (tf === '1M') stepMinutes = 1;
  else if (tf === '5M') stepMinutes = 5;
  else if (tf === '15M') stepMinutes = 15;
  else if (tf === '1H') stepMinutes = 60;
  else if (tf === '1D') stepMinutes = 1440;

  for (let i = count - 1; i >= 0; i--) {
    const t = new Date(now.getTime() - i * stepMinutes * 60000);
    if (tf === '1D') {
      labels.push(`${t.getDate()}/${t.getMonth() + 1}`);
    } else {
      labels.push(`${String(t.getHours()).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')}`);
    }
  }
  return labels;
}

function updateChartData() {
  if (!chartInstance) return;
  const asset = state.assets[state.activeAsset];
  const isPositive = asset.changePercent >= 0;

  const ctx = chartInstance.ctx;
  const gradient = ctx.createLinearGradient(0, 0, 0, 360);
  if (isPositive) {
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
  } else {
    gradient.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
    gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
  }

  chartInstance.data.labels = generateChartLabels(asset.history.length);
  chartInstance.data.datasets[0].label = asset.name;
  chartInstance.data.datasets[0].data = [...asset.history];
  chartInstance.data.datasets[0].borderColor = isPositive ? '#10b981' : '#ef4444';
  chartInstance.data.datasets[0].pointBackgroundColor = isPositive ? '#10b981' : '#ef4444';
  chartInstance.data.datasets[0].backgroundColor = gradient;
  chartInstance.update('none');
}

function setTimeframe(tf) {
  state.timeframe = tf;
  document.querySelectorAll('.tf-btn').forEach(b => {
    b.classList.remove('bg-primary', 'text-white', 'font-bold', 'active');
    b.classList.add('text-neutral-400');
  });

  const activeBtn = Array.from(document.querySelectorAll('.tf-btn')).find(b => b.textContent.trim() === tf);
  if (activeBtn) {
    activeBtn.classList.add('bg-primary', 'text-white', 'font-bold', 'active');
    activeBtn.classList.remove('text-neutral-400');
  }

  updateChartData();
}

// =======================================================
//   ORDER CALCULATIONS & FORM SHORTCUTS
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
  const cashDisplay = document.getElementById('player-cash-display');
  if (cashDisplay) {
    cashDisplay.textContent = `$${state.session.cashBalance.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  }
  
  const formBal = document.getElementById('form-available-balance');
  if (formBal) {
    if (state.activeTradeType === 'BUY') {
      formBal.textContent = `$${state.session.cashBalance.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    } else {
      const owned = state.portfolio[state.activeAsset]?.amount || 0;
      formBal.textContent = `${owned.toFixed(2)} ${state.assets[state.activeAsset].symbol}`;
    }
  }
}

function setPercentageAmount(pct) {
  const asset = state.assets[state.activeAsset];
  const input = document.getElementById('trade-amount-input');

  if (state.activeTradeType === 'BUY') {
    const totalCash = state.session.cashBalance;
    const taxRate = getAssetTaxRate(state.activeAsset);
    const maxBuyAmount = totalCash / (asset.price * (1 + taxRate));
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

  const taxRate = getAssetTaxRate(state.activeAsset);
  const taxPercent = Math.round(taxRate * 100);
  const subtotal = amount * asset.price;
  const tax = subtotal * taxRate;
  const total = state.activeTradeType === 'BUY' ? subtotal + tax : subtotal - tax;

  const taxLabel = document.getElementById('summary-tax-label');
  if (taxLabel) taxLabel.textContent = `Protocol Tax (${taxPercent}%):`;

  document.getElementById('summary-subtotal').textContent = `$${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  document.getElementById('summary-tax').textContent = `$${tax.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
  document.getElementById('summary-total').textContent = `$${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
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

function renderTradeHistory() {
  const container = document.getElementById('trade-history-list');
  if (!container) return;

  if (state.tradeLogs.length === 0) {
    container.innerHTML = `<div class="text-center py-6 text-neutral-500 font-sans">Belum ada transaksi pada akun ini.</div>`;
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
    b.classList.remove('bg-primary', 'text-white', 'shadow-lg');
    b.classList.add('bg-neutral-900/60', 'text-neutral-400');
  });

  const activeBtn = document.getElementById(`tab-btn-${tab}`);
  if (activeBtn) {
    activeBtn.classList.add('bg-primary', 'text-white', 'shadow-lg');
    activeBtn.classList.remove('bg-neutral-900/60', 'text-neutral-400');
  }

  document.getElementById('tab-content-portfolio')?.classList.add('hidden');
  document.getElementById('tab-content-orderbook')?.classList.add('hidden');
  document.getElementById('tab-content-history')?.classList.add('hidden');
  document.getElementById('tab-content-leaderboard')?.classList.add('hidden');

  const content = document.getElementById(`tab-content-${tab}`);
  if (content) content.classList.remove('hidden');

  if (tab === 'leaderboard') {
    loadLeaderboard();
  }

  if (window.lucide) {
    lucide.createIcons();
  }
}

// =======================================================
//   TOP 10 INVESTORS LEADERBOARD ENGINE
// =======================================================
async function loadLeaderboard() {
  try {
    const res = await fetch('/api/trading/leaderboard');
    const json = await res.json();

    if (!json.success || !json.data) return;

    const { total_investors, total_market_cap, top_investors } = json.data;

    const mCapEl = document.getElementById('lb-market-cap');
    const tInvEl = document.getElementById('lb-total-investors');
    if (mCapEl) mCapEl.textContent = `$${total_market_cap.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    if (tInvEl) tInvEl.textContent = `${total_investors} Pemain`;

    // Render Podium (Top 3)
    const podiumEl = document.getElementById('leaderboard-podium');
    if (podiumEl) {
      if (top_investors.length === 0) {
        podiumEl.innerHTML = `
          <div class="col-span-1 sm:col-span-3 text-center py-10 px-4 rounded-2xl bg-neutral-950/60 border border-neutral-900 space-y-2">
            <div class="mx-auto h-12 w-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
              <i data-lucide="trophy" class="h-6 w-6"></i>
            </div>
            <h4 class="font-bold text-white text-sm">Belum Ada Investor Terdaftar</h4>
            <p class="text-xs text-neutral-400 max-w-md mx-auto leading-relaxed">
              Jadilah yang pertama masuk ke Top 10 Hall of Fame! Masuk ke server Minecraft in-game (<code class="text-primary">genzsmp.site</code>) dan ketik: <strong class="text-white">/invest setpin &lt;6-digit&gt;</strong> untuk memulai portofolio Anda.
            </p>
          </div>
        `;
      } else {
        const top3 = top_investors.slice(0, 3);
        const podiumCards = top3.map((inv, idx) => {
          const rankColors = [
            { border: 'border-amber-500/50', bg: 'bg-amber-500/10', text: 'text-amber-400', badge: '#1 GOLD', label: '1ST PLACE' },
            { border: 'border-slate-400/40', bg: 'bg-slate-400/10', text: 'text-slate-300', badge: '#2 SILVER', label: '2ND PLACE' },
            { border: 'border-amber-700/40', bg: 'bg-amber-700/10', text: 'text-amber-600', badge: '#3 BRONZE', label: '3RD PLACE' }
          ][idx] || { border: 'border-neutral-800', bg: 'bg-neutral-900', text: 'text-white', badge: '#', label: '' };

          return `
            <div class="p-4 rounded-2xl ${podiumCardsBg(idx)} border ${rankColors.border} relative overflow-hidden flex flex-col items-center text-center space-y-2.5 shadow-xl">
              <div class="flex items-center justify-between w-full text-[10px] font-mono font-bold">
                <span class="${rankColors.text} flex items-center gap-1">${rankColors.badge} • ${rankColors.label}</span>
                <span class="px-2 py-0.5 rounded-full bg-neutral-900/80 border border-neutral-800 text-neutral-300">${inv.badge}</span>
              </div>

              <div class="relative">
                <img src="${inv.avatar_url}" alt="${inv.player_name}" class="h-14 w-14 rounded-xl border-2 ${rankColors.border} shadow-lg" onerror="this.src='/images/logo.png'" />
                ${inv.is_bedrock ? '<span class="absolute -bottom-1 -right-1 px-1.5 py-0.2 rounded text-[8px] font-bold bg-cyan-500 text-black">BE</span>' : ''}
              </div>

              <div>
                <h4 class="font-bold text-white text-sm truncate max-w-[140px]">${inv.player_name}</h4>
                <p class="text-[10px] text-purple-400 font-bold uppercase tracking-wider">Total Aset Investasi</p>
                <p class="font-mono font-black text-sm text-purple-300">$${inv.assets_value.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
              </div>

              <div class="w-full pt-2 border-t border-neutral-800/80 grid grid-cols-2 text-[10px] font-mono text-neutral-400">
                <div>Kas: <strong class="text-white">$${(inv.cash_balance / 1000).toFixed(1)}k</strong></div>
                <div>Trades: <strong class="text-white">${inv.total_trades}x</strong></div>
              </div>
            </div>
          `;
        });
        podiumEl.innerHTML = podiumCards.join('');
      }
    }

    // Render Table (Rank 4 - 10)
    const tableBody = document.getElementById('leaderboard-table-body');
    if (tableBody) {
      if (top_investors.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-6 text-neutral-500 font-sans">Belum ada riwayat transaksi investor pada sesi ini.</td></tr>`;
      } else {
        const rest = top_investors.slice(3);
        if (rest.length === 0) {
          tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-neutral-500 font-sans">Semua investor terdaftar telah tampil di podium atas.</td></tr>`;
        } else {
          tableBody.innerHTML = rest.map(inv => `
            <tr class="hover:bg-white/[0.02] transition">
              <td class="py-2.5 px-3 font-bold text-neutral-400">#${inv.rank}</td>
              <td class="py-2.5 px-3">
                <div class="flex items-center gap-2">
                  <img src="${inv.avatar_url}" class="h-6 w-6 rounded-md border border-neutral-800" onerror="this.src='/images/logo.png'" />
                  <span class="font-bold text-white">${inv.player_name}</span>
                  ${inv.is_bedrock ? '<span class="px-1 py-0.2 rounded text-[8px] font-bold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">BEDROCK</span>' : ''}
                </div>
              </td>
              <td class="py-2.5 px-3">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neutral-900 border border-neutral-800 text-neutral-300">${inv.badge}</span>
              </td>
              <td class="py-2.5 px-3 text-purple-400 font-bold">$${inv.assets_value.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
              <td class="py-2.5 px-3 text-neutral-300">$${inv.cash_balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
              <td class="py-2.5 px-3 text-right text-emerald-400 font-bold">$${inv.total_net_worth.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
            </tr>
          `).join('');
        }
      }
    }

    if (window.lucide) {
      lucide.createIcons();
    }
  } catch (err) {
    console.error('Error loading leaderboard:', err);
  }
}

function podiumCardsBg(idx) {
  if (idx === 0) return 'bg-gradient-to-b from-amber-500/10 via-neutral-950 to-neutral-950';
  if (idx === 1) return 'bg-gradient-to-b from-slate-400/10 via-neutral-950 to-neutral-950';
  return 'bg-gradient-to-b from-amber-800/10 via-neutral-950 to-neutral-950';
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

// =======================================================
//   PWA (PROGRESSIVE WEB APP) ENGINE
// =======================================================
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  const btn = document.getElementById('pwa-install-btn');
  if (btn) {
    btn.classList.remove('hidden');
    btn.classList.add('flex');
  }
});

async function triggerPwaInstall() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === 'accepted') {
      showToast('Aplikasi GenzSMP Trading berhasil dipasang di layar utama!', 'success');
    }
    deferredPrompt = null;
    const btn = document.getElementById('pwa-install-btn');
    if (btn) {
      btn.classList.add('hidden');
      btn.classList.remove('flex');
    }
  } else {
    showToast('Untuk memasang di HP: Buka menu browser (titik 3) lalu pilih "Tambahkan ke Layar Utama" / "Add to Home Screen".', 'info');
  }
}

// Expose all interactive functions explicitly on window
window.openLoginModal = openLoginModal;
window.closeLoginModal = closeLoginModal;
window.handleLoginSubmit = handleLoginSubmit;
window.promptPinModal = promptPinModal;
window.openPinModal = promptPinModal;
window.closePinModal = closePinModal;
window.handlePinSubmit = handlePinSubmit;
window.switchBottomTab = switchBottomTab;
window.selectAsset = selectAsset;
window.setTradeType = setTradeType;
window.setPercentageAmount = setPercentageAmount;
window.setTimeframe = setTimeframe;
window.calculateTradeCost = calculateTradeCost;
window.triggerPwaInstall = triggerPwaInstall;
window.loginPlayer = loginPlayer;

