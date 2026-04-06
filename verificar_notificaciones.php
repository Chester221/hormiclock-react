<?php
// ============================================
// VERIFICAR NUEVAS NOTIFICACIONES (AJAX)
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'No autenticado']);
    exit();
}

$userId = $_SESSION['user_id'];
$ultimoId = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;

try {
    // Verificar si hay notificaciones nuevas
    $stmt = $conn->prepare("
        SELECT COUNT(*) as nuevas, MAX(id) as ultimo_id
        FROM notificaciones 
        WHERE usuario_id = ? AND id > ?
    ");
    $stmt->execute([$userId, $ultimoId]);
    $resultado = $stmt->fetch();
    
    echo json_encode([
        'exito' => true,
        'nuevas' => $resultado['nuevas'],
        'ultimo_id' => $resultado['ultimo_id'] ?: $ultimoId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'error' => $e->getMessage()]);
}
?>