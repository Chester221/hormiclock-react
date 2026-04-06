<?php
// ============================================
// GUARDAR EVENTO - VERSIÓN CORREGIDA
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Obtener datos del POST
$id = $_POST['id'] ?? '';
$title = trim($_POST['title'] ?? '');
$start = $_POST['start'] ?? '';
$end = $_POST['end'] ?? '';
$user_id = $_POST['user_id'] ?? $_SESSION['user_id'];

// Validar permisos
if ($user_id != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit();
}

// Validar datos
if (empty($title) || empty($start)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

try {
    // Convertir fechas a formato MySQL
    $start_date = date('Y-m-d H:i:s', strtotime($start));
    $end_date = !empty($end) ? date('Y-m-d H:i:s', strtotime($end)) : null;
    
    if (empty($id)) {
        // Crear nuevo evento
        $stmt = $conn->prepare("
            INSERT INTO events (title, start_date, end_date, user_id) 
            VALUES (?, ?, ?, ?)
        ");
        $success = $stmt->execute([$title, $start_date, $end_date, $user_id]);
        
        if ($success) {
            $nuevo_id = $conn->lastInsertId();
            
            // Crear notificación de nuevo turno
            $hora_inicio = date('H:i', strtotime($start_date));
            $hora_fin = $end_date ? date('H:i', strtotime($end_date)) : '17:00';
            Notificaciones::nuevoTurno($user_id, $start_date, $hora_inicio, $hora_fin);
            
            echo json_encode([
                'success' => true, 
                'id' => $nuevo_id,
                'message' => 'Evento creado correctamente'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al crear evento']);
        }
    } else {
        // Verificar que el evento existe
        $check = $conn->prepare("SELECT id, user_id FROM events WHERE id = ?");
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
        
        // Actualizar evento existente
        $stmt = $conn->prepare("
            UPDATE events 
            SET title = ?, start_date = ?, end_date = ? 
            WHERE id = ?
        ");
        $success = $stmt->execute([$title, $start_date, $end_date, $id]);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Evento actualizado' : 'Error al actualizar'
        ]);
    }
} catch (Exception $e) {
    error_log("Error en guardar_evento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
}
?>