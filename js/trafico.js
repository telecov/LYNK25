// js/trafico.js
(function () {
  const card  = document.getElementById('traficoCard');
  const badge = document.getElementById('traficoBadge');
  const call  = document.getElementById('traficoCall');
  const id    = document.getElementById('traficoID');
  const name  = document.getElementById('traficoNombre');
  const tg    = document.getElementById('traficoTG');
  const hora  = document.getElementById('traficoHora');
  const dur   = document.getElementById('traficoDur');

  let timer = null;
  let startTs = null;

  const IDLE_TIMEOUT = 300; // solo visual (5 min)

  function fmtDur(sec) {
    sec = Math.max(0, Math.floor(sec));
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return m > 0 ? `${m}m ${s}s` : `${s}s`;
  }

  function stopTimer() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
    startTs = null;
    if (dur) dur.textContent = '—';
  }

  function startTimer(ts) {
    if (!ts) {
      stopTimer();
      return;
    }

    if (startTs === ts) return; // ya corriendo

    stopTimer();
    startTs = ts;

    timer = setInterval(() => {
      dur.textContent = fmtDur(Math.floor(Date.now() / 1000) - startTs);
    }, 1000);
  }

  async function poll() {
    try {
      const r = await fetch('index.php?ajax=trafico', { cache: 'no-store' });
      if (!r.ok) return;

      const d = await r.json();

      /* ===========================
         PINTAR DATOS (SIEMPRE)
         =========================== */
      call.innerHTML = d.callsign && d.qrz
        ? `<a href="${d.qrz}" target="_blank" class="text-white text-decoration-underline">${d.callsign}</a>`
        : '—';

      id.textContent   = d.id   ?? '—';
      name.textContent = d.name ?? '—';
      tg.textContent   = d.tg   ?? '—';
      hora.textContent = d.started_at || d.hora || '—';

      /* ===========================
         ESTADO VISUAL
         =========================== */
      if (d.active) {
        card.classList.remove('bg-success', 'bg-warning');
        card.classList.add('bg-danger');

        badge.className = 'badge bg-light text-danger ms-2';
        badge.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Transmitiendo';

        startTimer(d.started_ts);
      } else {
        card.classList.remove('bg-danger', 'bg-warning');
        card.classList.add('bg-success');

        badge.className = 'badge bg-light text-success ms-2';
        badge.textContent = 'En espera';

        stopTimer();
      }

    } catch (e) {
      console.error('Error tráfico:', e);
    }
  }

  poll();
  setInterval(poll, 5000);
})();
