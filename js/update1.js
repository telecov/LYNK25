document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("checkUpdate");
  const result = document.getElementById("updateResult");

  if (!btn) return;

  btn.addEventListener("click", (e) => {
    e.preventDefault();
    result.innerHTML = "⏳ Comprobando versión...";
    fetch("includes/check_update.php")
      .then(r => r.json())
      .then(data => {
        if (data.status === "update_available") {
          result.innerHTML = `
            🆕 Nueva versión: <b>${data.latest_version}</b> 
            <a href="${data.url_zip}" target="_blank" class="btn btn-success btn-sm ms-1">Descargar</a>
          `;
        } else if (data.status === "up_to_date") {
          result.innerHTML = "✅ LYNK25 actualizado";
        } else {
          result.innerHTML = `⚠️ ${data.message}`;
        }
      })
      .catch(err => {
        result.innerHTML = "❌ Error al verificar actualización.";
        console.error(err);
      });
  });
});
