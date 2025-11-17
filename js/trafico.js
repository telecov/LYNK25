// AJAX 1s para Tráfico Actual + cronómetro
(function(){
  const elCard = document.getElementById('traficoCard');
  const elBadge= document.getElementById('traficoBadge');
  const elCall = document.getElementById('traficoCall');
  const elID   = document.getElementById('traficoID');
  const elTG   = document.getElementById('traficoTG');
  const elHora = document.getElementById('traficoHora');
  const elNom  = document.getElementById('traficoNombre');
  const elDur  = document.getElementById('traficoDur');

  let timer = null, startTs = null;

  function fmt(secs){
    secs = Math.max(0, secs|0);
    const h = Math.floor(secs/3600), m = Math.floor((secs%3600)/60), s = secs%60;
    return (h>0? String(h).padStart(2,'0')+':' : '') + String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  }
  function tick(){
    if (!startTs) return;
    const now = Math.floor(Date.now()/1000);
    if (elDur) elDur.textContent = fmt(now - startTs);
  }
  function startTimer(ts){
    if (timer) clearInterval(timer);
    startTs = ts || null;
    if (!startTs){ if (elDur) elDur.textContent = '—'; return; }
    tick();
    timer = setInterval(tick, 1000);
  }

  async function poll(){
    try{
      const r = await fetch('index.php?ajax=trafico&_=' + Date.now(), {cache:'no-store'});
      if(!r.ok) throw new Error();
      const d = await r.json();

      if (elCall){
        elCall.innerHTML = d.callsign && d.qrz
          ? `<a class="text-white text-decoration-underline" target="_blank" rel="noopener" href="${d.qrz}">${d.callsign}</a>`
          : '—';
      }
      if (elID)   elID.textContent   = d.id   || '—';
      if (elTG)   elTG.textContent   = d.tg   || '—';
      if (elHora) elHora.textContent = d.started_at || d.hora || '—';
      if (elNom)  elNom.textContent  = d.name || '—';

      if (d.active && d.started_ts){
        if (startTs !== d.started_ts) startTimer(d.started_ts);
        if (elCard){
          elCard.classList.remove('bg-success'); elCard.classList.add('bg-danger');
        }
        if (elBadge){
          elBadge.classList.remove('text-success'); elBadge.classList.add('text-danger');
          elBadge.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Transmitiendo';
        }
      } else {
        startTimer(null);
        if (elCard){
          elCard.classList.remove('bg-danger'); elCard.classList.add('bg-success');
        }
        if (elBadge){
          elBadge.classList.remove('text-danger'); elBadge.classList.add('text-success');
          elBadge.textContent = 'En espera';
        }
      }
    }catch(e){ /* silencioso */ }
  }

  poll();
  setInterval(poll, 5000);
})();

