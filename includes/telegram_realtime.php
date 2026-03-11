<?php
// ===========================================================
// LYNK25 - Telegram tiempo real (VERSIÓN ESTABLE FINAL)
// Rotación REAL + protección anti-loop + robustez extra
// ===========================================================

require __DIR__ . '/config.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/timezone.php';

define('LOG_DIR', '/var/log/p25reflector/');
define('IGNORE_CALLS', ['DVREFCHK','DVREFCK']);
define('DEBUG_MODE', true);

function dbg($m){ if(DEBUG_MODE) echo "[DBG] $m\n"; }

// ===========================================================
// OBTENER EL LOG MÁS NUEVO
// ===========================================================
function get_latest_log() {
    $files = glob(LOG_DIR . "P25Reflector-*.log");

    if (!$files) {
        dbg("❌ No se encontraron logs en ".LOG_DIR);
        return null;
    }

    usort($files, function($a, $b) {
        preg_match('/(\d{4}-\d{2}-\d{2})/', basename($a), $ma);
        preg_match('/(\d{4}-\d{2}-\d{2})/', basename($b), $mb);
        return strtotime($mb[1]) - strtotime($ma[1]);
    });

    return $files[0];
}

// ===========================================================
// tail -F ROBUSTO
// ===========================================================
function run_tail($file) {

    if (!is_readable($file)) {
        dbg("❌ Archivo NO legible: $file");
        sleep(2);
        return false;
    }

    dbg("🟢 Escuchando: $file");

    $cmd = 'tail -n 0 -F ' . escapeshellarg($file);
    $proc = popen($cmd, 'r');

    if (!$proc) {
        dbg("❌ ERROR: no pude iniciar tail");
        sleep(2);
        return false;
    }

    while (!feof($proc)) {

        // DETECCIÓN DE ROTACIÓN REAL
        $latest = get_latest_log();
        if ($latest && $latest !== $file) {
            dbg("🔁 Nuevo log detectado → $latest");
            pclose($proc);
            return false;
        }

        $line = fgets($proc);
        if (!$line) {
            usleep(200000); 
            continue;
        }

        $line = trim($line);
        if ($line === '') continue;

        // ==========================
        //   EVENTO: CONECTADA
        // ==========================
        if (preg_match('/Adding\s+([A-Z0-9]{3,8})\s+\(([\d\.]+:\d+)\)/', $line, $m)) {
            $cs = $m[1];
            if (!in_array($cs, IGNORE_CALLS)) {
                telegram_send("✅ <b>Estación conectada</b>\n<b>$cs</b>\n⏰ ".date('Y-m-d H:i:s'));
                dbg("Conectada: $cs");
            }
        }

        // ==========================
        //   EVENTO: DESCONECTADA
        // ==========================
        if (preg_match('/Removing\s+([A-Z0-9]{3,8})\s+\(([\d\.]+:\d+)\)\s+(disappeared|unlinked)/', $line, $m)) {
            $cs = $m[1];
            if (!in_array($cs, IGNORE_CALLS)) {
                telegram_send("❌ <b>Estación desconectada</b>\n<b>$cs</b>\n⏰ ".date('Y-m-d H:i:s'));
                dbg("Desconectada: $cs");
            }
        }
    }

    dbg("⚠ tail finalizó inesperadamente, reiniciando...");
    pclose($proc);
    sleep(1);
    return false;
}

// ===========================================================
// MAIN LOOP
// ===========================================================
dbg("🔄 Iniciando LYNK25 realtime...");

$current = get_latest_log();
if (!$current) {
    dbg("❌ No puedo iniciar: no hay logs.");
    exit;
}

dbg("Log inicial: $current");

while (true) {
    run_tail($current);
    sleep(1);

    $new = get_latest_log();
    if ($new && $new !== $current) {
        dbg("🔄 Cambiando a nuevo log: $new");
        $current = $new;

        // Previene loops rápidos
        sleep(1);
    }
}
