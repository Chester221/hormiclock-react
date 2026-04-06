<?php
session_start();
require_once 'config.php';

// Obtener cédula del QR y ubicación si el navegador la envía
$cedula = $_GET['cedula'] ?? '';
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;

if (empty($cedula)) {
    header("Location: index.php?error=qr_invalido");
    exit();
}

// Buscar usuario por cédula
$sql = "SELECT id, name, id_number, role FROM users WHERE id_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $cedula);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php?error=usuario_no_encontrado");
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

// VERIFICAR SI YA TIENE REGISTRO HOY
$hoy = date('Y-m-d');
$sql_check = "SELECT * FROM registros_tiempo WHERE user_id = ? AND fecha = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("is", $user_id, $hoy);
$stmt_check->execute();
$check = $stmt_check->get_result();

if ($check->num_rows == 0) {
    // NO tiene registro hoy - CREAR ENTRADA
    $hora_entrada = date('Y-m-d H:i:s'); // Formato completo datetime
    $estado = 'trabajando';
    $origen = 'qr';
    
    $sql_insert = "INSERT INTO registros_tiempo 
                   (user_id, fecha, hora_entrada, estado, ubicacion_lat, ubicacion_lng, origen_marcacion) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("issssss", $user_id, $hoy, $hora_entrada, $estado, $lat, $lng, $origen);
    
    if ($stmt_insert->execute()) {
        // Iniciar sesión del usuario
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['name'];
        $_SESSION['cedula'] = $user['id_number'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Redirigir según rol
        $dashboard = ($user['role'] == 'admin') ? 'admin_page.php' : 'user_page.php';
        header("Location: $dashboard?qr=entrada_exitosa");
    } else {
        header("Location: index.php?error=error_registro");
    }
} else {
    // YA tiene registro hoy - verificar estado
    $registro = $check->fetch_assoc();
    
    if ($registro['estado'] == 'trabajando') {
        // Ya está trabajando, redirigir a su dashboard
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['name'];
        $_SESSION['cedula'] = $user['id_number'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        $dashboard = ($user['role'] == 'admin') ? 'admin_page.php' : 'user_page.php';
        header("Location: $dashboard?qr=ya_en_jornada");
    } elseif ($registro['estado'] == 'descanso') {
        header("Location: index.php?qr=en_descanso");
    } else {
        header("Location: index.php?qr=jornada_completada");
    }
}
exit();
?>