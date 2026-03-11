<?php
// ===========================================================
// LYNK25 - Notificaciones Telegram (FINAL OFICIAL)
// ===========================================================
// • Resumen estaciones conectadas (cada hora con CRON)
// • Reporte semanal (domingo 20:00)
// • Reporte diario del servidor (12:00)
// • Soporte ROTATE para logs P25Reflector
// ===========================================================

require __DIR__ . '/config.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/heard.php';

define('LOG_DIR', '/var/log/p25reflector/');
define('STATE_FILE', __DIR__ . '/../data/telegram_state.json');
define('TG_REMINDER_INTERVAL', 3600);
define('MAX_LOG_LINES', 500);
define('DEBUG_MODE', true);

function dbg($m){ if(DEBUG_MODE) echo "[DBG] $m\n"; }

// ======================================================
// 1) STATE
// ======================================================
function ensure_state(){
    if(!file_exists(STATE_FILE)){
        $init=[
            'summary'=>['last'=>0],
            'weekly'=>['last'=>null],
            'daily'=>['last'=>null]
        ];
        file_put_contents(STATE_FILE,json_encode($init,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        return $init;
    }

    $st=json_decode(@file_get_contents(STATE_FILE),true);
    if(!is_array($st)){
        $st=['summary'=>['last'=>0],'weekly'=>['last'=>null],'daily'=>['last'=>null]];
    }
    return $st;
}

function save_state($st){
    file_put_contents(
        STATE_FILE,
        json_encode($st,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

// ======================================================
// 2) DETECTAR LOG MÁS RECIENTE (ROTATE)
// ======================================================
function get_latest_log(){
    $files=glob(LOG_DIR.'P25Reflector-*.log');
    if(empty($files)) return null;

    // orden por fecha de modificación
    usort($files, fn($a,$b)=>filemtime($b)-filemtime($a));

    return $files[0];
}

// ======================================================
// 3) EXTRAER EL BLOQUE “Currently linked repeaters”
// ======================================================
function get_currently_linked($logfile){
    $lines=@file($logfile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if(!$lines){
        dbg("❌ No se pudo leer el log.");
        return null;
    }

    // limitar a últimas N líneas
    if(count($lines)>MAX_LOG_LINES){
        $lines=array_slice($lines,-MAX_LOG_LINES);
    }

    dbg("Analizando ".count($lines)." líneas...");

    $linked=[];
    $found=false;
    $total=count($lines);

    // Buscar bloque desde el final hacia arriba
    for($i=$total-1;$i>=0;$i--){
        $ln=$lines[$i];
        if(strpos($ln,'Currently linked repeaters')!==false){
            $found=true;
            dbg("Bloque encontrado en índice: $i");

            // leer estaciones
            for($j=$i+1;$j<$total;$j++){
                $ln2=$lines[$j];

                // Fin del bloque ante nueva línea M: con timestamp
                if(
                    preg_match('/^M:\s*\d{4}-\d{2}-\d{2}/',$ln2)
                    && strpos($ln2,'Currently linked repeaters')!==false
                ){
                    break;
                }

                // Parseo del bloque
                if(preg_match(
                    '/^M:\s*\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d+\s+([A-Z0-9]{3,8})\s*:\s*([\d\.]+:\d+)\s+(\d+)\/(\d+)/',
                    $ln2,$m
                )){
                    $linked[]=[
                        'cs'=>$m[1],
                        'ip'=>$m[2],
                        'slot'=>$m[3],
                        'timeout'=>$m[4]
                    ];
                }
            }
            break;
        }
    }

    dbg("Estaciones detectadas: ".count($linked));
    return $found && !empty($linked)?$linked:null;
}

// ======================================================
// MAIN
// ======================================================
$state=ensure_state();
$today=date('Y-m-d');
$day_now=date('w');     // 0 = domingo
$hour_now=(int)date('H');

$log=get_latest_log();
if(!$log){
    dbg("❌ No se encontró log válido.");
    exit;
}

dbg("Usando log: $log");

// ======================================================
// 1) RESUMEN HORARIO DE ESTACIONES
// ======================================================
$linked=get_currently_linked($log);

if($linked){
    $msg="📡 <b>LYNK25 — Estaciones Conectadas</b>\n";

    foreach($linked as $stn){
        $msg.="• <b>{$stn['cs']}</b> — {$stn['ip']} ({$stn['slot']}/{$stn['timeout']})\n";
    }

    $msg.="🕒 ".date('Y-m-d H:i:s');

    if((time() - ($state['summary']['last'] ?? 0)) >= TG_REMINDER_INTERVAL){
        telegram_send($msg);
        $state['summary']['last'] = time();
        save_state($state);
        dbg("Resumen horario enviado.");
    } else {
        dbg("Resumen NO enviado (intervalo aún no cumple).");
    }

} else {
    dbg("⚠️ No hay estaciones activas.");
}

// ======================================================
// 2) REPORTE SEMANAL (DOMINGO 20:00)
// ======================================================
if($day_now==0 && $hour_now==20){

    if(($state['weekly']['last'] ?? null) !== $today){

        // Ordenar histórico de QSOs desde heard.php
        if(!empty($heard) && is_array($heard)){
            uasort($heard, fn($a,$b)=>($b['count'] ?? 0) <=> ($a['count'] ?? 0));

            $top=array_slice($heard,0,3,true);

            $msg="📅 <b>LYNK25 — Reporte Semanal</b>\n";
            $msg.="🏆 Top 3 operadores:\n\n";

            $i=1;
            foreach($top as $cs=>$d){
                $medal=$i==1?'🥇':($i==2?'🥈':'🥉');
                $msg.="{$medal} <b>$cs</b> — {$d['count']} QSOs\n";
                $i++;
            }

            telegram_send($msg);

            $state['weekly']['last']=$today;
            save_state($state);

            dbg("Reporte semanal enviado.");
        }
    }
}

// ======================================================
// 3) REPORTE DIARIO DEL SERVIDOR (12:00)
// ======================================================
if($hour_now==12 && (($state['daily']['last'] ?? null) !== $today)){
    
    // Temperatura CPU
    $temp='N/A';
    if(file_exists('/sys/class/thermal/thermal_zone0/temp')){
        $temp=round(file_get_contents('/sys/class/thermal/thermal_zone0/temp')/1000,1);
    }

    // Uptime
    $uptime=trim(shell_exec("uptime -p"));
    $uptime=str_replace("up ","",$uptime);

    // RAM
    $free=shell_exec("free -m");
    preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/',$free,$m);
    $ram_total=$m[1] ?? 0;
    $ram_used=$m[2] ?? 0;
    $ram_pct=$ram_total ? round(($ram_used/$ram_total)*100,1) : 0;

    // Carga CPU
    $load=trim(shell_exec("cat /proc/loadavg | awk '{print $1\" \"$2\" \"$3}'"));

    $msg="🖥️ <b>LYNK25 — Estado del Servidor</b>\n";
    $msg.="📅 ".date('Y-m-d H:i')."\n";
    $msg.="🌡️ Temp CPU: {$temp} °C\n";
    $msg.="⚙️ Uptime: {$uptime}\n";
    $msg.="💾 RAM: {$ram_used}/{$ram_total} MB ({$ram_pct}%)\n";
    $msg.="🔌 Carga CPU: {$load}\n";

    telegram_send($msg);

    $state['daily']['last']=$today;
    save_state($state);

    dbg("Reporte diario enviado.");
}

// ======================================================
// 4) DETECTOR DE CAMBIO DE IP PÚBLICA (CONFIRMADO 15 MIN)
// ======================================================

// Obtener IP pública actual
$ip_actual = trim(shell_exec("curl -s https://api.ipify.org"));

// Si no se pudo obtener, no seguimos
if ($ip_actual) {

    $registro_ip = &$state['ip_change'];

    // Si es la primera vez, guardar IP inicial
    if ($registro_ip['last_ip'] === null) {
        $registro_ip['last_ip'] = $ip_actual;
        $registro_ip['detected_ts'] = 0;
        $registro_ip['notified'] = false;
        save_state($state);
        dbg("IP inicial registrada: $ip_actual");
    }

    // Si la IP NO cambió → resetear temporizador y notificación
    elseif ($ip_actual === $registro_ip['last_ip']) {

        if ($registro_ip['notified']) {
            dbg("IP estable luego de cambio.");
        }

        $registro_ip['detected_ts'] = 0;
        $registro_ip['notified'] = false;
        save_state($state);
        dbg("IP sin cambios: $ip_actual");
    }

    // La IP cambió → iniciar temporizador de 15 minutos
    else {
        dbg("⚠️ IP CAMBIÓ: {$registro_ip['last_ip']} → {$ip_actual}");

        // Si recién detectamos el cambio
        if ($registro_ip['detected_ts'] === 0) {
            $registro_ip['detected_ts'] = time();
            save_state($state);
            dbg("Iniciando temporizador de verificación (15 min)...");
        }

        // Si ya pasaron 15 min y aún NO notificamos
        elseif (!$registro_ip['notified'] && (time() - $registro_ip['detected_ts'] >= 15*60)) {

            // Enviamos notificación Telegram
            $msg = "⚠️ <b>ATENCIÓN — CAMBIO DE IP PÚBLICA</b>\n\n"
                 . "🔄 IP anterior: <code>{$registro_ip['last_ip']}</code>\n"
                 . "🌐 Nueva IP: <code>{$ip_actual}</code>\n\n"
                 . "📌 Actualiza tu <b>hostfile</b> o configuraciones de conexión.\n"
                 . "⏱️ Cambio confirmado tras 15 minutos.";

            telegram_send($msg);
            dbg("Notificación enviada a Telegram.");

            // Actualizamos estado
            $registro_ip['last_ip'] = $ip_actual;
            $registro_ip['notified'] = true;
            $registro_ip['detected_ts'] = 0;

            save_state($state);
        }
    }
}



dbg("Ejecución finalizada.");
