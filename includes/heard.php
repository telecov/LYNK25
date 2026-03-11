<?php
// ===========================================================
// heard.php - Construcción de $heard_rows a partir de TODOS los logs
// ===========================================================

require_once __DIR__ . '/timezone.php';

// Carpeta y patrón de logs
$log_dir  = "/var/log/p25reflector/";
$log_glob = "P25Reflector-*.log";   // logs rotados
$main_log = "P25Reflector.log";     // log en curso

// Buscar todos los logs (rotados + actual)
$log_files = glob($log_dir . $log_glob);
if (!$log_files) $log_files = [];
sort($log_files, SORT_NATURAL);
$log_files[] = $log_dir . $main_log;

$heard = [];
$en_tx = false;
$tx_inicio_ts = null;
$tx_callsign  = null;
$tx_tg        = null;

foreach ($log_files as $f) {
    $lines = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) continue;

    foreach ($lines as $line) {
        // --- Inicio TX ---
        if (preg_match('/Transmission started from\s+([A-Z0-9]+)/', $line, $m)) {
            $tx_callsign  = $m[1];
            $tx_inicio_ts = ts_from_utc(substr($line, 3, 19));
            $en_tx        = true;
        }

        // --- Detalle TX (TalkGroup) ---
        if ($en_tx && preg_match('/Transmission from .* at\s+([A-Z0-9]+)\s+to TG\s+(\d+)/', $line, $m)) {
            $tx_tg = $m[2];
        }

        // --- Fin TX ---
        if ($en_tx && strpos($line, "Received end of transmission") !== false) {
            $fin_ts   = ts_from_utc(substr($line, 3, 19));
            $duracion = max(0, $fin_ts - $tx_inicio_ts);

            if (!isset($heard[$tx_callsign])) {
                $heard[$tx_callsign] = [
                    'id'      => null,
                    'first'   => $tx_inicio_ts,
                    'last'    => $fin_ts,
                    'count'   => 1,
                    'time'    => $duracion,
                    'last_tg' => $tx_tg,
                ];
            } else {
                $heard[$tx_callsign]['count']++;
                $heard[$tx_callsign]['time']  += $duracion;
                $heard[$tx_callsign]['last']   = $fin_ts;
                $heard[$tx_callsign]['last_tg']= $tx_tg;
            }

            // Reset TX
            $en_tx = false;
            $tx_callsign = null;
            $tx_inicio_ts = null;
            $tx_tg = null;
        }
    }
}

// ===========================================================
// Construcción de $heard_rows para dashboard / telegram
// ===========================================================
$heard_rows = [];
foreach ($heard as $cs => $d) {
    // Fórmula de score (ajustable):
    // 1 QSO = 1 punto + cada 10s = +1 punto
    $score = $d['count'] + ($d['time'] / 10);

    $heard_rows[] = [
        'indicativo' => $cs,
        'id'         => $d['id'],
        'first'      => date('Y-m-d H:i:s', $d['first']),
        'last'       => date('Y-m-d H:i:s', $d['last']),
        'count'      => $d['count'],   // Número de QSOs
        'time'       => $d['time'],    // Tiempo total (segundos)
        'score'      => $score,        // Puntaje ponderado
        'last_tg'    => $d['last_tg'],
    ];
}

// ===========================================================
// ORDENAR EL RANKING
// ===========================================================
// Opciones: 'score', 'time', 'count'
$ordenar_por = 'score';

usort($heard_rows, function($a, $b) use ($ordenar_por) {
    return $b[$ordenar_por] <=> $a[$ordenar_por];
});
