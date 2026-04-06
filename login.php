<?php
// ============================================
// PROCESADOR DE LOGIN - VERSIÓN CORREGIDA
// ============================================

require_once 'config.php';

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['login_error'] = 'Error de validación. Intenta de nuevo.';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

// Eliminar token usado
unset($_SESSION['csrf_token']);

// Verificar que se envió el formulario
if (!isset($_POST['login_submit'])) {
    header("Location: index.php");
    exit();
}

// Obtener datos
$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

// Validar campos vacíos
if ($login === '' || $password === '') {
    $_SESSION['login_error'] = 'Todos los campos son obligatorios';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

try {
    // Buscar usuario por email
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Usuario o contraseña incorrectos';
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }

    if (!password_verify($password, $user['password'])) {
        $_SESSION['login_error'] = 'Usuario o contraseña incorrectos';
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }

    // Login exitoso
    logActivity($conn, $user['email'], 'login', 'Inicio de sesión exitoso');
    session_regenerate_id(true);

    // Guardar datos en sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['profile_img'] = 'uploads/default.svg'; // Valor por defecto

    // ============================================
    // NOTIFICACIÓN: Verificar días de vacaciones
    // ============================================
    if (class_exists('Notificaciones')) {
        Notificaciones::verificarVacacionesBajas();
    }

    // Redirigir según rol
    $location = ($user['role'] === 'admin') ? "admin_page.php" : "user_page.php";
    header("Location: $location");
    exit();

} catch (PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    $_SESSION['login_error'] = 'Error en el sistema. Intenta más tarde.';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}
?>