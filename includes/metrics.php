<?php
function _read_file_firstline($path) {
    if (is_readable($path)) {
        $s = @file_get_contents($path);
        if ($s !== false) return trim($s);
    }
    return null;
}

function cpuCores() {
    $n = trim((string)@shell_exec('nproc 2>/dev/null'));
    return (is_numeric($n) && (int)$n > 0) ? (int)$n : 1;
}

function loadAverages() {
    $line = _read_file_firstline('/proc/loadavg');
    if ($line) {
        $p = preg_split('/\s+/', $line);
        if (count($p) >= 3) return [(float)$p[0], (float)$p[1], (float)$p[2]];
    }
    $u = trim((string)@shell_exec('uptime'));
    if (preg_match('/load average[s]?:\s*([0-9\.,]+),\s*([0-9\.,]+),\s*([0-9\.,]+)/i', $u, $m)) {
        return [
            (float)str_replace(',', '.', $m[1]),
            (float)str_replace(',', '.', $m[2]),
            (float)str_replace(',', '.', $m[3])
        ];
    }
    return [0.0, 0.0, 0.0];
}

function cpuLoadPercent() {
    list($l1) = loadAverages();
    $cores = cpuCores();
    $perc = ($cores > 0) ? round(($l1 / $cores) * 100) : 0;
    if ($perc < 0) $perc = 0;
    if ($perc > 100) $perc = 100;
    return (int)$perc;
}

function memInfoMB() {
    $out = (string)@shell_exec('free -m');
    $mem = ['total'=>0,'used'=>0,'free'=>0,'avail'=>0];
    $swap= ['total'=>0,'used'=>0,'free'=>0];
    foreach (explode("\n", $out) as $line) {
        $line = trim($line);
        if (stripos($line, 'Mem:') === 0 || stripos($line, 'Mem ') === 0) {
            $parts = preg_split('/\s+/', $line);
            $nums = array_values(array_filter($parts, function($v){ return is_numeric($v); }));
            if (count($nums) >= 6) {
                $mem['total'] = (int)$nums[0];
                $mem['used']  = (int)$nums[1];
                $mem['free']  = (int)$nums[2];
                $mem['avail'] = (int)$nums[5];
            }
        }
        if (stripos($line, 'Swap:') === 0 || stripos($line, 'Swap ') === 0) {
            $parts = preg_split('/\s+/', $line);
            $nums = array_values(array_filter($parts, function($v) { return is_numeric($v); }));
            if (count($nums) >= 3) {
                $swap['total'] = (int)$nums[0];
                $swap['used']  = (int)$nums[1];
                $swap['free']  = (int)$nums[2];
            }
        }
    }
    return [$mem, $swap];
}

function diskRootHuman() {
    $out = (string)@shell_exec('df -hP /');
    $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
    if (count($lines) >= 2) {
        $cols = preg_split('/\s+/', $lines[1]);
        if (count($cols) >= 6) {
            return [
                'size'  => $cols[1],
                'used'  => $cols[2],
                'avail' => $cols[3],
                'usep'  => rtrim($cols[4], '%')
            ];
        }
    }
    return ['size'=>'-','used'=>'-','avail'=>'-','usep'=>0];
}

function temperatureC() {
    $paths = [
        '/sys/class/thermal/thermal_zone0/temp',
        '/sys/devices/virtual/thermal/thermal_zone0/temp',
        '/sys/class/hwmon/hwmon0/temp1_input'
    ];
    foreach ($paths as $p) {
        $v = _read_file_firstline($p);
        if (is_numeric($v)) {
            $c = ((int)$v >= 1000) ? ((int)$v / 1000) : (float)$v;
            return round($c, 1);
        }
    }
    $vcg = trim((string)@shell_exec('which vcgencmd 2>/dev/null'));
    if ($vcg) {
        $t = trim((string)@shell_exec("$vcg measure_temp 2>/dev/null"));
        if (preg_match('/temp=([\d\.]+)/', $t, $m)) return round((float)$m[1], 1);
    }
    return null;
}

function osVersion() {
    $desc = trim((string)@shell_exec('lsb_release -d 2>/dev/null | cut -f2'));
    if ($desc !== '') return $desc;

    if (is_readable('/etc/os-release')) {
        $lines = file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'PRETTY_NAME=') === 0) {
                return trim(str_replace('"', '', substr($line, 12)));
            }
        }
    }
    return php_uname('s') . " " . php_uname('r');
}

/* ===========================================================
   DVREF STATUS CHECK (lee config + cache 5 minutos)
   =========================================================== */
function dvref_status_check($cache_file = __DIR__ . '/../data/dvref_status.json', $cache_ttl = 300) {
    $cfg_file = __DIR__ . '/../data/dvref_config.json';
    if (!file_exists($cfg_file)) {
        return ['status' => "CONFIG NO DEFINIDA", 'last_verified_at' => null];
    }
    $cfg = json_decode(@file_get_contents($cfg_file), true);
    if (!is_array($cfg) || empty($cfg['token']) || empty($cfg['host']) || empty($cfg['port']) || empty($cfg['tg'])) {
        return ['status' => "CONFIG INVÁLIDA", 'last_verified_at' => null];
    }

    $token = $cfg['token'];
    $host  = $cfg['host'];
    $port  = (int)$cfg['port'];
    $tg    = (int)$cfg['tg'];

    // Cache (5 minutos)
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
        $cached = json_decode(@file_get_contents($cache_file), true);
        if (is_array($cached) && isset($cached['status'])) {
            return $cached;
        }
    }

    $url = "https://dvref.com/api/v2/p25/reflectors/";

    // Llamada API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token $token",
            "Accept: application/json"
        ],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse
    $data = json_decode($response, true);
    $reflectors = [];
    if (is_array($data)) {
        // Nuevo formato con envoltorio
        if (isset($data['data']['reflectors']) && is_array($data['data']['reflectors'])) {
            $reflectors = $data['data']['reflectors'];
        } elseif (isset($data[0])) {
            // fallback antiguo (array plano)
            $reflectors = $data;
        }
    }

    $status = "NO REPORTADO";
    $last_verified = null;

    foreach ($reflectors as $ref) {
        if (
            isset($ref['dns'], $ref['port'], $ref['designator']) &&
            strtolower(trim($ref['dns'])) === strtolower(trim($host)) &&
            (int)$ref['port'] == $port &&
            (int)$ref['designator'] == $tg
        ) {
            $status = "EN LÍNEA DVREF";
            $last_verified = $ref['last_verified_at'] ?? null;
            break;
        }
    }

    $result = ['status' => $status, 'last_verified_at' => $last_verified];

    // Guardar cache
    @file_put_contents($cache_file, json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);

    return $result;
}

