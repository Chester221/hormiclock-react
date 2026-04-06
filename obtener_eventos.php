<?php
// ============================================
// OBTENER EVENTOS DEL CALENDARIO - VERSIÓN CORREGIDA
// ============================================

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$user_id = $_GET['id'] ?? $_SESSION['user_id'];

// Verificar permisos
if ($user_id != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

// Obtener parámetros de fecha del calendario (opcional)
$start_date = $_GET['start'] ?? null;
$end_date = $_GET['end'] ?? null;

try {
    // Construir consulta base
    $sql = "SELECT id, title, start_date as start, end_date as end FROM events WHERE user_id = ?";
    $params = [$user_id];
    
    // Filtrar por rango de fechas si se proporciona
    if ($start_date && $end_date) {
        $sql .= " AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ?)";
        $params[] = $start_date;
        $params[] = $end_date;
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    $sql .= " ORDER BY start_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas para FullCalendar
    foreach ($eventos as &$evento) {
        $evento['start'] = date('c', strtotime($evento['start']));
        if ($evento['end']) {
            $evento['end'] = date('c', strtotime($evento['end']));
        } else {
            unset($evento['end']);
        }
        
        // Añadir color por defecto si no tiene
        $evento['color'] = '#3b82f6';
        $evento['textColor'] = '#ffffff';
    }
    
    header('Content-Type: application/json');
    echo json_encode($eventos);
    
} catch (Exception $e) {
    error_log("Error en obtener_eventos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar eventos']);
}
?>