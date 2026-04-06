<?php
// ============================================
// ELIMINAR EVENTO - VERSIÓN CORREGIDA
// ============================================

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit();
}

try {
    // Verificar que el evento existe
    $check = $conn->prepare("SELECT user_id FROM events WHERE id = ?");
    $check->execute([$id]);
    $evento = $check->fetch();
    
    if (!$evento) {
        echo json_encode(['success' => false, 'error' => 'Evento no encontrado']);
        exit();
    }
    
    // Verificar permisos
    if ($evento['user_id'] != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
        exit();
    }
    
    // Eliminar evento
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $success = $stmt->execute([$id]);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Evento eliminado' : 'Error al eliminar'
    ]);
    
} catch (Exception $e) {
    error_log("Error en eliminar_evento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
}
?>