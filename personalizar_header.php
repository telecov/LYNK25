<?php
// lynk25_config.php
// Panel unificado de configuración para Lynk25 (P25Reflector + Red + Telegram + Header + Credenciales)
// Autor: Telecoviajero - CA2RDP

session_start();
date_default_timezone_set('America/Santiago');

/* ==============================
   CONSTANTES BÁSICAS
   ============================== */
$P25_SERVICE = 'p25reflector.service'; // systemd service
$ETH_IF      = 'enp4s0';               // Interfaz Ethernet principal
$WIFI_IF     = 'wlan0';                // Reservado para futuro (no usado aún)

$auth_file      = __DIR__ . '/data/admin_auth.json';
$config_file    = __DIR__ . '/data/header_config.json';
$telegram_file  = __DIR__ . '/includes/telegram_config.json';
$dvref_cfg_file = __DIR__ . '/data/dvref_config.json';
$p25_ini_file   = '/etc/P25Reflector.ini';

$img_dir = __DIR__ . '/img/';

$ok_msgs    = [];
$error_msgs = [];
$first_run_notice = null;

/* ==============================
   CREAR CREDENCIALES DEFAULT SI NO EXISTEN
   ============================== */
if (!file_exists($auth_file)) {
    $default_user = 'admin';
    $default_pass = 'cambia_esto_ya'; // ¡cambiar luego!
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

/* ==============================
   CARGAR CREDENCIALES
   ============================== */
$admin_user = 'admin';
$admin_hash = null;

if (file_exists($auth_file)) {
    $j = @json_decode(@file_get_contents($auth_file), true);
    if (is_array($j)) {
        $admin_user = $j['user']      ?? $admin_user;
        $admin_hash = $j['pass_hash'] ?? null;
    }
}
if (!$admin_hash) {
    // fallback muy básico
    $admin_hash = password_hash('cambia_esto_ya', PASSWORD_DEFAULT);
}

/* ==============================
   LOGOUT
   ============================== */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

/* ==============================
   BASIC AUTH
   ============================== */
function get_basic_credentials(): array {
    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW']   ?? null;

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
   FUNCIONES AUXILIARES
   ============================== */

// Timezone helpers
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

// Construir lista de zonas horarias agrupadas
$tz_regions_order = ['America','Europe','Asia','Africa','Australia','Pacific','Atlantic','Indian','Antarctica','Etc','UTC'];
$tz_by_region = [];
foreach (DateTimeZone::listIdentifiers() as $tz) {
    $region = strtok($tz, '/');
    if ($region === false) $region = $tz;
    if (!isset($tz_by_region[$region])) $tz_by_region[$region] = [];
    $tz_by_region[$region][] = $tz;
}
foreach ($tz_by_region as &$arr) {
    sort($arr, SORT_NATURAL);
}
unset($arr);

// Obtener conexión activa asociada a un dispositivo (nmcli)
function nm_get_connection_for_device(string $dev): ?string {
    $cmd = "nmcli -t -f DEVICE,NAME connection show --active 2>/dev/null";
    $out = trim(@shell_exec($cmd));
    if ($out === '') return null;
    $lines = explode("\n", $out);
    foreach ($lines as $line) {
        if (!$line) continue;
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) continue;
        if ($parts[0] === $dev) return $parts[1];
    }
    return null;
}

/* ==============================
   CARGAR CONFIGURACIÓN HEADER
   ============================== */
$default_header = [
    'title'    => 'REFLECTOR P25 – ZONA DMR',
    'subtitle' => 'Conectando amigos, enlazando pasiones por el aire.',
    'logo'     => 'img/zdmrlogoindex.png',
    'timezone' => 'America/Santiago',
];

$config = $default_header;
if (file_exists($config_file)) {
    $json = @file_get_contents($config_file);
    $tmp  = @json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $config = array_merge($default_header, $tmp);
    }
}

/* ==============================
   CARGAR TELEGRAM
   ============================== */
$telegram_cfg = ['token' => '', 'chat_id' => '', 'invite_link' => ''];
if (file_exists($telegram_file)) {
    $tj = @json_decode(@file_get_contents($telegram_file), true);
    if (is_array($tj)) $telegram_cfg = array_merge($telegram_cfg, $tj);
}

