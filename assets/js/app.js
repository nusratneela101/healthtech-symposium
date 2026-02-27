/**
 * Canada HealthTech Innovation Symposium — app.js
 */

/** Start live clock in #clock element */
function startClock() {
  const el = document.getElementById('clock');
  if (!el) return;
  function update() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    el.textContent = h + ':' + m + ':' + s;
  }
  update();
  setInterval(update, 1000);
}

/** Show a toast notification */
function showToast(msg, type) {
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) return;
  const t = document.createElement('div');
  t.className = 'toast ' + (type === 'error' ? 'toast-err' : 'toast-ok');
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transition = 'opacity .4s';
    setTimeout(() => t.remove(), 400);
  }, 4500);
}

/** Animate province bars from data-width attribute */
function animateProvBars() {
  document.querySelectorAll('.prov-fill').forEach(function(el) {
    const target = el.dataset.width || '0%';
    setTimeout(function() { el.style.width = target; }, 100);
  });
}

/** Auto-dismiss flash messages after 5 seconds */
function initFlash() {
  const el = document.getElementById('flash-msg');
  if (!el) return;
  setTimeout(function() {
    el.style.opacity = '0';
    el.style.transition = 'opacity .5s';
    setTimeout(function() { el.remove(); }, 500);
  }, 5000);
}

/** DOMContentLoaded: boot all functions */
document.addEventListener('DOMContentLoaded', function() {
  startClock();
  animateProvBars();
  initFlash();
});
