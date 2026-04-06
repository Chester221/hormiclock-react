<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Verificar si ya tiene entrada hoy
    $stmt = $conn->prepare("
        SELECT id FROM marcaciones 
        WHERE user_id = ? AND fecha = CURDATE()
    ");
    $stmt->execute([$userId]);
    $existe = $stmt->fetch();

    if ($existe) {
        // Actualizar entrada
        $stmt = $conn->prepare("
            UPDATE marcaciones 
            SET hora_entrada = NOW(), estado = 'trabajando'
            WHERE user_id = ? AND fecha = CURDATE()
        ");
        $stmt->execute([$userId]);
    } else {
        // Insertar nueva marcación
        $stmt = $conn->prepare("
            INSERT INTO marcaciones (user_id, fecha, hora_entrada, estado)
            VALUES (?, CURDATE(), NOW(), 'trabajando')
        ");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>