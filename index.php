<?php
/* ===========================================================
   LYNK25 - index.php  (TZ configurable + RadioID+QRZ + AJAX 1s)
   =========================================================== */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/metrics.php';
require __DIR__ . '/includes/radioid.php';
require __DIR__ . '/includes/timezone.php';
require __DIR__ . '/includes/logs.php';

require __DIR__ . '/includes/telegram.php';

/* ===========================
   IDIOMA (LYNK25)
   =========================== */
$configFile = __DIR__ . '/data/header_config.json';
$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

$idioma = $config['idioma'] ?? 'es';

$langFile = __DIR__ . "/data/lang/{$idioma}.json";

// Fallback seguro a español
$lang = file_exists($langFile)
    ? json_decode(file_get_contents($langFile), true)
    : json_decode(file_get_contents(__DIR__ . "/data/lang/es.json"), true);

function __($key) {
    global $lang;
    return $lang[$key] ?? $key;
}

/* ===========================
   OCULTAR IP
   =========================== */


function mask_ip_last2(string $ip): string {
    $ip = trim($ip);

    // Solo IPv4 (x.x.x.x)
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $ip !== '' ? $ip : 'N/D';
    }

    $parts = explode('.', $ip);
    return $parts[0] . '.' . $parts[1] . '.x.x';
}


/* ===========================
   VERSION DEL SISTEMA
   =========================== */
$version_file = __DIR__ . '/includes/version.json';
$system_version = 'N/D';

if (file_exists($version_file)) {
    $v = json_decode(file_get_contents($version_file), true);
    if (is_array($v) && !empty($v['version'])) {
        $system_version = $v['version'];
    }
}


$telegram_file = __DIR__ . '/includes/telegram_config.json';
$telegram_cfg  = ['invite_link' => '']; // valor por defecto

if (file_exists($telegram_file)) {
    $tj = @json_decode(@file_get_contents($telegram_file), true);
    if (is_array($tj)) {
        $telegram_cfg = array_merge($telegram_cfg, $tj);
    }
}

$json_file        = __DIR__ . '/data/estado_reflector.json';
$inicio_p25       = 'Desconocido';
$puerto_reflector = 'No detectado';
$dvref = dvref_status_check();
$ultimo_warning   = 'Sin alertas recientes';

if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
    $inicio_p25       = $data['inicio_p25']  ?? $inicio_p25;
    $puerto_reflector = $data['puerto_udp']  ?? $puerto_reflector;
    $ultimo_warning   = htmlentities($data['ultimo_warning'] ?? $ultimo_warning);
}

$log_dir   = "/var/log/p25reflector/";
$log_files = glob($log_dir . "P25Reflector-*.log");

if (!empty($log_files)) {
    sort($log_files, SORT_NATURAL);
    $log_file = end($log_files);
} else {

    $log_file = $log_dir . "P25Reflector.log";
}


$log_lines = file_exists($log_file) ? file($log_file) : [];

$ultimas_lineas = file_exists($log_file) ? tailLog($log_file, 5) : [];


$estado_reflector = "INACTIVO";
exec("pgrep -f P25Reflector", $output);
if (count($output) > 0) $estado_reflector = "ACTIVO";


$trafico_actual        = "Sin actividad";
$trafico_historico     = [];
$trafico_actual_nombre = null;
$en_transmision        = false;
$uptime                = shell_exec("uptime -p");
$ip_publica_raw = trim(@shell_exec("curl -s ifconfig.me"));
$ip_publica     = mask_ip_last2($ip_publica_raw);





$trafico_actual        = "Sin actividad";
$trafico_actual_nombre = null;
$trafico_actual_loc    = null;
$trafico_actual_tg     = null;
$trafico_inicio_ts     = null;
$trafico_actual_id     = null;
$en_transmision        = false;

