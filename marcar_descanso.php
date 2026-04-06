<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Actualizar inicio de descanso
    $stmt = $conn->prepare("
        UPDATE marcaciones 
        SET descanso_inicio = NOW(), estado = 'descanso'
        WHERE user_id = ? AND fecha = CURDATE()
    ");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>