<?php
// cache_estaciones.php
// Genera lista de estaciones conectadas con hora REAL de conexión

$cacheFile = __DIR__ . '/../data/estaciones_cache.json';
$ttl = 120;

// Log del reflector
$log_dir = "/var/log/p25reflector/";
$log_files = glob($log_dir . "P25Reflector-*.log");
if (!empty($log_files)) {
    sort($log_files, SORT_NATURAL);
    $log_file = end($log_files);
} else {
    $log_file = $log_dir . "P25Reflector.log";
}

// CACHE válido
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    header('Content-Type: application/json; charset=utf-8');
    echo file_get_contents($cacheFile);
    exit;
}

$estaciones = [];
$hora_conexion = [];

// Cargar todo el log
if (file_exists($log_file)) {
    $log_lines = file($log_file);

    // 1) Buscar horas reales de conexión (Adding)
    foreach ($log_lines as $line) {
        if (preg_match('/M:\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}).*Adding\s+([A-Z0-9]+)/', $line, $m)) {
            $hora = $m[1];
            $ind  = $m[2];
            $hora_conexion[$ind] = $hora;
        }
    }

    // 2) Buscar el bloque de "Currently linked repeaters"
    for ($i = count($log_lines) - 1; $i >= 0; $i--) {
        $line = $log_lines[$i];

        if (strpos($line, "Currently linked repeaters") !== false) break;

        if (preg_match('/\s+([A-Z0-9]+)\s+:\s+([\d\.]+:\d+)/', $line, $m)) {
            $ind = $m[1];
            $ip  = $m[2];

            // Hora real → si existe la guardada, se usa
            $fecha_hora = $hora_conexion[$ind] ?? gmdate("Y-m-d H:i:s");

            $estaciones[$ind][] = [
                'ip'   => $ip,
                'hora' => $fecha_hora,
                'ts'   => strtotime($fecha_hora)
            ];
        }
    }
}

// Save JSON
file_put_contents($cacheFile, json_encode($estaciones, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Output
header('Content-Type: application/json; charset=utf-8');
echo json_encode($estaciones);
?>
