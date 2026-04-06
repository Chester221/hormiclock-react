<?php
// ============================================
// MARCAR NOTIFICACIÓN COMO LEÍDA (AJAX)
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'No autenticado']);
    exit();
}

$datos = json_decode(file_get_contents('php://input'), true);
$notificacion_id = $datos['id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$notificacion_id) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'ID no válido']);
    exit();
}

try {
    $stmt = $conn->prepare("
        UPDATE notificaciones 
        SET leida = TRUE, fecha_lectura = NOW() 
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->execute([$notificacion_id, $user_id]);
    
    echo json_encode(['exito' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'error' => $e->getMessage()]);
}
?>