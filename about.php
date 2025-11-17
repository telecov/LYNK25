<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>About · LYNK25</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">  
  <link rel="icon" type="image/png" href="img/lynk25_favicon.png">

</head>
<body class="dark-mode">

<!-- Header idéntico al index -->
<div class="container py-3 flex-grow-1">
  <div class="bg-dark text-white py-0 rounded shadow-sm mb-3">
    <div class="row align-items-center">
      <div class="col-md-8 d-flex align-items-center">
        <img src="img/lynk25logo.png" alt="Lynk25" class="me-3 img-fluid" style="max-height:180px;">
        <div>
          <h3 class="mb-1"><i class="fas fa-walkie-talkie text-info"></i> REFLECTOR P25 – ZONA DMR</h3>
          <p class="mb-0 fst-italic text-center text-light small">“Conectando amigos, enlazando pasiones por el aire.”</p>
        </div>
      </div>
      <div class="col-md-4 text-center mt-1 mt-md-0 d-flex flex-column align-items-center">
        <img src="img/zdmrlogoindex.png" alt="Grupo Zona DMR" class="img-fluid rounded shadow-sm mb-2" style="max-height: 140px;">
        <div class="d-flex flex-wrap justify-content-center header-tools">
          <a href="index.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="Ir al Dashboard" aria-label="Dashboard">
            <i class="fas fa-house"></i>
          </a>
          <a href="personalizar_header.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="Personalizar" aria-label="Personalizar">
            <i class="fas fa-pen"></i>
          </a>
          <a href="about.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="About Lynk25" aria-label="About">
            <i class="fas fa-circle-info"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<main class="container my-4">
  <!-- LYNK25: propósito y dedicación -->
  <section class="mb-4">
    <div class="card bg-dark border-secondary shadow-sm">
      <div class="card-body">
        <h3 class="h4 mb-3">LYNK25</h3>
<p>
  <strong>LYNK25 Dashboard</strong> es el panel web del reflector P25 que <strong>centraliza</strong>
  el estado del sistema, los <strong>QSOs en curso</strong> y las <strong>métricas del servidor</strong>,
  para que cualquier radioaficionado pueda ver la actividad y sumarse <strong>al mundo digital</strong>.
</p>

<ul class="mb-2">
  <li><strong>Claridad inmediata:</strong> actividad en vivo, alertas y estaciones conectadas en una sola vista.</li>
  <li><strong>Bajo esfuerzo:</strong> menos clics, más operación. Diseño ligero con recarga periódica.</li>
  <li><strong>Datos útiles:</strong> indicativo con enlace a QRZ, búsqueda de nombre vía RadioID y detalle de TG.</li>
  <li><strong>Estabilidad:</strong> modo noche fijo, tipografías legibles y componentes Bootstrap.</li>
  <li><strong>Personalizable:</strong> título, subtítulo y logo desde la página de ajustes.</li>
  <li><strong>Telegram:</strong> recibe en el canal oficial conexiones, estado y datos utiles de LYNK25.</li>
</ul>

<div class="p-3 border rounded-3 bg-dark">
  <p class="mb-2">
    Creado por <strong>CA2RDP / TELECOVIAJERO</strong> con el objetivo de una web
    <strong>intuitiva, clara y rápida</strong> — interfaz inspirada en <strong>Jocelyn, mi esposa</strong> tanto en nombre tipografias y colores, detalles que unen lo familiar y el hobbie.
  </p>
  <p class="mb-2">
    Gracias a la comunidad que impulsa P25 y a los proyectos open-source de referencia que lo hacen posible, saludos especiales a mis amigos radioaficionados de <strong>ZONA DMR CHILE</strong> que motivaron con entusiamo levantar un reflector P25 para seguir sacando provecho a los modos digitales de la radioaficion.
  </p>
  <div class="mt-1 small">
    <span class="badge bg-info text-dark border">CA2PTO</span>
    <span class="badge bg-info text-dark border">CA5EHC</span>
    <span class="badge bg-info text-dark border">CE2DRB</span>
    <span class="ms-1 text-muted">73</span>
  </div>
</div>

      </div>
    </div>
  </section>

  <!-- Qué es P25 en radioafición -->
  <section class="mb-4">
    <div class="card bg-dark border-secondary shadow-sm">
      <div class="card-body">
        <h3 class="h5 mb-3">¿Qué es P25 en radioafición y cómo lo usamos?</h3>
	<p>
  	 <strong>P25 (Project 25)</strong> es un estándar de radiocomunicaciones digitales para servicios móviles terrestres.
  	 En radioafición utilizamos principalmente la <strong>Fase 1</strong> en modo <strong>convencional</strong> (12.5 kHz),
  	 con modulación <em>C4FM</em> y vocoder <em>IMBE/AMBE</em>. Ofrece audio claro, señalización y trabajo por
  	 <em>talkgroups</em> dentro de VHF/UHF en las bandas de aficionado.
	</p>
	<ul class="mb-2">
  	 <li><strong>Hotspots / MDVM:</strong> enlazan tu equipo P25 con el reflector a través de Internet.</li>
  	 <li><strong>Repetidores MMDVM:</strong> pueden vincularse al reflector para ampliar cobertura y alcance.</li>
  	 <li><strong>Talkgroups:</strong> agrupan las conversaciones por “salas” (ej. <code>30444</code>).</li>
  	 <li><strong>Identidad:</strong> usamos el registro de <em>RadioID</em> para asociar ID ⇄ indicativo/nombre.</li>
	</ul>
	<p class="mb-0">
  	<small class="text-muted">
    	 Buenas prácticas: identifica tu indicativo, deja pausas entre transmisiones para evitar solapes y time-outs,
    	 y respeta la normativa local de banda y potencia.
  	</small>
	</p>
      </div>
    </div>
  </section>

