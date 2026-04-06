<?php
// ajax_turnos.php - Gestionar turnos (CRUD)
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$user_id = $_SESSION['user_id'];

$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'guardar':
            $id = $_POST['id'] ?? '';
            $fecha = $_POST['fecha'] ?? '';
            $hora_inicio = $_POST['hora_inicio'] ?? '';
            $hora_fin = $_POST['hora_fin'] ?? '';
            $tipo = $_POST['tipo'] ?? 'diurna';
            $ubicacion = $_POST['ubicacion'] ?? 'Oficina Principal';
            $departamento = $_POST['departamento'] ?? 'General';
            $estado = 'pendiente';
            
            if (empty($fecha) || empty($hora_inicio) || empty($hora_fin)) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
                exit();
            }
            
            if ($id && $id != '') {
                $stmt = $conn->prepare("UPDATE turnos SET fecha = ?, hora_inicio = ?, hora_fin = ?, tipo = ?, ubicacion = ?, departamento = ? WHERE id = ? AND user_id = ?");
                $result = $stmt->execute([$fecha, $hora_inicio, $hora_fin, $tipo, $ubicacion, $departamento, $id, $user_id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO turnos (user_id, fecha, hora_inicio, hora_fin, tipo, ubicacion, departamento, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$user_id, $fecha, $hora_inicio, $hora_fin, $tipo, $ubicacion, $departamento, $estado]);
            }
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al guardar']);
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no especificado']);
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM turnos WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$id, $user_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
            }
            break;
            
        case 'eliminar_pasados':
            $hoy = date('Y-m-d');
            $stmt = $conn->prepare("DELETE FROM turnos WHERE user_id = ? AND fecha < ?");
            if ($stmt->execute([$user_id, $hoy])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar turnos pasados']);
            }
            break;
            
        case 'cambiar_estado':
            $id = $_POST['id'] ?? '';
            $estado = $_POST['estado'] ?? '';
            
            if (empty($id) || empty($estado)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE turnos SET estado = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$estado, $id, $user_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al cambiar estado']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>