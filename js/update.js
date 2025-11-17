document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("checkUpdate");
  const result = document.getElementById("updateResult");

  if (!btn) return;

  btn.addEventListener("click", (e) => {
    e.preventDefault();
    result.innerHTML = "⏳ Comprobando versión...";

    // Paso 1: verificar si hay nueva versión
    fetch("includes/check_update.php")
      .then(r => r.json())
      .then(data => {
        if (data.status === "update_available") {
          const latest = data.latest_version;
          const local = data.local_version;

          if (confirm(`🚀 Se encontró una nueva versión (${latest})\nTu versión actual es ${local}\n¿Deseas actualizar ahora?`)) {
            result.innerHTML = "🔄 Descargando e instalando actualización...";
            // Paso 2: ejecutar actualización real
            fetch("includes/check_update.php?do_update=1")
              .then(r => r.json())
              .then(update => {
                if (update.status === "success") {
                  result.innerHTML = `✅ ${update.message}`;
                } else {
                  result.innerHTML = `⚠️ ${update.message || 'Error al actualizar'}`;
                }
              })
              .catch(err => {
                console.error(err);
                result.innerHTML = "❌ Error durante la actualización.";
              });
          } else {
            result.innerHTML = "❎ Actualización cancelada por el usuario.";
          }
        } 
        else if (data.status === "up_to_date") {
          result.innerHTML = "✅ LYNK25 está actualizado.";
        } 
        else {
          result.innerHTML = `⚠️ ${data.message}`;
        }
      })
      .catch(err => {
        console.error(err);
        result.innerHTML = "❌ Error al verificar actualización.";
      });
  });
});
