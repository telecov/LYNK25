<?php
// ==============================
// Funciones de zona horaria (UTC → Local)
// ==============================

/**
 * Convierte un string UTC a objeto DateTime en la zona horaria local.
 */
function dt_from_utc($utcStr) {
    $utcStr = trim((string)$utcStr);
    if ($utcStr === '' || strtolower($utcStr) === 'desconocido') return null;

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $utcStr, new DateTimeZone('UTC'));
    if (!$dt) {
        try { 
            $dt = new DateTime($utcStr, new DateTimeZone('UTC')); 
        } catch (Exception $e) { 
            return null; 
        }
    }
    $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    return $dt;
}

/**
 * Devuelve la fecha/hora local en un formato elegido.
 */
function fmt_local($utcStr, $fmt = 'Y-m-d H:i:s') {
    $dt = dt_from_utc($utcStr);
    return $dt ? $dt->format($fmt) : $utcStr;
}

/**
 * Convierte string UTC a timestamp (epoch).
 */
function ts_from_utc($utcStr) {
    $dt = dt_from_utc($utcStr);
    return $dt ? $dt->getTimestamp() : (is_numeric($utcStr) ? (int)$utcStr : time());
}

/**
 * Retorna tiempo relativo en formato humano.
 */
function tiempoRelativoTS($ts) {
    $diff = time() - (int)$ts;
    if ($diff < 60)   return $diff . "s atrás";
    if ($diff < 3600) return floor($diff/60) . "min atrás";
    if ($diff < 86400)return floor($diff/3600) . "h atrás";
    return floor($diff/86400) . "d atrás";
}

