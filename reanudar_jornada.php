<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Obtener marcación actual
    $stmt = $conn->prepare("
        SELECT descanso_inicio, tiempo_descanso 
        FROM marcaciones 
        WHERE user_id = ? AND fecha = CURDATE()
    ");
    $stmt->execute([$userId]);
    $marcacion = $stmt->fetch();

    if ($marcacion && $marcacion['descanso_inicio']) {
        // Calcular tiempo de descanso
        $inicio = new DateTime($marcacion['descanso_inicio']);
        $fin = new DateTime();
        $intervalo = $inicio->diff($fin);
        $minutos_descanso = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
        
        // Actualizar tiempo total de descanso
        $nuevo_total = ($marcacion['tiempo_descanso'] ?? 0) + $minutos_descanso;
        
        $stmt = $conn->prepare("
            UPDATE marcaciones 
            SET descanso_fin = NOW(), 
                tiempo_descanso = ?,
                estado = 'trabajando'
            WHERE user_id = ? AND fecha = CURDATE()
        ");
        $stmt->execute([$nuevo_total, $userId]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>