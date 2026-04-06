<?php
// ============================================
// CONFIGURACIÓN PRINCIPAL - ZONA HORARIA CORREGIDA
// ============================================

// 🔥 ZONA HORARIA CORRECTA (Caracas / Bogotá / Lima)
date_default_timezone_set('America/Caracas');

// 1. Configuraciones de seguridad de sesión (ANTES de session_start)
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 1440);

// 2. Parámetros de la cookie (ANTES de session_start)
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 3. Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Incluir configuración de tiempo de sesión
require_once 'tiempo_sesion.php';

// 5. Actualizar última actividad en cada página
updateLastActivity();

// 6. Credenciales de Base de Datos
define('DB_HOST', 'sql309.infinityfree.com');
define('DB_USER', 'if0_41091316');
define('DB_PASS', 'In8L5c2Uqi');
define('DB_NAME', 'if0_41091316_users_db');

// 7. Conexión con PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $conn = new PDO($dsn, DB_USER, DB_PASS, $opt);
} catch (PDOException $e) {
    error_log("Error de conexión PDO: " . $e->getMessage());
    die("Error interno del servidor. Por favor, intenta más tarde.");
}

// 8. Función para registrar actividad de usuario
function logActivity($conn, $email, $action, $details = null) {
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_email VARCHAR(255) NOT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $conn->prepare("INSERT INTO user_activity (user_email, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$email, $action, $details]);
    } catch (Exception $e) {
        error_log("Error en logActivity: " . $e->getMessage());
    }
}

// 9. Función para obtener IP real del usuario
function getRealIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header])) {
            $ip_list = explode(',', $_SERVER[$header]);
            foreach ($ip_list as $ip_candidate) {
                $ip_candidate = trim($ip_candidate);
                if (filter_var($ip_candidate, FILTER_VALIDATE_IP)) {
                    return $ip_candidate;
                }
            }
        }
    }
    return $ip;
}
?>