foreach ($log_lines as $line) {

    if (preg_match('/Transmission started from\s+([A-Z0-9]+)/', $line, $m)) {
        $trafico_actual_nombre = $m[1];
        $trafico_inicio_ts     = ts_from_utc(substr($line, 3, 19));
        $en_transmision        = true;
    }


    if (preg_match('/Transmission from (.+?) at\s+([A-Z0-9]+)\s+to TG\s+(\d+)/', $line, $m)) {
        $real_callsign     = trim($m[1]);
        $via_bridge        = $m[2];
        $trafico_actual_tg = $m[3];

        $real_id = radioid_lookup_id($real_callsign);


        if (!empty($real_callsign)) {
            $trafico_actual_nombre = $real_callsign;
            $trafico_actual_loc    = "via " . $via_bridge;
            $trafico_actual_id     = $real_id;
        } else {

            $trafico_actual_loc = $via_bridge;
        }
    }


    if (strpos($line, "Received end of transmission") !== false && $en_transmision) {
        $fin_ts  = ts_from_utc(substr($line, 3, 19));
        $duracion = $fin_ts - $trafico_inicio_ts;

        $trafico_historico[] = [
            'hora'       => fmt_local(substr($line, 3, 19)),
            'ts_inicio'  => $trafico_inicio_ts,
            'indicativo' => $trafico_actual_nombre,
            'id'         => $trafico_actual_id ?? null,
            'loc'        => $trafico_actual_loc,
            'tg'         => $trafico_actual_tg,
            'duracion'   => $duracion
        ];


        $trafico_actual        = "Sin actividad";
        $trafico_actual_nombre = null;
        $trafico_actual_loc    = null;
        $trafico_actual_tg     = null;
        $trafico_inicio_ts     = null;
        $trafico_actual_id     = null;
        $en_transmision        = false;
    }
}

$cache_estaciones = __DIR__ . '/data/estaciones_cache.json';
$estaciones = [];

if (file_exists($cache_estaciones) && (time() - filemtime($cache_estaciones)) < 120) {

    $estaciones = json_decode(file_get_contents($cache_estaciones), true);
} else {

    $estaciones_json = shell_exec('php ' . __DIR__ . '/includes/cache_estaciones.php');
    $estaciones = json_decode($estaciones_json, true);
}


require __DIR__ . '/includes/heard.php';



[$alert_ts, $alert_msg] = parseAlerta($ultimo_warning);
$alert_class = claseAlerta($alert_msg, $estado_reflector);
$alert_rel   = $alert_ts ? (' · ' . tiempoRelativoTS($alert_ts)) : '';

list($load1, $load5, $load15) = loadAverages();
$cpu_perc   = cpuLoadPercent();
list($mem, $swap) = memInfoMB();
$mem_total  = max(1, (int)$mem['total']);
$mem_used   = (int)$mem['used'];
$mem_avail  = (int)$mem['avail'];
$mem_perc   = min(100, max(0, round(($mem_used / $mem_total) * 100)));
$swap_total = max(0, (int)$swap['total']);
$swap_used  = (int)$swap['used'];
$swap_perc  = $swap_total > 0 ? min(100, max(0, round(($swap_used / $swap_total) * 100))) : 0;
$disk       = diskRootHuman();
$disk_usep  = (int)$disk['usep'];
$temp_c     = temperatureC();
$os_version = osVersion();