<!-- Recursos de la Red P25 -->
<section class="mb-4">
  <div class="card bg-dark border-secondary shadow-sm">
    <div class="card-body">
      <h3 class="h5 mb-3"><i class="fas fa-globe text-info"></i> Recursos de la Red P25</h3>
      <p class="mb-2">Consulta información en tiempo real y herramientas útiles para P25:</p>
      <ul class="list-unstyled">
        <li>🔗 <a href="https://dvref.com/p25/" target="_blank" class="text-decoration-none text-info">Listado de Reflectores Validados por DVREF</a></li>
	<li>🔗 <a href="https://w0chp.radio/p25-reflectors/" target="_blank" class="text-decoration-none text-info">Listado de Reflectores w0chp</a></li>
        <li>🔗 <a href="https://www.radioid.net/" target="_blank" class="text-decoration-none text-info">Registro de RadioID (IDs e Indicativos)</a></li>
        <li>🔗 <a href="https://zonadmr.cl/" target="_blank" class="text-decoration-none text-info">ZONA DMR CL  - Informacion y BLOG de radioaficion</a></li>
      </ul>
    </div>
  </div>
</section>


  <!-- Agradecimientos y Donación -->
  <section class="mb-4">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card bg-dark border-secondary shadow-sm h-100">
          <div class="card-body">
            <h3 class="h5 mb-3">Agradecimientos</h3>
            <p>Gracias a <strong>Jonathan Naylor, G4KLX</strong>, por su trabajo abierto que hizo posible los reflectores y utilidades P25. También a quienes mantienen <em>forks</em> y dashboards que usamos como referencia.</p>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-light border-light" href="https://github.com/nostar/DVReflectors" target="_blank" rel="noopener">
                <i class="fas fa-download me-1"></i> Descargar P25Reflector
              </a>
              <a class="btn btn-outline-light border-light" href="https://github.com/g4klx/P25Clients" target="_blank" rel="noopener">
                <i class="fas fa-toolbox me-1"></i> P25 Clients (Parrot/Gateway)
              </a>
		<a class="btn btn-outline-light border-light" href="https://github.com/telecov" target="_blank" rel="noopener">
                <i class="fa-solid fa-table-columns me-1"></i> Dashboard WEB LYNK25
              </a>
            </div>
            <p class="text-muted mt-3 mb-0 small">Los enlaces apuntan a repos públicos para compilar o revisar el código.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
  <div class="card bg-dark border-secondary shadow-sm h-100">
    <div class="card-body d-flex flex-column">
      <h3 class="h5 mb-2">Apoya a LYNK25</h3>

      <p class="mb-3">
        Si deseas apoyar este proyecto de manera <strong>voluntaria</strong>, puedes realizar una donación.
        También puedes seguirme en mis redes sociales para más contenido de radioafición, P25 y proyectos técnicos.
      </p>

      <!-- Redes sociales -->
      <div class="d-flex justify-content-start gap-3 mb-4">
        <a href="https://www.youtube.com/@Telecoviajero" target="_blank" class="text-danger fs-4" data-bs-toggle="tooltip" title="YouTube">
          <i class="fab fa-youtube"></i>
        </a>
        <a href="https://www.tiktok.com/@telecoviajero" target="_blank" class="text-light fs-4" data-bs-toggle="tooltip" title="TikTok">
          <i class="fab fa-tiktok"></i>
        </a>
        <a href="https://www.instagram.com/telecoviajero" target="_blank" class="text-warning fs-4" data-bs-toggle="tooltip" title="Instagram">
          <i class="fab fa-instagram"></i>
        </a>
      </div>

      <!-- Botón PayPal conservado (hosted_button_id igual a tu original) -->
      <div class="d-grid mt-auto">
        <form action="https://www.paypal.com/donate" method="post" target="_top">
          <input type="hidden" name="hosted_button_id" value="7PSGRCUBLSRDY" />
          <button type="submit" class="btn btn-outline-info w-100">
            <i class="fas fa-heart me-2"></i> Donación voluntaria
          </button>
        </form>
      </div>

    </div>
  </div>
</div>

    </div>
  </section>
</main>

<!-- FOOTER igual al index -->
<footer class="bg-dark text-white text-center py-3 mt-auto">
  🚀 Dashboard web LYNK25 Desarrollado por <strong>Telecoviajero - CA2RDP</strong> |
  <a href="https://github.com/telecov/LYNK25" target="_blank" class="text-info text-decoration-none">GitHub</a><br>
    © <?php echo date('Y'); ?> Telecoviajero – CA2RDP. Código bajo GPL v3..
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

</script>
</body>
</html>
