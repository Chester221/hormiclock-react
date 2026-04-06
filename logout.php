<?php
require_once 'config.php';

// ✅ Registrar actividad antes de destruir la sesión (si existe email)
if (isset($_SESSION['email'])) {
    logActivity($conn, $_SESSION['email'], 'logout', 'Cierre de sesión');
}

// Limpiar sesión
$_SESSION = array();

// Eliminar cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: index.php");
exit();