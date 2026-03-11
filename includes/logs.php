<?php
// ==============================
// Funciones de logs y alertas
// ==============================

/**
 * Devuelve las últimas N líneas de un archivo (tail).
 */
function tailLog($filename, $lines = 30) {
    $output = [];
    exec("tail -n $lines " . escapeshellarg($filename), $output);
    return $output;
}

/**
 * Procesa una línea de log y devuelve [timestamp, mensaje].
 */
function parseAlerta($linea) {
    $linea = trim($linea ?? '');
    $ts = null; 
    $msg = $linea;

    if (preg_match('/^M:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})(?:\.\d+)?\s+(.*)$/', $linea, $m)) {
        $msg = trim($m[2]);
        $ts  = ts_from_utc($m[1]); // usa timezone.php
    }

    // Caso especial: watchdog
    if (stripos($msg, 'Network watchdog has expired') !== false) {
        $msg = 'Sin alertas';
        $ts  = null;
    }

    return [$ts, $msg];
}

/**
 * Determina la clase CSS de la alerta según mensaje y estado del reflector.
 */
function claseAlerta($msg, $estado_reflector) {
    $m = strtolower($msg ?? '');

    if ($m === '' || strpos($m, 'sin alertas') !== false) return 'secondary';

    foreach (['fatal','panic','segfault','error','failed'] as $kw) {
        if (strpos($m, $kw) !== false) return 'danger';
    }
    foreach (['expired','watchdog','timeout'] as $kw) {
        if (strpos($m, $kw) !== false) return 'warning';
    }

    return ($estado_reflector === 'ACTIVO') ? 'info' : 'secondary';
}

