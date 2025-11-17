<?php
// cache_estaciones.php
// Genera o entrega un JSON con las estaciones conectadas, actualizado cada 2 minutos

$cacheFile = __DIR__ . '/../data/estaciones_cache.json';
$ttl = 120; // 2 minutos

// Log del reflector
$log_dir = "/var/log/p25reflector/";
$log_files = glob($log_dir . "P25Reflector-*.log");
if (!empty($log_files)) {
    sort($log_files, SORT_NATURAL);
    $log_file = end($log_files);
} else {
    $log_file = $log_dir . "P25Reflector.log";
}

// Si existe un cache válido, lo devolvemos directamente
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    header('Content-Type: application/json; charset=utf-8');
    echo file_get_contents($cacheFile);
    exit;
}

// Si no, generamos nueva lista leyendo el log
$estaciones = [];
if (file_exists($log_file)) {
    $log_lines = file($log_file);
    for ($i = count($log_lines) - 1; $i >= 0; $i--) {
        $line = $log_lines[$i];
        if (strpos($line, "Currently linked repeaters") !== false) break;

        if (preg_match('/\s+([A-Z0-9]+)\s+:\s+([\d\.]+:\d+)/', $line, $m)) {
            $indicativo = $m[1];
            $ip_port    = $m[2];
            if (preg_match('/M:\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $t)) {
                $fecha_hora = $t[1];
            } else {
                $fecha_hora = gmdate("Y-m-d H:i:s");
            }
            $estaciones[$indicativo][] = [
                'ip'   => $ip_port,
                'hora' => $fecha_hora,
                'ts'   => strtotime($fecha_hora)
            ];
        }
    }
}

// Guardamos en JSON
file_put_contents($cacheFile, json_encode($estaciones, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Salida
header('Content-Type: application/json; charset=utf-8');
echo json_encode($estaciones);
?>
