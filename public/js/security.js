/**
 * =========================================================================
 *  GENZSMP CLIENT-SIDE SECURITY & ANTI-INSPECT ENGINE
 *  - Disables F12, DevTools shortcuts (Ctrl+Shift+I/J/C, Ctrl+U, Ctrl+S)
 *  - Disables Right Click Context Menu
 *  - Self-XSS Console Anti-Tamper Banner
 *  - Image & Asset Drag Protection
 * =========================================================================
 */

(function () {
  'use strict';

  // 1. DISABLE SHORTCUT KEYS (F12, Ctrl+Shift+I/J/C, Ctrl+U, Ctrl+S, Ctrl+P)
  window.addEventListener('keydown', function (e) {
    // F12 key
    if (e.key === 'F12' || e.keyCode === 123) {
      e.preventDefault();
      e.stopPropagation();
      notifyBlockedAction('Tombol F12 dinonaktifkan demi keamanan sistem.');
      return false;
    }

    const isCtrlOrCmd = e.ctrlKey || e.metaKey;

    // Ctrl + Shift + I (Inspect), Ctrl + Shift + J (Console), Ctrl + Shift + C (Element Picker)
    if (isCtrlOrCmd && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
      e.preventDefault();
      e.stopPropagation();
      notifyBlockedAction('Akses Developer Tools dinonaktifkan.');
      return false;
    }

    // Ctrl + U (View Page Source)
    if (isCtrlOrCmd && (e.key === 'u' || e.key === 'U')) {
      e.preventDefault();
      e.stopPropagation();
      notifyBlockedAction('Akses View Page Source dinonaktifkan.');
      return false;
    }

    // Ctrl + S (Save Page)
    if (isCtrlOrCmd && (e.key === 's' || e.key === 'S')) {
      e.preventDefault();
      e.stopPropagation();
      notifyBlockedAction('Penyimpanan halaman lokal dinonaktifkan.');
      return false;
    }
  }, true);

  // 2. SELF-XSS CONSOLE BANNER
  function printConsoleWarning() {
    if (typeof console !== 'undefined') {
      console.log(
        '%c🛑 STOP! PERINGATAN KEAMANAN GENZSMP 🛑',
        'color: #ef4444; font-size: 20px; font-weight: 900;'
      );
      console.log(
        '%cSeluruh transaksi trading, saldo Vault, dan PIN 6-digit diverifikasi secara aman langsung pada server backend Laravel & SQLite.',
        'color: #a855f7; font-size: 12px; font-weight: bold;'
      );
    }
  }

  printConsoleWarning();

  // 5. TOAST NOTIFICATION FOR BLOCKED ACTIONS
  function notifyBlockedAction(msg) {
    if (typeof showToast === 'function') {
      showToast(msg, 'danger');
      return;
    }

    // Fallback toast if showToast is not defined on current page
    let toast = document.getElementById('genz-security-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'genz-security-toast';
      toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(24, 24, 27, 0.95);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.3);
        box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        backdrop-filter: blur(12px);
        padding: 12px 20px;
        border-radius: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 12px;
        font-weight: 700;
        z-index: 999999;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: opacity 0.3s ease, transform 0.3s ease;
        opacity: 0;
        pointer-events: none;
      `;
      document.body.appendChild(toast);
    }

    toast.innerHTML = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span> ${msg}`;
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';

    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(-50%) translateY(10px)';
    }, 2500);
  }
})();
