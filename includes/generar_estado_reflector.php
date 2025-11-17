<?php
// includes/generar_estado_reflector.php

$log_dir = "/var/log/p25reflector/";
$log_files = glob($log_dir . "P25Reflector-*.log");

// Ordenar por fecha (más nuevo primero)
usort($log_files, fn($a, $b) => filemtime($b) - filemtime($a));

// Tomar solo los 5 más recientes (suficiente)
$log_files = array_slice($log_files, 0, 5);

$inicio_p25 = "No registrada";
$puerto_udp = "No detectado";
$ultimo_warning = "Sin alertas recientes";
$ultimo_warning_ts = null;

// Procesar logs
foreach ($log_files as $file) {
    $lines = file($file);

    foreach ($lines as $line) {

        // Inicio del reflector
        if (strpos($line, "Opening P25 network connection") !== false) {
            $inicio_p25 = substr($line, 3, 19);
        }

        // Puerto UDP
        if (preg_match('/Opening UDP port on\s+(\d+)/', $line, $m)) {
            $puerto_udp = $m[1];
        }

        // Warnings reales
        if (strpos($line, "watchdog") !== false || strpos($line, "W:") === 3) {

            // Extraer fecha (YYYY-MM-DD HH:MM:SS)
            $fecha = substr($line, 3, 19);
            $ultimo_warning_ts = strtotime($fecha);

            $ultimo_warning = trim(substr($line, 23)); // texto del warning
        }
    }
}

// Si hay timestamp, unirlo con el mensaje
if ($ultimo_warning_ts) {
    $ultimo_warning = date("Y-m-d H:i:s", $ultimo_warning_ts) . "|" . $ultimo_warning;
}

$data = [
    "inicio_p25"    => $inicio_p25,
    "puerto_udp"    => $puerto_udp,
    "ultimo_warning" => $ultimo_warning
];

// SALIDA CORRECTA → data/estado_reflector.json
file_put_contents('/var/www/html/data/estado_reflector.json', json_encode($data, JSON_PRETTY_PRINT));
