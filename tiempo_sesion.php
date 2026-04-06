<?php
// ============================================
// CONFIGURACIÓN DE TIEMPO DE SESIÓN
// ============================================

// Tiempo de expiración de sesión (en segundos)
// 60 segundos = 1 minuto (para pruebas)
// 3600 segundos = 1 hora (para producción)
define('SESSION_LIFETIME', 60); // 60 segundos = 1 minuto

// Tiempo de advertencia antes de expirar (en segundos)
define('SESSION_WARNING_TIME', 10); // 10 segundos antes

// Configurar el tiempo de vida de la sesión en el servidor
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);

// Función para verificar si la sesión está por expirar
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        $remainingTime = SESSION_LIFETIME - $inactiveTime;
        
        if ($remainingTime <= 0) {
            // Sesión expirada
            session_destroy();
            header("Location: logout.php?reason=timeout");
            exit();
        }
        
        return $remainingTime;
    }
    return SESSION_LIFETIME;
}

// Función para actualizar la última actividad
function updateLastActivity() {
    $_SESSION['last_activity'] = time();
}

// Función para obtener el tiempo restante formateado
function getRemainingTimeFormatted() {
    $remaining = checkSessionTimeout();
    $minutes = floor($remaining / 60);
    $seconds = $remaining % 60;
    return sprintf("%02d:%02d", $minutes, $seconds);
}

// Función para obtener el porcentaje de tiempo restante (para la barra circular)
function getRemainingPercentage() {
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        $percentage = max(0, (SESSION_LIFETIME - $inactiveTime) / SESSION_LIFETIME * 100);
        return round($percentage);
    }
    return 100;
}

// Función para obtener el tiempo de advertencia (para JavaScript)
function getWarningTime() {
    return SESSION_WARNING_TIME;
}

// Función para obtener el tiempo total (para JavaScript)
function getTotalTime() {
    return SESSION_LIFETIME;
}
?>