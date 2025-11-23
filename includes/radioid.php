<?<?php
// ==============================
// RadioID Lookup + QRZ link
// ==============================

// Caché en archivo local
define('RADIOID_CACHE', __DIR__ . '/../data/radioid_cache.json');
define('RADIOID_TTL',   86400); // 24h

/**
 * Geocodificación usando Nominatim (OpenStreetMap)
 * Devuelve lat/lon a partir de "ciudad + país"
 */
function radioid_geocode($texto) {
    if (!$texto) return null;
    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=" . urlencode($texto);
    $opts = ["http" => ["header" => "User-Agent: Lynk25 Dashboard"]];
    $ctx  = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (!empty($data[0])) {
        return [
            'lat' => (float)$data[0]['lat'],
            'lon' => (float)$data[0]['lon']
        ];
    }
    return null;
}


/**
 * Descargar JSON desde una URL con soporte cURL o file_get_contents
 */
function http_get_json($url, $timeout = 5, $connectTimeout = 3) {

    // 1) Preferir cURL si existe
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Lynk25 Dashboard',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        if ($raw) {
            $j = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $j;
            }
        }
    }

    // 2) fallback file_get_contents
    $ctx = stream_context_create([
        'http' => [
            'method' => "GET",
            'timeout' => $timeout,
            'header' => "User-Agent: Lynk25 Dashboard\r\n"
        ]
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw) {
        $j = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $j;
        }
    }

    return null;
}


/**
 * Busca nombre en RadioID.net
 */
function radioid_lookup_name($callsign, $idNum = null) {
    static $mem = [];
    $key = strtoupper(trim($callsign ?: ''));
    if ($key === '') return null;

    // Revisa memoria (cache en RAM durante ejecución)
    if (isset($mem[$key])) return $mem[$key];

    // Revisa cache en disco
    $cache = [];
    if (is_readable(RADIOID_CACHE)) {
        $cache = json_decode(@file_get_contents(RADIOID_CACHE), true) ?: [];
        if (isset($cache[$key]['ts']) && (time() - $cache[$key]['ts'] < RADIOID_TTL)) {
            return $mem[$key] = ($cache[$key]['name'] ?? null);
        }
    }

    // Arma URL de consulta
    $param = $idNum ? ('id=' . urlencode($idNum)) : ('callsign=' . urlencode($key));
    $url   = "https://radioid.net/api/dmr/user/?$param";
    $json  = http_get_json($url);

    $name = null;
    if ($json && !empty($json['results'][0])) {
        $r = $json['results'][0];
        if (!empty($r['name'])) {
            $name = trim($r['name']);
        } elseif (!empty($r['fname']) || !empty($r['surname'])) {
            $name = trim(($r['fname'] ?? '') . ' ' . ($r['surname'] ?? ''));
        } elseif (!empty($r['firstName']) || !empty($r['lastName'])) {
            $name = trim(($r['firstName'] ?? '') . ' ' . ($r['lastName'] ?? ''));
        }
    }
// ==============================
// Guardar en cache enriquecido
// ==============================
$idNum   = $r['id']      ?? $idNum;  // ahora sí guardamos ID
$city    = $r['city']    ?? null;
$country = $r['country'] ?? null;

// Intentar obtener coordenadas si no están guardadas
$lat = $cache[$key]['lat'] ?? null;
$lon = $cache[$key]['lon'] ?? null;

if ((!$lat || !$lon) && ($city || $country)) {
    $query = trim($city . " " . $country);
    $coords = radioid_geocode($query);
    if ($coords) {
        $lat = $coords['lat'];
        $lon = $coords['lon'];
    }
}

// Guardar todo en el cache
$cache[$key] = [
    'id'      => $idNum,   // 🔥 NUEVO
    'name'    => $name,
    'ts'      => time(),
    'city'    => $city,
    'country' => $country,
    'lat'     => $lat,
    'lon'     => $lon
];

@file_put_contents(RADIOID_CACHE, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));


    return $mem[$key] = $name;
}

/**
 * Genera link a QRZ.com
 */
function qrz_link($callsign, $classes='text-info text-decoration-none') {
    $cs  = strtoupper(trim($callsign));
    $url = 'https://www.qrz.com/db/' . rawurlencode($cs);
    return '<a href="'.$url.'" target="_blank" rel="noopener" class="'.$classes.'">' . htmlspecialchars($cs) . '</a>';
}

/**
 * Devuelve el ID numérico (DMR ID) asociado a un indicativo
 */
function radioid_lookup_id($callsign) {
    $key = strtoupper(trim($callsign ?: ''));
    if ($key === '') return null;

    // Primero mirar en el cache
    if (is_readable(RADIOID_CACHE)) {
        $cache = json_decode(@file_get_contents(RADIOID_CACHE), true) ?: [];
        if (isset($cache[$key]['id'])) {
            return $cache[$key]['id'];
        }
    }

    // Si no está en cache, forzamos a pedir el nombre (lo que llena el cache con el ID)
    radioid_lookup_name($callsign);

    // Volvemos a intentar del cache
    if (is_readable(RADIOID_CACHE)) {
        $cache = json_decode(@file_get_contents(RADIOID_CACHE), true) ?: [];
        return $cache[$key]['id'] ?? null;
    }

    return null;
}
