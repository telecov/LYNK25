<?php
// personalizar_header.php (Night-Only + Index Style + Basic Auth + Cambio de clave + Timezone + Telegram)
// Autor: Telecoviajero - CA2RDP

session_start();
date_default_timezone_set('America/Santiago');

/* ==============================
   AUTH BÁSICA (HTTP Basic Auth)
   ============================== */
$auth_file = __DIR__ . '/data/admin_auth.json';
$first_run_notice = null;

// Crear credenciales por defecto si no existe el archivo
if (!file_exists($auth_file)) {
    $default_user = 'admin';
    $default_pass = 'cambia_esto_ya'; // ¡cámbiala!
    $hash = password_hash($default_pass, PASSWORD_DEFAULT);
    $seed = [
        'user'      => $default_user,
        'pass_hash' => $hash,
        'hint'      => 'Cambia pass_hash y si quieres el user.'
    ];
    @file_put_contents($auth_file, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    @chmod($auth_file, 0640);
    $first_run_notice = "Se creó admin_auth.json con usuario 'admin' y contraseña temporal 'cambia_esto_ya'. ¡Cámbiala aquí mismo!";
}

// Cargar credenciales actuales
$admin_user = 'admin';
$admin_hash = null;
if (file_exists($auth_file)) {
    $j = @json_decode(@file_get_contents($auth_file), true);
    if (is_array($j)) {
        $admin_user = $j['user'] ?? $admin_user;
        $admin_hash = $j['pass_hash'] ?? null;
    }
}
if (!$admin_hash) {
    $admin_hash = password_hash('cambia_esto_ya', PASSWORD_DEFAULT);
}

// Forzar logout → redirección a index
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}



// Extraer credenciales de Basic Auth (con soporte para proxies)
function get_basic_credentials(): array {
    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

    if (!$user || $pass === null) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($auth && stripos($auth, 'basic ') === 0) {
            $dec = base64_decode(substr($auth, 6));
            if ($dec !== false && strpos($dec, ':') !== false) {
                list($user, $pass) = explode(':', $dec, 2);
            }
        }
    }
    return [$user, $pass];
}

list($u, $p) = get_basic_credentials();
$need_auth = (!$u && $p === null) || $u !== $admin_user || !password_verify($p ?? '', $admin_hash);
if ($need_auth) {
    header('WWW-Authenticate: Basic realm="Lynk25 Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo "Autenticación requerida.";
    exit;
}

/* ==============================
   CONFIG HEADER (ahora con timezone) + TELEGRAM
   ============================== */
$config_file   = __DIR__ . '/data/header_config.json';
$telegram_file = __DIR__ . '/includes/telegram_config.json';
$img_dir       = __DIR__ . '/img/';

$default = [
    'title'    => 'REFLECTOR P25 – ZONA DMR',
    'subtitle' => 'Conectando amigos, enlazando pasiones por el aire.',
    'logo'     => 'img/zdmrlogoindex.png', // fijo
    'timezone' => 'America/Santiago',      // NUEVO: zona horaria por defecto
];
$config = $default;

if (file_exists($config_file)) {
    $json = file_get_contents($config_file);
    $tmp  = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $config = array_merge($default, $tmp);
    }
}

// Cargar (o valores por defecto) de Telegram
$telegram_cfg = ['token' => '', 'chat_id' => '', 'invite_link' => ''];
if (file_exists($telegram_file)) {
    $tj = @json_decode(@file_get_contents($telegram_file), true);
    if (is_array($tj)) $telegram_cfg = array_merge($telegram_cfg, $tj);
}

// DVREF config
$dvref_cfg_file = __DIR__ . '/data/dvref_config.json';
$dvref_cfg = ['token' => '', 'host' => '', 'port' => 41000, 'tg' => 0];
if (file_exists($dvref_cfg_file)) {
    $tmp = @json_decode(@file_get_contents($dvref_cfg_file), true);
    if (is_array($tmp)) $dvref_cfg = array_merge($dvref_cfg, $tmp);
}


