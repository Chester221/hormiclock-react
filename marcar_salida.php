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

    // Si está en descanso, finalizar descanso primero
    if ($marcacion && $marcacion['descanso_inicio'] && !$marcacion['descanso_fin']) {
        $inicio = new DateTime($marcacion['descanso_inicio']);
        $fin = new DateTime();
        $intervalo = $inicio->diff($fin);
        $minutos_descanso = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
        $nuevo_total = ($marcacion['tiempo_descanso'] ?? 0) + $minutos_descanso;
        
        $stmt = $conn->prepare("
            UPDATE marcaciones 
            SET descanso_fin = NOW(), 
                tiempo_descanso = ?,
                hora_salida = NOW(), 
                estado = 'finalizado'
            WHERE user_id = ? AND fecha = CURDATE()
        ");
        $stmt->execute([$nuevo_total, $userId]);
    } else {
        // Finalizar jornada directamente
        $stmt = $conn->prepare("
            UPDATE marcaciones 
            SET hora_salida = NOW(), estado = 'finalizado'
            WHERE user_id = ? AND fecha = CURDATE()
        ");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>