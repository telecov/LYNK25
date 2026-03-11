<?php
// ==============================
// Telegram Bot Helper (JSON en /includes)
// ==============================

function getTelegramConfig() {
    $file = __DIR__ . '/telegram_config.json';  // ahora en /includes
    if (!file_exists($file)) return null;

    $cfg = json_decode(file_get_contents($file), true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;

    return $cfg;
}

function telegram_send($msg) {
    $cfg = getTelegramConfig();
    if (!$cfg || empty($cfg['token']) || empty($cfg['chat_id'])) {
        error_log("[LYNK25] Telegram config inválida o incompleta.");
        return false;
    }

    $token   = trim($cfg['token']);
    $chat_id = trim($cfg['chat_id']);
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id'    => $chat_id,
        'text'       => $msg,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[LYNK25] Error CURL Telegram: $error");
        return false;
    }

    $json = json_decode($response, true);
    if (empty($json['ok'])) {
        error_log("[LYNK25] Error Telegram API: " . ($json['description'] ?? 'Desconocido'));
        return false;
    }

    return true;
}
