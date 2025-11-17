<?php
$config_file     = __DIR__ . '/../header_config.json';
$header_title    = "REFLECTOR P25 – ZONA DMR";
$header_subtitle = "Conectando amigos, enlazando pasiones por el aire.";
$header_logo     = "img/zdmrlogoindex.png";
$panel_timezone  = "America/Santiago"; // por defecto

if (file_exists($config_file)) {
    $cfg = json_decode(file_get_contents($config_file), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($cfg)) {
        $header_title    = $cfg['title']    ?? $header_title;
        $header_subtitle = $cfg['subtitle'] ?? $header_subtitle;
        $header_logo     = $cfg['logo']     ?? $header_logo;
        $panel_timezone  = $cfg['timezone'] ?? $panel_timezone;
    }
}
date_default_timezone_set($panel_timezone);