if (isset($_GET['ajax']) && $_GET['ajax'] === 'trafico') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');




    $n = count($log_lines);
    if ($n === 0) {
        echo json_encode([
            'active'     => false,
            'callsign'   => null,
            'id'         => null,
            'tg'         => null,
            'hora'       => null,
            'name'       => null,
            'qrz'        => null,
            'started_ts' => null,
            'started_at' => null,
	    'log_ts' => filemtime($log_file) ?: null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }


    $re_start  = '/Transmission started from\s+([A-Z0-9]+)/';
    $re_detail = '/Transmission from (.+?) at\s+([A-Z0-9]+)\s+to TG\s+(\d+)/';
    $re_end = '/(end of transmission|transmission ended|end tx|tx end|transmission stop|end transmission)/i';



    $last_start_idx = -1;
    $last_start_call = null;
    $last_start_utc = null;
    $last_end_idx   = -1;
    $last_end_utc    = null;

    for ($i = $n - 1; $i >= 0; $i--) {
        $ln = $log_lines[$i];

        if ($last_start_idx === -1 && preg_match($re_start, $ln, $m)) {
            $last_start_idx  = $i;
            $last_start_call = $m[1];
            $last_start_utc  = substr($ln, 3, 19);
        }

        if ($last_end_idx === -1 && preg_match($re_end, $ln)) {
            $last_end_idx = $i;
            $last_end_utc = substr($ln, 3, 19);
        }

        if ($last_start_idx !== -1 && $last_end_idx !== -1) break;
    }


    $active = ($last_start_idx !== -1) && ($last_end_idx === -1 || $last_start_idx > $last_end_idx);


    if ($active && $last_start_utc) {
        $age = time() - ts_from_utc($last_start_utc);
        if ($age > 180) {
            $active = false;
        }
    }


    $busca_detalles = function ($from, $to) use ($log_lines, $re_detail) {
        $det = ['real' => null, 'via' => null, 'tg' => null];
        for ($j = $from; $j <= $to; $j++) {
            $ln = $log_lines[$j];
            if (preg_match($re_detail, $ln, $mm)) {
                $det['real'] = trim($mm[1]);
                $det['via']  = $mm[2];
                $det['tg']   = $mm[3];
            }
        }
        return $det;
    };

    if ($active) {

        $det        = $busca_detalles($last_start_idx, $n - 1);
        $callsign   = $det['real'] ?: $last_start_call;
        $tg         = $det['tg'] ?? null;
        $started_ts = $last_start_utc ? ts_from_utc($last_start_utc) : null;
        $started_at = $last_start_utc ? fmt_local($last_start_utc)  : null;
        $qrz        = $callsign ? ('https://www.qrz.com/db/' . rawurlencode($callsign)) : null;

	$idNum = $callsign ? radioid_lookup_id($callsign) : null;
	$name  = $callsign ? radioid_lookup_name($callsign, $idNum) : null;



        echo json_encode([
            'active'     => true,
            'callsign'   => $callsign,
            'id'         => $idNum,
            'tg'         => $tg,
            'hora'       => $started_at,
            'name'       => $name,
            'qrz'        => $qrz,
            'started_ts' => $started_ts,
            'started_at' => $started_at,
	    'log_ts'     => filemtime($log_file) ?: null        
], JSON_UNESCAPED_UNICODE);
        exit;
    } else {

        if ($last_end_idx === -1) {
            echo json_encode([
                'active'     => false,
                'callsign'   => null,
                'id'         => null,
                'tg'         => null,
                'hora'       => null,
                'name'       => null,
                'qrz'        => null,
                'started_ts' => null,
                'started_at' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }


        $prev_start_idx = -1;
        $prev_start_call = null;
        $prev_start_utc = null;
        for ($i = $last_end_idx; $i >= 0; $i--) {
            $ln = $log_lines[$i];
            if (preg_match($re_start, $ln, $m)) {
                $prev_start_idx  = $i;
                $prev_start_call = $m[1];
                $prev_start_utc  = substr($ln, 3, 19);
                break;
            }
        }


        $callsign = $prev_start_call;
        $tg = null;
        if ($prev_start_idx !== -1) {
            $det = $busca_detalles($prev_start_idx, $last_end_idx);
            if (!empty($det['real'])) $callsign = $det['real'];
            if (!empty($det['tg']))   $tg       = $det['tg'];
        }

        $idNum = $callsign ? radioid_lookup_id($callsign) : null;
	$name  = $callsign ? radioid_lookup_name($callsign, $idNum) : null;


        $qrz   = $callsign ? ('https://www.qrz.com/db/' . rawurlencode($callsign)) : null;

        $hora_local_end = $last_end_utc ? fmt_local($last_end_utc) : null;

        echo json_encode([
            'active'     => false,
            'callsign'   => $callsign,
            'id'         => $idNum,
            'tg'         => $tg,
            'hora'       => $hora_local_end,
            'name'       => $name,
            'qrz'        => $qrz,
            'started_ts' => null,
            'started_at' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= __('dashboard_title') ?></title>
    <meta http-equiv="refresh" content="120">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <link href="css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="img/lynk25_favicon.png">

</head>

<body class="d-flex flex-column min-vh-100">
    <div class="container py-3 flex-grow-1">
        <!-- HEADER -->
        <div class="bg-dark text-white py-1 rounded shadow-sm mb-3">
            <div class="row align-items-center">
                <!-- Izquierda: título y subtítulo -->
                <div class="col-md-8 d-flex align-items-center">
                    <img src="img/lynk25logo.png" alt="Lynk25" class="me-3 img-fluid" style="max-height:180px;">
                    <div>
                        <h3 class="mb-1">
                            <i class="fas fa-walkie-talkie text-info"></i> <?php echo htmlspecialchars($header_title); ?>
                        </h3>
                        <p class="mb-0 fst-italic text-center text-light small">
                            “<?php echo htmlspecialchars($header_subtitle); ?>”
                        </p>
                    </div>
                </div>
                <!-- Derecha: logo ZDMR + botones debajo -->
                <div class="col-md-4 text-center mt-1 mt-md-0 d-flex flex-column align-items-center">
                    <img src="<?php echo htmlspecialchars($header_logo); ?>" alt="Grupo Zona DMR"
                        class="img-fluid rounded shadow-sm mb-2" style="max-height: 140px;">
                    <div class="d-flex flex-wrap justify-content-center header-tools">
                        <a href="index.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="<?= __('go_dashboard') ?>" aria-label="Dashboard">
                            <i class="fas fa-house"></i>
                        </a>
                        <a href="personalizar_header.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="Personalizar" aria-label="Personalizar">
                            <i class="fas fa-pen"></i>
                        </a>
                        <a href="about.php" class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip" title="About LYNK25" aria-label="About">
                            <i class="fas fa-circle-info"></i>
                        </a>
                        
                        <?php if (!empty($telegram_cfg['invite_link'])): ?>
                            <a href="<?php echo htmlspecialchars($telegram_cfg['invite_link']); ?>" target="_blank"
                                class="btn btn-ghost btn-xxs btn-icon" data-bs-toggle="tooltip"
                                title="Canal de Telegram" aria-label="Telegram">
                                <i class="fab fa-telegram"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <div class="row g-3 align-items-stretch">
            <!-- Estado del reflector -->
            <div class="col-md-4 d-flex">
                <div class="card flex-fill h-100 border-<?php echo $estado_reflector === 'ACTIVO' ? 'success' : 'danger'; ?> small-card">
                    <div class="card-body">
                        <h5><i class="fas fa-signal"></i> <?= __('reflector_status') ?></h5>
                        <span class="badge bg-<?php echo $estado_reflector === 'ACTIVO' ? 'success' : 'danger'; ?>">
                            <?php echo $estado_reflector; ?>
                        </span>

                        <span class="badge ms-2 bg-<?php echo ($dvref['status'] === 'ONLINE DVREF' ? 'success' : 'danger'); ?>">
                            DVREF
                        </span>
                        <table class="table table-sm mb-0 align-middle mt-2">
                            <tr>
                                <td class="text-nowrap"><i class="fas fa-clock"></i> <strong><?= __('uptime') ?></strong></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars(trim($uptime)); ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-nowrap"><i class="fas fa-network-wired"></i> <strong><?= __('port') ?></strong></td>
                                <td><?php echo htmlspecialchars($puerto_reflector); ?></td>
                            </tr>



                            <tr>
                                <td class="text-nowrap"><i class="fas fa-globe"></i> <strong><?= __('public_ip') ?></strong></td>
                                <td>
  <span data-bs-toggle="tooltip" title="IP parcial por privacidad">
    <?php echo htmlspecialchars($ip_publica); ?>
  </span>
</td>

                            </tr>
                            <tr>
                                <td class="text-nowrap"><i class="fas fa-plug"></i> <strong><?= __('p25_start') ?></strong></td>
                                <td><?php echo htmlspecialchars(fmt_local($inicio_p25)); ?></td>
                            </tr>
                            <tr>
                                <td class="text-nowrap"><i class="fas fa-exclamation-triangle"></i> <strong><?= __('alert') ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $alert_class; ?>">

                                        <?php echo $alert_msg !== '' ? htmlspecialchars($alert_msg) : __('no_alerts'); ?>
                                    </span>
                                    <small class="text-muted"><?php echo $alert_rel; ?></small>
                                </td>
                                </td>
			     <tr>
                                <td class="text-nowrap"><i class="fa-brands fa-linux"></i> <strong><?= __('system') ?></strong></td>
                                <td><?php echo htmlspecialchars($os_version); ?></td>
                            </tr>
                            <tr>
                                <td class="text-nowrap"><i class="fas fa-broadcast-tower"></i> <strong><?= __('protocol') ?></strong></td>
                                <td><span>P25 Fase 1</span> <small>(C4FM / IMBE)</small></td>
                            </tr>

                        </table>
                    </div>
                </div>
            </div>


            <div class="col-md-4 d-flex">
                <div class="card flex-fill h-100 text-white <?php echo $en_transmision ? 'bg-danger' : 'bg-success'; ?>" id="traficoCard">
                    <div class="card-body">
                        <h5>
                            <i class="fas fa-broadcast-tower me-1"></i> <?= __('current_traffic') ?>

                            <?php if ($en_transmision): ?>
                                <span class="badge bg-light text-danger ms-2" id="traficoBadge"><i class="fas fa-circle-notch fa-spin"></i> <?= __('transmitting') ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-success ms-2" id="traficoBadge"><?= __('waiting') ?></span>
                            <?php endif; ?>
                        </h5>

                        <div class="mt-3">
                            <p class="mb-1"><i class="fas fa-user-tag"></i>
                                <strong><?= __('callsign') ?>:</strong>
                                <span id="traficoCall">
                                    <?php
                                    echo $trafico_actual_nombre
                                        ? qrz_link($trafico_actual_nombre, 'text-white text-decoration-underline')
                                        : '—';
                                    ?>
                                </span>
                            </p>

                            <p class="mb-1"><i class="fas fa-id-card"></i>
                                <strong>ID:</strong> <span id="traficoID">
                                    <?php
                                    $ultimo_qso = !empty($trafico_historico) ? end($trafico_historico) : null;
                                    echo $ultimo_qso['id'] ?? '—';
                                    ?>
                                </span>
                            </p>

                            <p class="mb-1"><i class="fas fa-user"></i>
                                <strong><?= __('name') ?>:</strong>
                                <span id="traficoNombre">
                                    <?php
                                    if (!empty($ultimo_qso)) {
                                        $n = radioid_lookup_name($ultimo_qso['indicativo'] ?? '', $ultimo_qso['id'] ?? null);
                                        echo $n ? htmlspecialchars($n) : '—';
                                    } else echo '—';
                                    ?>
                                </span>
                            </p>

                            <p class="mb-1"><i class="fas fa-comments"></i>
                                <strong>TalkGroup:</strong> <span id="traficoTG"><?php echo $ultimo_qso['tg'] ?? '—'; ?></span>
                            </p>

                            <p class="mb-1"><i class="fas fa-clock"></i>
                                <strong><?= __('time') ?>:</strong><span id="traficoHora"><?php echo $ultimo_qso['hora'] ?? '—'; ?></span>
                            </p>

                            <p class="mb-0"><i class="fas fa-stopwatch"></i>
                                <strong><?= __('duration') ?>:</strong> <span id="traficoDur">—</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>


            <div class="col-md-4 d-flex">
                <div class="card flex-fill h-100 small-card border-info">
                    <div class="card-body">
                        <h5><i class="fas fa-server"></i> <?= __('status_server') ?></h5>

                        <!-- CPU -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">CPU (<?php echo cpuCores(); ?> núcleos)</small>
                                <small><span class="badge bg-<?php echo $cpu_perc < 70 ? 'success' : ($cpu_perc < 90 ? 'warning' : 'danger'); ?>">
                                        <?php echo $cpu_perc; ?>%
                                    </span></small>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-<?php echo $cpu_perc < 70 ? 'success' : ($cpu_perc < 90 ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $cpu_perc; ?>%;"></div>
                            </div>
                            <small class="text-muted">Cargas: <?php echo number_format($load1, 2); ?> / <?php echo number_format($load5, 2); ?> / <?php echo number_format($load15, 2); ?></small>
                        </div>

                        <!-- MEMORIA -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Memoria</small>
                                <small><span class="badge bg-<?php echo $mem_perc < 70 ? 'success' : ($mem_perc < 90 ? 'warning' : 'danger'); ?>">
                                        <?php echo $mem_used; ?> / <?php echo $mem_total; ?> MB (<?php echo $mem_perc; ?>%)
                                    </span></small>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-<?php echo $mem_perc < 70 ? 'success' : ($mem_perc < 90 ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $mem_perc; ?>%;"></div>
                            </div>
                            <small class="text-muted">Disponible: <?php echo $mem_avail; ?> MB</small>
                        </div>

                        <!-- DISCO -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Disco (/)</small>
                                <small><span class="badge bg-<?php echo $disk_usep < 70 ? 'success' : ($disk_usep < 90 ? 'warning' : 'danger'); ?>">
                                        <?php echo htmlspecialchars($disk['used']); ?> / <?php echo htmlspecialchars($disk['size']); ?> (<?php echo $disk_usep; ?>%)
                                    </span></small>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-<?php echo $disk_usep < 70 ? 'success' : ($disk_usep < 90 ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $disk_usep; ?>%;"></div>
                            </div>
                            <small class="text-muted">Libre: <?php echo htmlspecialchars($disk['avail']); ?></small>
                        </div>

                        <!-- SWAP -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Swap</small>
                                <small><span class="badge bg-<?php echo $swap_perc < 50 ? 'success' : ($swap_perc < 80 ? 'warning' : 'danger'); ?>">
                                        <?php echo $swap_total; ?> MB total · <?php echo $swap_used; ?> MB en uso (<?php echo $swap_perc; ?>%)
                                    </span></small>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-<?php echo $swap_perc < 50 ? 'success' : ($swap_perc < 80 ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $swap_perc; ?>%;"></div>
                            </div>
                        </div>

                        <!-- TEMP -->
                        <div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Temperatura</small>
                                <small>
                                    <?php if ($temp_c !== null): ?>
                                        <?php $tBadge = ($temp_c < 65) ? 'success' : (($temp_c < 80) ? 'warning' : 'danger'); ?>
                                        <span class="badge bg-<?php echo $tBadge; ?>"><?php echo $temp_c; ?> °C</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/D</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php if ($temp_c !== null):
                                $tperc = max(0, min(100, round(($temp_c / 100) * 100))); ?>
                                <div class="progress" style="height:8px;">
                                    <div class="progress-bar bg-<?php echo $tBadge; ?>" role="progressbar" style="width: <?php echo $tperc; ?>%;"></div>
                                </div>
                                <small class="text-muted">Referencia: 80 °C = alto</small>
                            <?php else: ?>
                                <small class="text-muted">No disponible</small>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
     ESTACIONES CONECTADAS - NUEVO SISTEMA (UNA FILA POR PUERTO)
     ============================================================ -->

        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card border-primary small-card">
                    <div class="card-body">
                        <h5><i class="fas fa-users"></i> <?= __('connected_stations') ?></h5>

                        <?php if (count($estaciones) > 0): ?>

    <?php
    $ultimo_item_ts = 0;
    $items = [];

    foreach ($estaciones as $indicativo => $conns) {
        foreach ($conns as $c) {
            $c['indicativo'] = $indicativo;
            $items[] = $c;

            if (($c['ts'] ?? 0) > $ultimo_item_ts) {
                $ultimo_item_ts = $c['ts'];
            }
        }
    }

    usort($items, fn($a, $b) => ($b['ts'] ?? 0) - ($a['ts'] ?? 0));
    ?>

    <ul class="list-group list-group-flush">
        <?php foreach ($items as $c): ?>
            <?php
            $indicativo = $c['indicativo'];
            $hora       = $c['hora'];
            $ipport     = $c['ip'];
            $ts         = $c['ts'];
            $nombre_est = radioid_lookup_name($indicativo);

            $ts_conexion = strtotime($hora);
            $edad_seg    = $ts_conexion ? (time() - $ts_conexion) : 999999;

            $es_nuevo = ($ts == $ultimo_item_ts && $edad_seg <= 180);

            if ($edad_seg < 60) {
                $txt_edad = $edad_seg . ' s';
            } elseif ($edad_seg < 3600) {
                $txt_edad = floor($edad_seg / 60) . ' min';
            } else {
                $txt_edad = floor($edad_seg / 3600) . ' h';
            }

            $puerto = intval(explode(':', $ipport)[1] ?? 0);

            if (($puerto >= 4000 && $puerto <= 7000) || ($puerto >= 21000 && $puerto <= 27000)) {
                $tipo = "Hotspot/Repetidor";
                $icono = "fa-tower-broadcast text-primary";
            } elseif ($puerto >= 27000 && $puerto <= 28000) {
                $tipo = "Bridge/Enlace";
                $icono = "fa-link text-warning";
            } elseif (($puerto >= 48000 && $puerto <= 65535) || ($puerto >= 12000 && $puerto <= 21000)) {
                $tipo = "Móvil/DroidStar";
                $icono = "fa-mobile-screen-button text-success";
            } else {
                $tipo = "Desconocido";
                $icono = "fa-question-circle text-secondary";
            }

            // (Opcional) ocultar IP:puerto en listado también
            // $ipport = preg_replace('/^(\d+\.\d+)\.\d+\.\d+(:\d+)?$/', '$1.x.x$2', $ipport);
            ?>

            <li class="list-group-item py-2 px-2 <?php echo $es_nuevo ? 'bg-warning-subtle' : ''; ?>">
                <i class="fas <?php echo $icono; ?>"></i>
                <strong><?php echo qrz_link($indicativo); ?></strong>

                <?php if ($nombre_est): ?>
                    <small class="text-muted">— <?php echo htmlspecialchars($nombre_est); ?></small>
                <?php endif; ?>

                <span class="badge bg-dark ms-2"><?php echo $tipo; ?></span>

                <?php if ($es_nuevo): ?>
                    <span class="badge bg-info text-dark ms-2">🆕 Nuevo</span>
                <?php endif; ?>

                <div class="ms-4 mt-1">
                    <small class="text-muted">
                        <?php echo htmlspecialchars($ipport); ?>
                        <span class="ms-2">📅 <?php echo htmlspecialchars($hora); ?></span>
                    </small>
                    <br>
                    <small class="text-muted">
                        ⏱ Conectado hace <?php echo htmlspecialchars($txt_edad); ?>
                    </small>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

<?php else: ?>
    <p class="text-muted mb-0"><?= __('no_active_links') ?></p>
<?php endif; ?>


                    </div>
                </div>
            </div>
        </div>



        <!-- Historial -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card border-secondary small-card">
                    <div class="card-body">
                        <h5><i class="fas fa-history"></i> <?= __('tx_history') ?></h5>
                        <?php if (count($trafico_historico) > 0): ?>
                            <div class="scroll-area mt-2">
                                <table class="table table-sm table-striped align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Hora</th>
                                            <th>ID</th>
                                            <th>Indicativo</th>
                                            <th>Nombre</th>
                                            <th>TalkGroup</th>
                                            <th>Origen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_reverse($trafico_historico) as $qso): ?>
                                            <?php $n = radioid_lookup_name($qso['indicativo'], $qso['id']); ?>
                                            <tr>
                                                <td><?php echo $qso['hora']; ?></td>
                                                <td><?php echo $qso['id']; ?></td>
                                                <td><?php echo qrz_link($qso['indicativo']); ?></td>
                                                <td><?php echo $n ? htmlspecialchars($n) : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $qso['tg']; ?></td>
                                                <td>
                                                    <?php echo !empty($qso['loc']) ? htmlspecialchars($qso['loc']) : '<span class="text-muted">—</span>'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            </div>
                        <?php else: ?>
                            <p class="text-muted">Sin transmisiones recientes.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🏆 Ranking de Actividad -->
    <div class="container py-0">
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card border-info small-card">
                    <div class="card-body">
                        <h5><i class="fas fa-trophy text-warning"></i> Ranking de Actividad</h5>

                        <?php if (!empty($heard_rows)): ?>
                            <?php

                            $top3 = array_slice($heard_rows, 0, 3);
                            ?>

                            <!-- Podio -->
                            <div class="row text-center mb-4">
                                <?php foreach ($top3 as $i => $row): ?>
                                    <?php $n = radioid_lookup_name($row['indicativo'], $row['id']); ?>
                                    <div class="col-md-4">
                                        <div class="card podio-<?php echo $i + 1; ?>">
                                            <div class="card-body">
                                                <h3>
                                                    <?php echo ($i === 0 ? "🥇" : ($i === 1 ? "🥈" : "🥉")); ?>
                                                </h3>
                                                <h5><strong><?php echo qrz_link($row['indicativo']); ?></strong></h5>
                                                <p class="mb-1"><?php echo $n ? htmlspecialchars($n) : '<span class="text-muted">—</span>'; ?></p>
                                                <span class="badge bg-info text-dark"><?php echo $row['count']; ?> QSOs</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Tabla completa -->
                            <div class="scroll-area mt-2" style="max-height:400px; overflow-y:auto;">
                                <table class="table table-sm table-striped align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Ranking</th>
                                            <th>Indicativo</th>
                                            <th>Nombre</th>
                                            <th>QSOs</th>
                                            <th>Tiempo (s)</th>
                                            <th>
                                                Puntaje
                                                <i class="fas fa-circle-info text-info"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="El puntaje combina QSOs y tiempo de transmisión. 1 QSO = 1 punto y cada 10s de cada QSO = 1 punto.">
                                                </i>
                                            </th>
                                            <th>Última vez</th>
                                            <th>Último TG</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1;
                                        $max_qsos = $heard_rows[0]['count']; ?>
                                        <?php foreach ($heard_rows as $row): ?>
                                            <?php $n = radioid_lookup_name($row['indicativo'], $row['id']); ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    if ($i === 1) echo "🥇";
                                                    elseif ($i === 2) echo "🥈";
                                                    elseif ($i === 3) echo "🥉";
                                                    else echo "#" . $i;
                                                    ?>
                                                </td>
                                                <td><strong><?php echo qrz_link($row['indicativo']); ?></strong></td>
                                                <td><?php echo $n ? htmlspecialchars($n) : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo (int)$row['count']; ?></td>
                                                <td><?php echo (int)$row['time']; ?></td>
                                                <td><?php echo number_format($row['score'], 1); ?></td>


                                                <td><?php echo htmlspecialchars($row['last']); ?></td>
                                                <td><?php echo htmlspecialchars($row['last_tg']); ?></td>

                                            </tr>
                                            <?php $i++; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <p class="text-muted mb-0">Sin datos históricos aún.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🌍 Mapa de usuarios conectados -->
<div class="card mt-3 mb-4 shadow-sm overflow-hidden">
    <div class="card-header">
        🌍 <?= __('maps_user_connect') ?>
    </div>
    <div class="card-body p-0">
        <iframe 
            src="includes/mapa.php" 
            style="width:100%; height:500px; border:0; display:block;">
        </iframe>
    </div>
</div>

</div>
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/trafico.js"></script>
    <script src="js/update.js"></script>


<?php 
$product_name = "LYNK25";
require __DIR__ . '/includes/footer.php'; 
?>


</body>

</html>