// CSRF token simple
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$ok_msgs = [];
$error_msgs = [];

/* ==============================
   Helpers de Timezone (lista y preview)
   ============================== */
function tz_is_valid($tz) {
    static $all = null;
    if ($all === null) $all = DateTimeZone::listIdentifiers();
    return in_array($tz, $all, true);
}
function tz_preview($tz) {
    try {
        $dt = new DateTime('now', new DateTimeZone($tz));
        return $dt->format('Y-m-d H:i:s') . ' (UTC' . $dt->format('P') . ')';
    } catch (Exception $e) {
        return '—';
    }
}
// Construir lista de zonas horarias agrupadas por región (optgroups)
$tz_regions_order = ['America','Europe','Asia','Africa','Australia','Pacific','Atlantic','Indian','Antarctica','Etc','UTC'];
$tz_by_region = [];
foreach (DateTimeZone::listIdentifiers() as $tz) {
    $region = strtok($tz, '/');
    if ($region === false) $region = $tz;
    if (!isset($tz_by_region[$region])) $tz_by_region[$region] = [];
    $tz_by_region[$region][] = $tz;
}
foreach ($tz_by_region as &$arr) { sort($arr, SORT_NATURAL); }
unset($arr);

/* ==============================
   ACCIONES (Guardar encabezado / Cambiar credenciales / Guardar Telegram)
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_header';
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $error_msgs[] = "Token inválido. Refresca la página e inténtalo nuevamente.";
    } else {
        if ($action === 'save_header') {
            // Guardar encabezado
            $title    = trim($_POST['title'] ?? $config['title']);
            $subtitle = trim($_POST['subtitle'] ?? $config['subtitle']);
            $timezone = trim($_POST['timezone'] ?? $config['timezone']);

            if ($title === '')    $title = $default['title'];
            if ($subtitle === '') $subtitle = $default['subtitle'];

            // Validar timezone
            if (!tz_is_valid($timezone)) {
                $error_msgs[] = "Zona horaria inválida. Selecciona una zona IANA (ej: America/Santiago, Europe/Madrid).";
            }

            $logo_path = 'img/zdmrlogoindex.png';

            if (!empty($_FILES['logo_file']['name'])) {
                if (!is_dir($img_dir)) {
                    @mkdir($img_dir, 0755, true);
                }
                $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'png') {
                    $error_msgs[] = "Formato no permitido. Sube una imagen en PNG.";
                } else {
                    $dest = $img_dir . 'zdmrlogoindex.png';
                    @unlink($dest);
                    if (!move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                        $error_msgs[] = "No se pudo subir el archivo. Revisa permisos en /img.";
                    }
                }
            }

            if (!$error_msgs) {
                $newcfg = [
                    'title'    => $title,
                    'subtitle' => $subtitle,
                    'logo'     => $logo_path,
                    'timezone' => $timezone, // GUARDAR TZ
                ];
                if (file_put_contents($config_file, json_encode($newcfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    $ok_msgs[] = "Configuración del encabezado guardada correctamente.";
                    $config    = $newcfg;
                } else {
                    $error_msgs[] = "No se pudo escribir el archivo de configuración. Revisa permisos.";
                }
            }

        } elseif ($action === 'save_telegram') {
            // Guardar token y chat_id de Telegram
            $token   = trim($_POST['tg_token'] ?? '');
            $chat_id = trim($_POST['tg_chat_id'] ?? '');
            $invite_link = trim($_POST['tg_invite'] ?? '');

            if ($token === '' || $chat_id === '') {
                $error_msgs[] = "Debes ingresar el Bot Token y el Chat ID.";
            }

            if (!$error_msgs) {
                $payload = ['token' => $token, 'chat_id' => $chat_id, 'invite_link' => $invite_link];
                if (file_put_contents($telegram_file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    @chmod($telegram_file, 0640);
                    $telegram_cfg = $payload;
                    $ok_msgs[] = "Configuración de Telegram guardada.";
                } else {
                    $error_msgs[] = "No se pudo escribir telegram_config.json. Revisa permisos.";
                }
            }

} elseif ($action === 'save_dvref') {
    // Guardar credenciales DVREF
    $token = trim($_POST['dvref_token'] ?? '');
    $host  = trim($_POST['dvref_host'] ?? '');
    $port  = (int)($_POST['dvref_port'] ?? 41000);
    $tg    = (int)($_POST['dvref_tg'] ?? 0);

    if ($token === '' || $host === '' || $tg === 0) {
        $error_msgs[] = "Debes completar todos los campos de DVREF.";
    }

    if (!$error_msgs) {
        $payload = ['token' => $token, 'host' => $host, 'port' => $port, 'tg' => $tg];
        if (file_put_contents($dvref_cfg_file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
            @chmod($dvref_cfg_file, 0640);
            $dvref_cfg = $payload;
            $ok_msgs[] = "Configuración de DVREF guardada.";
        } else {
            $error_msgs[] = "No se pudo escribir dvref_config.json. Revisa permisos.";
        }
    }


 } elseif ($action === 'send_custom_telegram') {
            // Enviar mensaje personalizado al canal
            $msg_title   = trim($_POST['msg_title'] ?? '');
            $msg_details = trim($_POST['msg_details'] ?? '');
            $msg_date    = trim($_POST['msg_date'] ?? '');

            if ($msg_title === '' || $msg_details === '' || $msg_date === '') {
                $error_msgs[] = "Debes completar todos los campos (motivo, detalles y fecha).";
            }

            if (!$error_msgs) {
                include __DIR__ . '/includes/telegram.php';

                $msg = "📡 <b>Evento Radioafición</b>\n\n".
                       "🔹 <b>Motivo:</b> {$msg_title}\n".
                       "📝 <b>Detalles:</b> {$msg_details}\n".
                       "📅 <b>Fecha:</b> {$msg_date}";

                if (telegram_send($msg)) {
                    $ok_msgs[] = "Mensaje enviado correctamente al canal de Telegram.";
                } else {
                    $error_msgs[] = "No se pudo enviar el mensaje. Revisa el token/chat_id.";
                }
            }



        } elseif ($action === 'change_creds') {
            // Cambiar credenciales
            $current_pass = $_POST['current_pass'] ?? '';
            $new_user     = trim($_POST['new_user'] ?? $admin_user);
            $new_pass     = $_POST['new_pass'] ?? '';
            $new_pass2    = $_POST['new_pass2'] ?? '';

            // Verificar clave actual
            if (!password_verify($current_pass, $admin_hash)) {
                $error_msgs[] = "La contraseña actual no es correcta.";
            }

            // Validaciones de cambio
            $changing_user = ($new_user !== '' && $new_user !== $admin_user);
            $changing_pass = ($new_pass !== '');
            if (!$changing_user && !$changing_pass) {
                $error_msgs[] = "No hay cambios que aplicar. Modifica usuario y/o contraseña.";
            }

            if ($changing_pass) {
                if (strlen($new_pass) < 8) $error_msgs[] = "La nueva contraseña debe tener al menos 8 caracteres.";
                if ($new_pass !== $new_pass2) $error_msgs[] = "Las contraseñas nuevas no coinciden.";
            }

            if (!$error_msgs) {
                $to_save_user = $changing_user ? $new_user : $admin_user;
                $to_save_hash = $changing_pass ? password_hash($new_pass, PASSWORD_DEFAULT) : $admin_hash;

                $payload = [
                    'user'      => $to_save_user,
                    'pass_hash' => $to_save_hash
                ];
                if (file_put_contents($auth_file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    @chmod($auth_file, 0640);
                    // refrescar en memoria por si se vuelve a enviar otro POST
                    $admin_user = $to_save_user;
                    $admin_hash = $to_save_hash;
                    $ok_msgs[]  = "Credenciales actualizadas. Presiona <strong>Cerrar sesión</strong> y vuelve a entrar con las nuevas credenciales.";
                } else {
                    $error_msgs[] = "No se pudo actualizar admin_auth.json. Revisa permisos de escritura.";
                }

            }
        }
    }
}
// Acción: Probar Telegram
if (isset($_GET['action']) && $_GET['action'] === 'test_telegram') {
    if (!isset($_GET['csrf']) || $_GET['csrf'] !== $_SESSION['csrf']) {
        $error_msgs[] = "Token inválido. Refresca la página e inténtalo nuevamente.";
    } else {
        include __DIR__ . '/includes/telegram.php';
        if (telegram_send("🔔 Mensaje de prueba desde LYNK25 – configuración OK ✅")) {
            $ok_msgs[] = "Mensaje de prueba enviado correctamente a Telegram.";
        } else {
            $error_msgs[] = "No se pudo enviar el mensaje de prueba. Revisa token/chat_id.";
        }
    }
}



?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Personalizar Header – Lynk25</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
<link rel="icon" type="image/png" href="img/lynk25_favicon.png">


</head>
<body class="dark-mode text-light">

<!-- ====== HEADER ====== -->
<div class="container py-3 flex-grow-1">
  <div class="bg-dark text-white py-0 rounded shadow-sm mb-3">
    <div class="row align-items-center">
      <!-- Izquierda: logo Lynk25 + título/subtítulo -->
      <div class="col-md-8 d-flex align-items-center">
        <img src="img/lynk25logo.png" alt="Lynk25" class="me-3 img-fluid" style="max-height:180px;">
        <div>
          <h3 class="mb-1">
            <i class="fas fa-walkie-talkie text-info"></i>
            <?php echo htmlspecialchars($config['title']); ?>
          </h3>
          <p class="mb-0 fst-italic text-center text-light small">“<?php echo htmlspecialchars($config['subtitle']); ?>”</p>
        </div>
      </div>
      <!-- Derecha: logo ZDMR + herramientas -->
      <div class="col-md-4 text-center mt-1 mt-md-0 d-flex flex-column align-items-center">
        <img src="<?php echo htmlspecialchars($config['logo']); ?>" alt="Grupo Zona DMR" class="img-fluid rounded shadow-sm mb-2" style="max-height: 140px;">
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

          <a href="?logout=1" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="Cerrar sesión" aria-label="Cerrar sesión">
            <i class="fas fa-right-from-bracket"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container pb-4">

  <?php if ($first_run_notice): ?>
    <div class="alert alert-warning border">
      <strong>Primera ejecución:</strong> <?php echo htmlspecialchars($first_run_notice); ?>
    </div>
  <?php endif; ?>

  <?php foreach ($ok_msgs as $m): ?>
    <div class="alert alert-success"><?php echo $m; ?></div>
  <?php endforeach; ?>
  <?php foreach ($error_msgs as $m): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($m); ?></div>
  <?php endforeach; ?>

  <!-- ====== TARJETA: Personalizar encabezado (incluye timezone) ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-pen-to-square me-2"></i>Personalizar encabezado</h5>
      <form method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_header">

        <div class="mb-3">
          <label class="form-label">Título</label>
          <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($config['title']); ?>" required>
          <small class="d-block">Ej: REFLECTOR P25 – ZONA DMR</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Subtítulo</label>
          <input type="text" name="subtitle" class="form-control" value="<?php echo htmlspecialchars($config['subtitle']); ?>" required>
          <small class="d-block">Ej: Conectando amigos, enlazando pasiones por el aire.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Zona horaria del panel</label>
          <select name="timezone" class="form-select" required>
            <?php
              $current_tz = $config['timezone'] ?? 'America/Santiago';
              foreach ($tz_regions_order as $region) {
                if (empty($tz_by_region[$region])) continue;
                echo '<optgroup label="'.htmlspecialchars($region).'">';
                foreach ($tz_by_region[$region] as $tz) {
                    $sel = ($tz === $current_tz) ? ' selected' : '';
                    echo '<option value="'.htmlspecialchars($tz).'"'.$sel.'>'.htmlspecialchars($tz).'</option>';
                }
                echo '</optgroup>';
              }
              // Mostrar cualquier región restante que no esté en el orden preferido
              foreach ($tz_by_region as $region => $arr) {
                if (in_array($region, $tz_regions_order, true)) continue;
                echo '<optgroup label="'.htmlspecialchars($region).'">';
                foreach ($arr as $tz) {
                    $sel = ($tz === $current_tz) ? ' selected' : '';
                    echo '<option value="'.htmlspecialchars($tz).'"'.$sel.'>'.htmlspecialchars($tz).'</option>';
                }
                echo '</optgroup>';
              }
            ?>
          </select>
          <small class="d-block">Ejemplos válidos: <code>America/Santiago</code>, <code>Europe/Madrid</code>, <code>UTC</code>.</small>
          <small class="d-block">Hora local ahora en <strong><?php echo htmlspecialchars($current_tz); ?></strong>: <?php echo htmlspecialchars(tz_preview($current_tz)); ?></small>
        </div>

        <div class="mb-3">
          <label class="form-label">Logo actual</label><br>
          <img src="<?php echo htmlspecialchars($config['logo']); ?>" alt="Logo" class="preview-logo rounded shadow-sm">
          <div class="form-text">Ruta fija: <code>img/zdmrlogoindex.png</code></div>
        </div>

        <div class="mb-3">
          <label class="form-label">Subir nuevo logo (PNG)</label>
          <input type="file" name="logo_file" class="form-control" accept=".png">
          <small class="d-block">Se guardará como <code>img/zdmrlogoindex.png</code></small>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Guardar cambios</button>
          <a class="btn btn-outline-light" href="index.php"><i class="fas fa-eye me-1"></i> Ver en vivo</a>
        </div>
      </form>
    </div>
  </div>

  <!-- ====== TARJETA: Telegram (token y chat_id) ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fab fa-telegram me-2"></i>Configuración de Telegram</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_telegram">

        <div class="mb-3">
          <label class="form-label">Bot Token</label>
          <input type="text" name="tg_token" class="form-control" value="<?php echo htmlspecialchars($telegram_cfg['token']); ?>" placeholder="Ej: 123456:ABC-DEF...">
          <small class="d-block">Crea tu bot con <code>@BotFather</code>.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Chat ID</label>
          <input type="text" name="tg_chat_id" class="form-control" value="<?php echo htmlspecialchars($telegram_cfg['chat_id']); ?>" placeholder="Ej: 123456789 o -1001234567890">
          <small class="d-block">Para grupos suele empezar con <code>-100</code>.</small>
        </div>
	
	<div class="mb-3">
  <label class="form-label">Link de invitación</label>
  <input type="text" name="tg_invite" class="form-control"
         value="<?php echo htmlspecialchars($telegram_cfg['invite_link']); ?>"
         placeholder="Ej: https://t.me/tuCanalPublico">
  <small class="d-block">Puedes poner un link público o privado de tu canal/grupo.</small>
</div>

        <button type="submit" class="btn btn-info"><i class="fas fa-save me-1"></i> Guardar Telegram</button>
	
  <a href="personalizar_header.php?action=test_telegram&csrf=<?php echo htmlspecialchars($_SESSION['csrf']); ?>" 
     class="btn btn-success">
    <i class="fas fa-paper-plane me-1"></i> Probar envío
  </a>

      </form>
    </div>
  </div>



<!-- ====== TARJETA: Configuración DVREF ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-server me-2"></i>Configuración DVREF</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_dvref">

        <div class="mb-3">
          <label class="form-label">Token DVREF</label>
          <input type="text" name="dvref_token" class="form-control"
                 value="<?php echo htmlspecialchars($dvref_cfg['token'] ?? ''); ?>"
                 placeholder="Ej: tu_token_personal">
        </div>

        <div class="mb-3">
          <label class="form-label">Host</label>
          <input type="text" name="dvref_host" class="form-control"
                 value="<?php echo htmlspecialchars($dvref_cfg['host'] ?? ''); ?>"
                 placeholder="Ej: zonadmr2.ddns.net">
        </div>

        <div class="mb-3">
          <label class="form-label">Puerto</label>
          <input type="number" name="dvref_port" class="form-control"
                 value="<?php echo htmlspecialchars($dvref_cfg['port'] ?? 41000); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Talkgroup (TG)</label>
          <input type="number" name="dvref_tg" class="form-control"
                 value="<?php echo htmlspecialchars($dvref_cfg['tg'] ?? 30444); ?>">
        </div>

        <button type="submit" class="btn btn-info">
          <i class="fas fa-save me-1"></i> Guardar DVREF
        </button>
      </form>
    </div>
  </div>




  <!-- ====== TARJETA: Enviar mensaje al canal ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-bullhorn me-2"></i>Enviar mensaje al canal</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="send_custom_telegram">

        <div class="mb-3">
          <label class="form-label">Motivo del evento</label>
          <input type="text" name="msg_title" class="form-control" required placeholder="Ej: Concurso DX, charla técnica...">
        </div>

        <div class="mb-3">
          <label class="form-label">Detalles</label>
          <textarea name="msg_details" class="form-control" rows="3" required placeholder="Describe el evento, horarios, frecuencias..."></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Fecha</label>
          <input type="text" name="msg_date" class="form-control" required placeholder="Ej: 22/09/2025 - 19:00 hrs">
        </div>

        <button type="submit" class="btn btn-success">
          <i class="fab fa-telegram me-1"></i> Enviar al canal
        </button>
      </form>
    </div>
  </div>



  <!-- ====== TARJETA: Cambiar credenciales (usuario/contraseña) ====== -->
  <div class="card bg-dark border-secondary">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-user-shield me-2"></i>Credenciales de administrador</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="change_creds">

        <div class="mb-3">
          <label class="form-label">Contraseña actual</label>
          <input type="password" name="current_pass" class="form-control" required>
          <small class="d-block">Necesaria para confirmar los cambios.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Nuevo usuario (opcional)</label>
          <input type="text" name="new_user" class="form-control" value="<?php echo htmlspecialchars($admin_user); ?>">
          <small class="d-block">Déjalo igual si no quieres cambiar el usuario.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Nueva contraseña (opcional)</label>
          <input type="password" name="new_pass" class="form-control" minlength="8" placeholder="Mínimo 8 caracteres">
        </div>

        <div class="mb-3">
          <label class="form-label">Repite la nueva contraseña</label>
          <input type="password" name="new_pass2" class="form-control" minlength="8">
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i> Actualizar credenciales</button>
          <a href="?logout=1" class="btn btn-outline-light"><i class="fas fa-right-from-bracket me-1"></i> Cerrar sesión</a>
        </div>

        <small class="d-block mt-3">Consejo: usa una contraseña larga y única. Si expones esta página en Internet, publícala solo sobre <strong>HTTPS</strong> y considera restringir por IP en tu servidor.</small>
      </form>
    </div>
  </div>

</div>

<!-- FOOTER -->
<footer class="bg-dark text-white text-center py-3 mt-4">
  🚀 Dashboard web LYNK25 Desarrollado por <strong>Telecoviajero - CA2RDP</strong> |
  <a href="https://github.com/telecov/" target="_blank" class="text-info text-decoration-none">GitHub</a><br>
  © 2025 Telecoviajero - CA2RDP.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

</body>
</html>