/* ==============================
   CARGAR DVREF
   ============================== */
$dvref_cfg = ['token' => '', 'host' => '', 'port' => 41000, 'tg' => 30444];
if (file_exists($dvref_cfg_file)) {
    $tmp = @json_decode(@file_get_contents($dvref_cfg_file), true);
    if (is_array($tmp)) $dvref_cfg = array_merge($dvref_cfg, $tmp);
}

/* ==============================
   CSRF
   ============================== */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ==============================
   ACCIONES POST
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_header';

    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $error_msgs[] = "Token inválido. Refresca la página e inténtalo nuevamente.";
    } else {
        /* ---------- GUARDAR HEADER + TZ + LOGO ---------- */
        if ($action === 'save_header') {
            $title    = trim($_POST['title']    ?? $config['title']);
            $subtitle = trim($_POST['subtitle'] ?? $config['subtitle']);
            $timezone = trim($_POST['timezone'] ?? $config['timezone']);

            if ($title    === '') $title    = $default_header['title'];
            if ($subtitle === '') $subtitle = $default_header['subtitle'];

            if (!tz_is_valid($timezone)) {
                $error_msgs[] = "Zona horaria inválida. Selecciona una zona IANA válida.";
            }

            $logo_path = $config['logo'];

            // Subida opcional de logo
            if (!empty($_FILES['logo_file']['name'])) {
                if (!is_dir($img_dir)) @mkdir($img_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'png') {
                    $error_msgs[] = "Solo se permite subir imágenes PNG.";
                } else {
                    $dest = $img_dir . 'zdmrlogoindex.png';
                    @unlink($dest);
                    if (!move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                        $error_msgs[] = "No se pudo subir el logo. Revisa permisos de escritura en /img.";
                    } else {
                        $logo_path = 'img/zdmrlogoindex.png';
                    }
                }
            }

            if (!$error_msgs) {
                $newcfg = [
                    'title'    => $title,
                    'subtitle' => $subtitle,
                    'logo'     => $logo_path,
                    'timezone' => $timezone,
                ];
                if (@file_put_contents($config_file, json_encode($newcfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    $config  = $newcfg;
                    $ok_msgs[] = "Configuración del encabezado guardada correctamente.";
                } else {
                    $error_msgs[] = "No se pudo escribir header_config.json. Revisa permisos.";
                }
            }

        /* ---------- GUARDAR TELEGRAM ---------- */
        } elseif ($action === 'save_telegram') {
            $token   = trim($_POST['tg_token']   ?? '');
            $chat_id = trim($_POST['tg_chat_id'] ?? '');
            $invite  = trim($_POST['tg_invite']  ?? '');

            if ($token === '' || $chat_id === '') {
                $error_msgs[] = "Debes ingresar Bot Token y Chat ID.";
            } else {
                $payload = ['token' => $token, 'chat_id' => $chat_id, 'invite_link' => $invite];
                if (@file_put_contents($telegram_file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    @chmod($telegram_file, 0640);
                    $telegram_cfg = $payload;
                    $ok_msgs[] = "Configuración de Telegram guardada correctamente.";
                } else {
                    $error_msgs[] = "No se pudo escribir telegram_config.json. Revisa permisos.";
                }
            }

        /* ---------- GUARDAR DVREF ---------- */
        } elseif ($action === 'save_dvref') {
            $token = trim($_POST['dvref_token'] ?? '');
            $host  = trim($_POST['dvref_host']  ?? '');
            $port  = (int)($_POST['dvref_port'] ?? 41000);
            $tg    = (int)($_POST['dvref_tg']   ?? 0);

            if ($token === '' || $host === '' || $tg === 0) {
                $error_msgs[] = "Debes completar todos los campos de DVREF.";
            } else {
                $payload = ['token' => $token, 'host' => $host, 'port' => $port, 'tg' => $tg];
                if (@file_put_contents($dvref_cfg_file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    @chmod($dvref_cfg_file, 0640);
                    $dvref_cfg = $payload;
                    $ok_msgs[] = "Configuración de DVREF guardada correctamente.";
                } else {
                    $error_msgs[] = "No se pudo escribir dvref_config.json. Revisa permisos.";
                }
            }

        /* ---------- ENVIAR MENSAJE CUSTOM A TELEGRAM ---------- */
        } elseif ($action === 'send_custom_telegram') {
            $msg_title   = trim($_POST['msg_title']   ?? '');
            $msg_details = trim($_POST['msg_details'] ?? '');
            $msg_date    = trim($_POST['msg_date']    ?? '');

            if ($msg_title === '' || $msg_details === '' || $msg_date === '') {
                $error_msgs[] = "Completa motivo, detalles y fecha para el evento.";
            } else {
                include __DIR__ . '/includes/telegram.php';

                $msg = "📡 <b>Evento Radioafición</b>\n\n" .
                       "🔹 <b>Motivo:</b> {$msg_title}\n" .
                       "📝 <b>Detalles:</b> {$msg_details}\n" .
                       "📅 <b>Fecha:</b> {$msg_date}";

                if (telegram_send($msg)) {
                    $ok_msgs[] = "Mensaje enviado correctamente al canal de Telegram.";
                } else {
                    $error_msgs[] = "No se pudo enviar el mensaje. Revisa token/chat_id.";
                }
            }

        /* ---------- CAMBIAR CREDENCIALES ---------- */
        } elseif ($action === 'change_creds') {
            $current_pass = $_POST['current_pass'] ?? '';
            $new_user     = trim($_POST['new_user'] ?? $admin_user);
            $new_pass     = $_POST['new_pass']  ?? '';
            $new_pass2    = $_POST['new_pass2'] ?? '';

            if (!password_verify($current_pass, $admin_hash)) {
                $error_msgs[] = "La contraseña actual no es correcta.";
            }

            $changing_user = ($new_user !== '' && $new_user !== $admin_user);
            $changing_pass = ($new_pass !== '');

            if (!$changing_user && !$changing_pass) {
                $error_msgs[] = "No hay cambios que aplicar. Modifica usuario y/o contraseña.";
            }

            if ($changing_pass) {
                if (strlen($new_pass) < 8) {
                    $error_msgs[] = "La nueva contraseña debe tener al menos 8 caracteres.";
                }
                if ($new_pass !== $new_pass2) {
                    $error_msgs[] = "Las contraseñas nuevas no coinciden.";
                }
            }

            if (!$error_msgs) {
                $to_save_user = $changing_user ? $new_user : $admin_user;
                $to_save_hash = $changing_pass ? password_hash($new_pass, PASSWORD_DEFAULT) : $admin_hash;

                $payload = [
                    'user'      => $to_save_user,
                    'pass_hash' => $to_save_hash,
                ];

                if (@file_put_contents($auth_file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                    @chmod($auth_file, 0640);
                    $admin_user = $to_save_user;
                    $admin_hash = $to_save_hash;
                    $ok_msgs[]  = "Credenciales actualizadas. Cierra sesión y vuelve a entrar con los nuevos datos.";
                } else {
                    $error_msgs[] = "No se pudo actualizar admin_auth.json. Revisa permisos de escritura.";
                }
            }

        /* ---------- GUARDAR P25Reflector.ini ---------- */
        } elseif ($action === 'save_p25_ini') {
            $nuevo_ini = $_POST['p25_ini'] ?? '';

            if (trim($nuevo_ini) === '') {
                $error_msgs[] = "El archivo P25Reflector.ini no puede quedar vacío.";
            } else {
                // Backup simple
                if (file_exists($p25_ini_file)) {
                    @copy($p25_ini_file, $p25_ini_file . '.bak');
                }
                if (@file_put_contents($p25_ini_file, $nuevo_ini, LOCK_EX) !== false) {
                    $ok_msgs[] = "P25Reflector.ini actualizado correctamente (se hizo backup .bak).";
                } else {
                    $error_msgs[] = "No se pudo escribir P25Reflector.ini. Revisa permisos en /etc.";
                }
            }

        /* ---------- CONTROL DEL SERVICIO P25 ---------- */
        } elseif ($action === 'p25_service') {
            $cmd = $_POST['cmd'] ?? '';

            switch ($cmd) {
                case 'start':
                    @shell_exec("sudo systemctl start " . escapeshellarg($P25_SERVICE));
                    $ok_msgs[] = "Servicio P25Reflector iniciado.";
                    break;
                case 'stop':
                    @shell_exec("sudo systemctl stop " . escapeshellarg($P25_SERVICE));
                    $ok_msgs[] = "Servicio P25Reflector detenido.";
                    break;
                case 'restart':
                    @shell_exec("sudo systemctl restart " . escapeshellarg($P25_SERVICE));
                    $ok_msgs[] = "Servicio P25Reflector reiniciado.";
                    break;
                case 'status':
                    $output = @shell_exec("systemctl status " . escapeshellarg($P25_SERVICE) . " --no-pager 2>&1");
                    $ok_msgs[] = "<pre>" . htmlspecialchars($output) . "</pre>";
                    break;
                case 'reboot':
                    @shell_exec("sudo reboot");
                    $ok_msgs[] = "Orden de reinicio enviada al servidor.";
                    break;
            }

        /* ---------- CONFIGURAR IP ETHERNET (nmcli) ---------- */
        } elseif ($action === 'net_eth') {
            $ip  = trim($_POST['new_ip_eth'] ?? '');
            $gw  = trim($_POST['new_gw_eth'] ?? '');
            $dns = trim($_POST['dns_eth']    ?? '');

            if ($ip === '' && $gw === '' && $dns === '') {
                $error_msgs[] = "No ingresaste ningún cambio para Ethernet.";
            } else {
                $conn = nm_get_connection_for_device($ETH_IF);
                if (!$conn) {
                    // fallback
                    $conn = 'Wired connection 1';
                }

                $conn_esc = escapeshellarg($conn);
                $out = "";

                if ($ip !== '') {
                    $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.addresses " . escapeshellarg($ip) . " 2>&1");
                    $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.method manual 2>&1");
                }

                if ($gw !== '') {
                    $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.gateway " . escapeshellarg($gw) . " 2>&1");
                }

                if ($dns !== '') {
                    $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.dns " . escapeshellarg($dns) . " 2>&1");
                }

                $out .= @shell_exec("sudo nmcli con up $conn_esc 2>&1");

                $ok_msgs[] = "Configuración Ethernet actualizada.<br><pre>" . htmlspecialchars($out) . "</pre>";
            }

        /* ---------- CAMBIAR HOSTNAME ---------- */
        } elseif ($action === 'change_hostname') {
            $new_host = trim($_POST['new_hostname'] ?? '');

            if ($new_host === '') {
                $error_msgs[] = "Debes ingresar un hostname.";
            } elseif (!preg_match('/^[a-zA-Z0-9\-]{1,63}$/', $new_host)) {
                $error_msgs[] = "Hostname inválido. Usa solo letras, números y guiones (máx 63).";
            } else {
                $cmd = "sudo hostnamectl set-hostname " . escapeshellarg($new_host) . " 2>&1";
                $out = @shell_exec($cmd);
                $ok_msgs[] = "Hostname actualizado a <strong>" . htmlspecialchars($new_host) . "</strong>.<br><pre>" . htmlspecialchars($out) . "</pre>";
            }
        }
    }
}

/* ==============================
   ACCIÓN GET: PROBAR TELEGRAM
   ============================== */
if (isset($_GET['action']) && $_GET['action'] === 'test_telegram') {
    if (!isset($_GET['csrf']) || $_GET['csrf'] !== $_SESSION['csrf']) {
        $error_msgs[] = "Token inválido al probar Telegram.";
    } else {
        include __DIR__ . '/includes/telegram.php';
        if (telegram_send("🔔 Mensaje de prueba desde LYNK25 – configuración OK ✅")) {
            $ok_msgs[] = "Mensaje de prueba enviado correctamente a Telegram.";
        } else {
            $error_msgs[] = "No se pudo enviar el mensaje de prueba. Revisa token/chat_id.";
        }
    }
}

/* ==============================
   DATOS DINÁMICOS PARA MOSTRAR
   ============================== */

// timezone actual
$current_tz = $config['timezone'] ?? 'America/Santiago';

// IP actual Ethernet vía nmcli
$ip_eth = trim(@shell_exec("nmcli -t -f IP4.ADDRESS device show " . escapeshellarg($ETH_IF) . " 2>/dev/null | head -n 1 | cut -d':' -f2"));
$gw_eth = trim(@shell_exec("nmcli -t -f IP4.GATEWAY device show " . escapeshellarg($ETH_IF) . " 2>/dev/null | head -n 1 | cut -d':' -f2"));

// Hostname actual
$current_hostname = gethostname();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configuración Lynk25 – Panel Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
<link rel="icon" type="image/png" href="img/lynk25_favicon.png">
</head>
<body class="dark-mode text-light">

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
          <p class="mb-0 fst-italic text-center text-light small">
            “<?php echo htmlspecialchars($config['subtitle']); ?>”
          </p>
          </div>
      </div>
      <!-- Derecha: logo ZDMR + herramientas -->
      <div class="col-md-4 text-center mt-1 mt-md-0 d-flex flex-column align-items-center">
        <img src="<?php echo htmlspecialchars($config['logo']); ?>" alt="Grupo Zona DMR"
             class="img-fluid rounded shadow-sm mb-2" style="max-height: 140px;">
        <div class="d-flex flex-wrap justify-content-center header-tools">
          <a href="index.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="Ir al Dashboard" aria-label="Dashboard">
            <i class="fas fa-house"></i>
          </a>
          <a href="lynk25_config.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="Panel de configuración" aria-label="Config">
            <i class="fas fa-sliders-h"></i>
          </a>
          <a href="about.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="About Lynk25" aria-label="About">
            <i class="fas fa-circle-info"></i>
          </a>
          <a href="?logout=1" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
             title="Cerrar sesión" aria-label="Cerrar sesión">
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

  <!-- ====== TARJETA: Personalizar encabezado ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-pen-to-square me-2"></i>Personalizar encabezado</h5>
      <form method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_header">

        <div class="mb-3">
          <label class="form-label">Título</label>
          <input type="text" name="title" class="form-control"
                 value="<?php echo htmlspecialchars($config['title']); ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Subtítulo</label>
          <input type="text" name="subtitle" class="form-control"
                 value="<?php echo htmlspecialchars($config['subtitle']); ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Zona horaria del panel</label>
          <select name="timezone" class="form-select" required>
            <?php
              foreach ($tz_regions_order as $region) {
                if (empty($tz_by_region[$region])) continue;
                echo '<optgroup label="'.htmlspecialchars($region).'">';
                foreach ($tz_by_region[$region] as $tz) {
                    $sel = ($tz === $current_tz) ? ' selected' : '';
                    echo '<option value="'.htmlspecialchars($tz).'"'.$sel.'>'.htmlspecialchars($tz).'</option>';
                }
                echo '</optgroup>';
              }
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
        </div>

        <div class="mb-3">
          <label class="form-label">Logo actual</label><br>
          <img src="<?php echo htmlspecialchars($config['logo']); ?>" alt="Logo"
               class="preview-logo rounded shadow-sm">
          <div class="form-text">Ruta fija: <code>img/zdmrlogoindex.png</code></div>
        </div>

        <div class="mb-3">
          <label class="form-label">Subir nuevo logo (PNG)</label>
          <input type="file" name="logo_file" class="form-control" accept=".png">
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Guardar cambios</button>
          <a class="btn btn-outline-light" href="index.php"><i class="fas fa-eye me-1"></i> Ver en vivo</a>
        </div>
      </form>
    </div>
  </div>

  <!-- ====== TARJETA: Configuración P25Reflector.ini ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-file-code me-2"></i>Configuración P25Reflector.ini</h5>
      <?php
        $ini_txt = '';
        if (file_exists($p25_ini_file)) {
            $ini_txt = htmlspecialchars(@file_get_contents($p25_ini_file));
        }
      ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_p25_ini">
        <textarea name="p25_ini" class="form-control bg-dark text-light"
                  rows="10" style="font-family: monospace;"><?php echo $ini_txt; ?></textarea>
        <small class="d-block text-muted mt-1">
          Se guarda en <code>/etc/P25Reflector.ini</code>. Antes de aplicar, se crea un backup <code>.bak</code>.
        </small>
        <button type="submit" class="btn btn-warning mt-3">
          <i class="fas fa-save me-1"></i> Guardar configuración
        </button>
      </form>
    </div>
  </div>

  <!-- ====== TARJETA: Control del servicio P25 ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-server me-2"></i>Control del Servicio P25</h5>
      <form method="post" class="d-flex flex-wrap gap-2">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="p25_service">

        <button name="cmd" value="start"   class="btn btn-success btn-sm"><i class="fas fa-play me-1"></i> Iniciar</button>
        <button name="cmd" value="stop"    class="btn btn-danger btn-sm"><i class="fas fa-stop me-1"></i> Detener</button>
        <button name="cmd" value="restart" class="btn btn-warning btn-sm"><i class="fas fa-rotate me-1"></i> Reiniciar</button>
        <button name="cmd" value="status"  class="btn btn-info btn-sm"><i class="fas fa-info-circle me-1"></i> Estado</button>
        <button name="cmd" value="reboot"  class="btn btn-secondary btn-sm"
                onclick="return confirm('¿Seguro que deseas reiniciar el servidor completo?');">
          <i class="fas fa-power-off me-1"></i> Reiniciar servidor
        </button>
      </form>
      <small class="d-block mt-2 text-muted">
        Ten en cuenta que estas acciones requieren permisos sudo para <code>p25reflector.service</code> y <code>reboot</code>.
      </small>
    </div>
  </div>

  <!-- ====== TARJETA: Configuración de Red – Ethernet ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-network-wired me-2"></i>IP Ethernet (<?php echo htmlspecialchars($ETH_IF); ?>)</h5>

      <div class="mb-3">
        <label class="form-label">IP actual</label>
        <div class="form-control bg-secondary text-white">
          <?php echo $ip_eth ? htmlspecialchars($ip_eth) : 'Sin IP'; ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Gateway actual</label>
        <div class="form-control bg-secondary text-white">
          <?php echo $gw_eth ? htmlspecialchars($gw_eth) : 'Sin gateway'; ?>
        </div>
      </div>

      <form method="post" autocomplete="off"
            onsubmit="return confirm('Al cambiar IP/gateway podrías perder conexión remota. ¿Continuar?');">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="net_eth">

        <div class="mb-3">
          <label class="form-label">Nueva IP (ej: 192.168.1.50/24)</label>
          <input type="text" name="new_ip_eth" class="form-control" placeholder="Dejar vacío si no quieres cambiarla">
        </div>

        <div class="mb-3">
          <label class="form-label">Nuevo gateway (ej: 192.168.1.1)</label>
          <input type="text" name="new_gw_eth" class="form-control" placeholder="Dejar vacío si no quieres cambiarlo">
        </div>

        <div class="mb-3">
          <label class="form-label">DNS (opcional, ej: 8.8.8.8,1.1.1.1)</label>
          <input type="text" name="dns_eth" class="form-control" placeholder="Dejar vacío si no quieres cambiarlo">
        </div>

        <button type="submit" class="btn btn-warning">
          <i class="fas fa-save me-1"></i> Aplicar cambios
        </button>
      </form>

      <small class="d-block mt-2 text-muted">
        Usa IP fija. No se ofrece opción DHCP por diseño (según tu decisión).
      </small>
    </div>
  </div>

  <!-- ====== TARJETA: Cambio de hostname ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-server me-2"></i>Hostname del servidor</h5>

      <div class="mb-3">
        <label class="form-label">Hostname actual</label>
        <div class="form-control bg-secondary text-white">
          <?php echo htmlspecialchars($current_hostname); ?>
        </div>
      </div>

      <form method="post" autocomplete="off"
            onsubmit="return confirm('Cambiar el hostname puede requerir reinicio de servicios/sesiones. ¿Continuar?');">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="change_hostname">

        <div class="mb-3">
          <label class="form-label">Nuevo hostname</label>
          <input type="text" name="new_hostname" class="form-control"
                 placeholder="Ej: LYNK25, lynk25-server, reflector-p25">
        </div>

        <button type="submit" class="btn btn-info">
          <i class="fas fa-save me-1"></i> Cambiar hostname
        </button>
      </form>
      <small class="d-block mt-2 text-muted">
        Asegúrate de que el nuevo hostname sea coherente con tu DNS/Red si lo usas externamente.
      </small>
    </div>
  </div>

  <!-- ====== TARJETA: Configuración de Telegram ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fab fa-telegram me-2"></i>Configuración de Telegram</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_telegram">

        <div class="mb-3">
          <label class="form-label">Bot Token</label>
          <input type="text" name="tg_token" class="form-control"
                 value="<?php echo htmlspecialchars($telegram_cfg['token']); ?>"
                 placeholder="Ej: 123456:ABC-DEF...">
        </div>

        <div class="mb-3">
          <label class="form-label">Chat ID</label>
          <input type="text" name="tg_chat_id" class="form-control"
                 value="<?php echo htmlspecialchars($telegram_cfg['chat_id']); ?>"
                 placeholder="Ej: 123456789 o -1001234567890">
        </div>

        <div class="mb-3">
          <label class="form-label">Link de invitación (opcional)</label>
          <input type="text" name="tg_invite" class="form-control"
                 value="<?php echo htmlspecialchars($telegram_cfg['invite_link']); ?>"
                 placeholder="Ej: https://t.me/tuCanal">
        </div>

        <button type="submit" class="btn btn-info"><i class="fas fa-save me-1"></i> Guardar Telegram</button>
        <a href="lynk25_config.php?action=test_telegram&csrf=<?php echo htmlspecialchars($_SESSION['csrf']); ?>"
           class="btn btn-success">
          <i class="fas fa-paper-plane me-1"></i> Probar envío
        </a>
      </form>
    </div>
  </div>

  <!-- ====== TARJETA: Configuración DVREF ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-plug me-2"></i>Configuración DVREF</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="save_dvref">

        <div class="mb-3">
          <label class="form-label">Token DVREF</label>
          <input type="text" name="dvref_token" class="form-control"
                 value="<?php echo htmlspecialchars($dvref_cfg['token'] ?? ''); ?>"
                 placeholder="Ej: token_personal">
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
          <input type="text" name="msg_title" class="form-control" required
                 placeholder="Ej: Concurso DX, charla técnica...">
        </div>

        <div class="mb-3">
          <label class="form-label">Detalles</label>
          <textarea name="msg_details" class="form-control" rows="3" required
                    placeholder="Describe el evento, horarios, frecuencias..."></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Fecha</label>
          <input type="text" name="msg_date" class="form-control" required
                 placeholder="Ej: 22/09/2025 - 19:00 hrs">
        </div>

        <button type="submit" class="btn btn-success">
          <i class="fab fa-telegram me-1"></i> Enviar al canal
        </button>
      </form>
    </div>
  </div>

  <!-- ====== TARJETA: Credenciales de administrador ====== -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="fas fa-user-shield me-2"></i>Credenciales de administrador</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
        <input type="hidden" name="action" value="change_creds">

        <div class="mb-3">
          <label class="form-label">Contraseña actual</label>
          <input type="password" name="current_pass" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Nuevo usuario (opcional)</label>
          <input type="text" name="new_user" class="form-control"
                 value="<?php echo htmlspecialchars($admin_user); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Nueva contraseña (opcional)</label>
          <input type="password" name="new_pass" class="form-control" minlength="8"
                 placeholder="Mínimo 8 caracteres">
        </div>

        <div class="mb-3">
          <label class="form-label">Repite la nueva contraseña</label>
          <input type="password" name="new_pass2" class="form-control" minlength="8">
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i> Actualizar credenciales</button>
          <a href="?logout=1" class="btn btn-outline-light"><i class="fas fa-right-from-bracket me-1"></i> Cerrar sesión</a>
        </div>

        <small class="d-block mt-3">
          Consejo: usa una contraseña larga y única. Expón este panel solo por HTTPS y, si puedes, restringe acceso por IP en el servidor.
        </small>
      </form>
    </div>
  </div>

</div>

<footer class="bg-dark text-white text-center py-3 mt-4">
  🚀 Dashboard web LYNK25 – Desarrollado por <strong>Telecoviajero - CA2RDP</strong> |
  <a href="https://github.com/telecov/" target="_blank" class="text-info text-decoration-none">GitHub</a><br>
  © 2025 Telecoviajero - CA2RDP. Todos los derechos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>


