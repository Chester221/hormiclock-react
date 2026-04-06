<?php
// ============================================
// ACTUALIZAR EVENTO POR ARRASTRE - VERSIÓN CORREGIDA
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

$id = $_POST['id'] ?? '';
$start = $_POST['start'] ?? '';
$end = $_POST['end'] ?? '';

if (!$id || !$start) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
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
    
    // Convertir fechas de ISO a formato MySQL
    $start_date = date('Y-m-d H:i:s', strtotime($start));
    $end_date = !empty($end) ? date('Y-m-d H:i:s', strtotime($end)) : null;
    
    // Actualizar evento
    $stmt = $conn->prepare("
        UPDATE events 
        SET start_date = ?, end_date = ? 
        WHERE id = ?
    ");
    $success = $stmt->execute([$start_date, $end_date, $id]);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Evento actualizado' : 'Error al actualizar'
    ]);
    
} catch (Exception $e) {
    error_log("Error en actualizar_evento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
}
?>