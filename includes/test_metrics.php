

<?php
require __DIR__ . '/metrics.php';

echo "=== METRICS TEST ===\n";

echo "CPU cores: " . cpuCores() . "\n";

list($l1, $l5, $l15) = loadAverages();
echo "Load avg: $l1 / $l5 / $l15\n";

echo "CPU load %: " . cpuLoadPercent() . "%\n";

list($mem, $swap) = memInfoMB();
echo "Memoria: {$mem['used']} / {$mem['total']} MB\n";
echo "Swap: {$swap['used']} / {$swap['total']} MB\n";

$disk = diskRootHuman();
echo "Disco: {$disk['used']} / {$disk['size']} ({$disk['usep']}%)\n";

echo "Temp CPU: " . (temperatureC() ?? 'N/D') . " °C\n";

echo "OS: " . osVersion() . "\n";

$dv = dvref_status_check();
echo "DVREF: {$dv['status']}\n";
echo "Última verificación: " . ($dv['last_verified_at'] ?? 'N/D') . "\n";
print_r($dv);

