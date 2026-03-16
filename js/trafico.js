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

  if (!card || !badge || !call || !id || !name || !tg || !hora || !dur) {
    console.error('Faltan elementos del bloque de tráfico en el HTML');
    return;
  }

  let timer = null;
  let startTs = null;
  let lastState = null;

  function fmtDur(sec) {
    sec = Math.max(0, Math.floor(sec));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;

    if (h > 0) return `${h}h ${m}m ${s}s`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
  }

  function stopTimer() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
    startTs = null;
    dur.textContent = '—';
  }

  function startTimer(ts) {
    if (!ts) {
      stopTimer();
      return;
    }

    // Si viene en milisegundos, convertir a segundos
    if (ts > 9999999999) {
      ts = Math.floor(ts / 1000);
    }

    if (startTs === ts && timer) return;

    stopTimer();
    startTs = ts;

    const render = () => {
      const now = Math.floor(Date.now() / 1000);
      dur.textContent = fmtDur(now - startTs);
    };

    render();
    timer = setInterval(render, 1000);
  }

  function setActiveState(d) {
    card.classList.remove('bg-success', 'bg-warning');
    card.classList.add('bg-danger');

    badge.className = 'badge bg-light text-danger ms-2';
    badge.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Transmitiendo';

    call.innerHTML = d.callsign && d.qrz
      ? `<a href="${d.qrz}" target="_blank" class="text-white text-decoration-underline">${d.callsign}</a>`
      : (d.callsign || '—');

    id.textContent   = d.id || '—';
    name.textContent = d.name || '—';
    tg.textContent   = d.tg || '—';
    hora.textContent = d.started_at || d.hora || '—';

    startTimer(d.started_ts);
  }

  function setIdleState(d) {
    card.classList.remove('bg-danger', 'bg-warning');
    card.classList.add('bg-success');

    badge.className = 'badge bg-light text-success ms-2';
    badge.textContent = 'En espera';

    call.innerHTML = d.callsign && d.qrz
      ? `<a href="${d.qrz}" target="_blank" class="text-white text-decoration-underline">${d.callsign}</a>`
      : (d.callsign || '—');

    id.textContent   = d.id || '—';
    name.textContent = d.name || '—';
    tg.textContent   = d.tg || '—';
    hora.textContent = d.hora || '—';

    stopTimer();
  }

  async function poll() {
    try {
      const r = await fetch(`index.php?ajax=trafico&_=${Date.now()}`, {
        cache: 'no-store'
      });

      if (!r.ok) {
        console.error('Respuesta HTTP inválida:', r.status);
        return;
      }

      const d = await r.json();

      const isActive = (d.active === true || d.active === 1 || d.active === '1');

      // Evita repintados innecesarios, pero permite actualizar datos si cambian
      const newState = JSON.stringify({
        active: isActive,
        callsign: d.callsign || null,
        id: d.id || null,
        tg: d.tg || null,
        hora: d.hora || null,
        started_ts: d.started_ts || null
      });

      if (newState !== lastState) {
        lastState = newState;
      }

      if (isActive) {
        setActiveState(d);
      } else {
        setIdleState(d);
      }

    } catch (e) {
      console.error('Error tráfico:', e);
    }
  }

  poll();
  setInterval(poll, 1000);
})();
