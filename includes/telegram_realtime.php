<?php
// ===========================================================
// LYNK25 - Telegram tiempo real (ROTACIÓN REAL + AUTO SWITCH)
// ===========================================================

require __DIR__ . '/config.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/timezone.php';

define('LOG_DIR', '/var/log/p25reflector/');
define('IGNORE_CALLS', ['DVREFCHK','DVREFCK']);
define('DEBUG_MODE', true);

function dbg($m){ if(DEBUG_MODE) echo "[DBG] $m\n"; }

// -----------------------------------------------------------
// Obtener SIEMPRE el archivo de log MÁS RECIENTE
// -----------------------------------------------------------
function get_latest_log() {
    $files = glob(LOG_DIR . "P25Reflector-*.log");
    if (!$files) return null;

    // Ordenar por fecha basada en el NOMBRE del archivo, no en filemtime()
    usort($files, function($a, $b) {
        preg_match('/P25Reflector-(\d{4}-\d{2}-\d{2})\.log$/', $a, $ma);
        preg_match('/P25Reflector-(\d{4}-\d{2}-\d{2})\.log$/', $b, $mb);

        $da = strtotime($ma[1] ?? '1970-01-01');
        $db = strtotime($mb[1] ?? '1970-01-01');

        return $db - $da; // más nuevo primero
    });

    return $files[0];
}


// -----------------------------------------------------------
// Ejecutar tail -F y detectar rotación REAL
// -----------------------------------------------------------
function run_tail($file) {
    dbg("🟢 Escuchando: $file");

    $cmd = 'tail -n 0 -F ' . escapeshellarg($file);
    $proc = popen($cmd, 'r');
    if (!$proc) return false;

    while (!feof($proc)) {

        clearstatcache();

        // ⛔ DETECCIÓN DE NUEVO LOG MÁS RECIENTE (rotación real)
        $latest = get_latest_log();
        if ($latest !== $file) {
            dbg("🔁 Nuevo log detectado: $latest");
            pclose($proc);
            return false;
        }

        $line = fgets($proc);
        if ($line === false){ usleep(200000); continue; }
        $line = trim($line);
        if ($line === '') continue;

        // ---------- CONEXIÓN ----------
        if (preg_match('/Adding\s+([A-Z0-9]{3,8})\s+\(([\d\.]+:\d+)\)/i',$line,$m)) {
            $cs = $m[1];
            if (!in_array($cs, IGNORE_CALLS)) {
                $msg = "✅ <b>Estación conectada</b>\n<b>{$cs}</b>\n⏰ ".date('Y-m-d H:i:s');
                telegram_send($msg);
                dbg("Conectada: $cs");
            }
        }

        // ---------- DESCONEXIÓN ----------
        if (preg_match('/Removing\s+([A-Z0-9]{3,8})\s+\(([\d\.]+:\d+)\)\s+(disappeared|unlinked)/i',$line,$m)) {
            $cs = $m[1];
            if (!in_array($cs, IGNORE_CALLS)) {
                $msg = "❌ <b>Estación desconectada</b>\n<b>{$cs}</b>\n⏰ ".date('Y-m-d H:i:s');
                telegram_send($msg);
                dbg("Desconectada: $cs");
            }
        }
    }

    pclose($proc);
    return false;
}

// -----------------------------------------------------------
// MAIN LOOP
// -----------------------------------------------------------
dbg("🔄 Iniciando LYNK25 realtime...");

$current = get_latest_log();
if (!$current){
    dbg("❌ No se encontraron logs.");
    exit;
}

dbg("Log inicial: $current");

while(true){
    run_tail($current);

    sleep(1);

    $new = get_latest_log();
    if ($new !== $current){
        dbg("🔁 Cambiando a nuevo log: $new");
        $current = $new;
    }
}
