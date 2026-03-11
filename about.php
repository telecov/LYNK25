<?php
/* ===========================
   Cargar header_config.json
   =========================== */
$config_file = __DIR__ . '/data/header_config.json';

$default = [
  'idioma'   => 'es',
  'title'    => 'REFLECTOR P25 – ZONA DMR',
  'subtitle' => 'Conectando amigos, enlazando pasiones por el aire.',
  'logo'     => 'img/zdmrlogoindex.png'
];

$config = $default;
if (file_exists($config_file)) {
  $tmp = json_decode(file_get_contents($config_file), true);
  if (is_array($tmp)) {
    $config = array_merge($default, $tmp);
  }
}

/* ===========================
   I18N
   =========================== */
$langCode = $config['idioma'] ?? 'es';
$langCode = in_array($langCode, ['es','en'], true) ? $langCode : 'es';

$langFile = __DIR__ . '/data/lang/' . $langCode . '.json';
$lang = file_exists($langFile) ? json_decode(file_get_contents($langFile), true) : [];

function __t(string $key, bool $escape = true): string {
  global $lang;
  $val = $lang[$key] ?? $key;
  return $escape ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : (string)$val;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($langCode) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= __t('about_page_title') ?></title>

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
          <h3 class="mb-1">
            <i class="fas fa-walkie-talkie text-info"></i>
            <?= htmlspecialchars($config['title']) ?>
          </h3>
          <p class="mb-0 fst-italic text-center text-light small">
            “<?= htmlspecialchars($config['subtitle']) ?>”
          </p>
        </div>
      </div>

      <div class="col-md-4 text-center mt-1 mt-md-0 d-flex flex-column align-items-center">
        <img src="<?= htmlspecialchars($config['logo']) ?>" alt="Grupo Zona DMR"
             class="img-fluid rounded shadow-sm mb-2" style="max-height: 140px;">
        <div class="d-flex flex-wrap justify-content-center header-tools">
          <a href="index.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="<?= __t('tt_go_dashboard') ?>" aria-label="Dashboard">
            <i class="fas fa-house"></i>
          </a>
          <a href="personalizar_header.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="<?= __t('tt_customize') ?>" aria-label="Personalizar">
            <i class="fas fa-pen"></i>
          </a>
          <a href="about.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="<?= __t('tt_about') ?>" aria-label="About">
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
        <h3 class="h4 mb-3"><?= __t('lynk25_section_title') ?></h3>

        <?= __t('lynk25_paragraph_1', false) ?>

        <?= __t('lynk25_feature_list', false) ?>

        <?= __t('lynk25_creator_block', false) ?>
      </div>
    </div>
  </section>

  <!-- Qué es P25 en radioafición -->
  <section class="mb-4">
    <div class="card bg-dark border-secondary shadow-sm">
      <div class="card-body">
        <h3 class="h5 mb-3"><?= __t('p25_section_title') ?></h3>

        <?= __t('p25_paragraph_1', false) ?>

        <?= __t('p25_list', false) ?>

        <p class="mb-0">
          <small class="text-muted">
            <?= __t('p25_best_practices', false) ?>
          </small>
        </p>
      </div>
    </div>
  </section>

  <!-- Recursos de la Red P25 -->
  <section class="mb-4">
    <div class="card bg-dark border-secondary shadow-sm">
      <div class="card-body">
        <h3 class="h5 mb-3">
          <i class="fas fa-globe text-info"></i> <?= __t('resources_title') ?>
        </h3>
        <p class="mb-2"><?= __t('resources_intro') ?></p>

        <?= __t('resources_list', false) ?>
      </div>
    </div>
  </section>

  <!-- Agradecimientos y Donación -->
  <section class="mb-4">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card bg-dark border-secondary shadow-sm h-100">
          <div class="card-body">
            <h3 class="h5 mb-3"><?= __t('thanks_title') ?></h3>

            <?= __t('thanks_text', false) ?>

            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-light border-light" href="https://github.com/nostar/DVReflectors" target="_blank" rel="noopener">
                <i class="fas fa-download me-1"></i> <?= __t('btn_download_p25reflector') ?>
              </a>
              <a class="btn btn-outline-light border-light" href="https://github.com/g4klx/P25Clients" target="_blank" rel="noopener">
                <i class="fas fa-toolbox me-1"></i> <?= __t('btn_p25_clients') ?>
              </a>
              <a class="btn btn-outline-light border-light" href="https://github.com/telecov" target="_blank" rel="noopener">
                <i class="fa-solid fa-table-columns me-1"></i> <?= __t('btn_dashboard_repo') ?>
              </a>
            </div>

            <p class="text-muted mt-3 mb-0 small"><?= __t('thanks_note') ?></p>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card bg-dark border-secondary shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h3 class="h5 mb-2"><?= __t('support_title') ?></h3>

            <?= __t('support_text', false) ?>

            <!-- Redes sociales (como el original) -->
            <div class="d-flex justify-content-start gap-3 mb-4">
              <a href="https://www.youtube.com/@Telecoviajero" target="_blank" class="text-danger fs-4"
                 data-bs-toggle="tooltip" title="<?= __t('tt_youtube') ?>">
                <i class="fab fa-youtube"></i>
              </a>
              <a href="https://www.tiktok.com/@telecoviajero" target="_blank" class="text-light fs-4"
                 data-bs-toggle="tooltip" title="<?= __t('tt_tiktok') ?>">
                <i class="fab fa-tiktok"></i>
              </a>
              <a href="https://www.instagram.com/telecoviajero" target="_blank" class="text-warning fs-4"
                 data-bs-toggle="tooltip" title="<?= __t('tt_instagram') ?>">
                <i class="fab fa-instagram"></i>
              </a>
            </div>

            <!-- Botón PayPal (idéntico a tu original) -->
            <div class="d-grid mt-auto">
              <form action="https://www.paypal.com/donate" method="post" target="_top">
                <input type="hidden" name="hosted_button_id" value="7PSGRCUBLSRDY" />
                <button type="submit" class="btn btn-outline-info w-100">
                  <i class="fas fa-heart me-2"></i> <?= __t('btn_donate') ?>
                </button>
              </form>
            </div>

          </div>
        </div>
      </div>

    </div>
  </section>

</main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // tooltips bootstrap
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>


<?php 
$product_name = "LYNK25";
require __DIR__ . '/includes/footer.php'; 
?>


</body>
</html>
