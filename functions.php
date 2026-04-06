<?php
// functions.php - Solo funciones nuevas, NO toca tus archivos
function getDeviceId() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $user_agent . '|' . $ip);
}
// ... más funciones
?>