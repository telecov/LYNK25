<?php
/*
===================================================
 LYNK25 - Verificación y Actualización Automática
 Autor: Telecoviajero (CA2RDP)
===================================================
*/

header('Content-Type: application/json; charset=utf-8');
set_time_limit(120);
error_reporting(0);

$base_dir = realpath(__DIR__ . '/..');
$local_file = "$base_dir/version.json";
$repo_api = "https://api.github.com/repos/telecov/LYNK25/releases/latest";

// --- 1️⃣ Validar versión local ---
if (!file_exists($local_file)) {
    echo json_encode(["status" => "error", "message" => "No se encontró version.json en la raíz del proyecto."]);
    exit;
}

$local_data = json_decode(file_get_contents($local_file), true);
$local_version = $local_data['version'] ?? '0.0.0';

// --- 2️⃣ Consultar GitHub Releases ---
$ch = curl_init($repo_api);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => 'LYNK25-Updater',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20
]);
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http !== 200 || !$response) {
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo conectar a GitHub (HTTP $http). Verifica que exista al menos un release publicado."
    ]);
    exit;
}

$data = json_decode($response, true);
$latest = ltrim($data["tag_name"] ?? "0.0.0", "v");
$url_zip = $data["zipball_url"] ?? "";

// --- 3️⃣ Si solo estamos verificando ---
if (!isset($_GET['do_update'])) {
    if (version_compare($latest, $local_version, ">")) {
        echo json_encode([
            "status" => "update_available",
            "local_version" => $local_version,
            "latest_version" => $latest
        ]);
    } else {
        echo json_encode([
            "status" => "up_to_date",
            "message" => "LYNK25 ya está actualizado a la versión $local_version"
        ]);
    }
    exit;
}

// --- 4️⃣ Aplicar la actualización real ---
$tmp_zip = sys_get_temp_dir() . "/lynk25_update.zip";
$tmp_dir = sys_get_temp_dir() . "/lynk25_update_" . uniqid();

// Descargar ZIP
$fp = fopen($tmp_zip, 'w+');
$ch = curl_init($url_zip);
curl_setopt_array($ch, [
    CURLOPT_FILE => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'LYNK25-Updater',
    CURLOPT_SSL_VERIFYPEER => true
]);
curl_exec($ch);
curl_close($ch);
fclose($fp);

// Descomprimir
mkdir($tmp_dir);
exec("unzip -oq $tmp_zip -d $tmp_dir");

// Buscar subcarpeta principal (GitHub la agrega con nombre dinámico)
$subdirs = glob("$tmp_dir/*", GLOB_ONLYDIR);
if (empty($subdirs)) {
    echo json_encode(["status" => "error", "message" => "No se pudo descomprimir el paquete de actualización."]);
    exit;
}
$source_dir = $subdirs[0];

// Excluir archivos locales
$exclude = ['data', 'includes/config.php', 'includes/telegram.php', 'version.json'];

// Copiar archivos actualizados
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $dest = $base_dir . '/' . $iterator->getSubPathName();
    foreach ($exclude as $ex) {
        if (strpos($dest, $ex) !== false) continue 2;
    }
    if ($file->isDir()) {
        if (!is_dir($dest)) mkdir($dest, 0755, true);
    } else {
        copy($file, $dest);
    }
}

// Actualizar version.json
$local_data['version'] = $latest;
$local_data['fecha_actualizacion'] = date('Y-m-d H:i:s');
file_put_contents($local_file, json_encode($local_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Limpiar temporales
exec("rm -rf $tmp_dir $tmp_zip");

echo json_encode([
    "status" => "success",
    "message" => "✅ Actualización completada correctamente a versión $latest"
]);
?>
