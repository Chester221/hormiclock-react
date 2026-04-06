<?php
// confirmar_turno.php
require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'] ?? 0;

try {
    // Crear tabla si no existe
    $conn->exec("
        CREATE TABLE IF NOT EXISTS event_confirmations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            confirmado BOOLEAN DEFAULT TRUE,
            confirmado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_user (event_id, user_id),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Insertar confirmación
    $stmt = $conn->prepare("
        INSERT INTO event_confirmations (event_id, user_id) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE confirmado_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$event_id, $user_id]);
    
    // Obtener información del turno y empleado
    $stmt = $conn->prepare("
        SELECT e.*, u.name as empleado_nombre
        FROM events e
        JOIN users u ON e.user_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $evento = $stmt->fetch();
    
    // NOTIFICACIÓN: Avisar a admins
    $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        Notificaciones::turnoConfirmado(
            $admin['id'],
            $evento['empleado_nombre'],
            $evento['start']
        );
    }
    
    // Registrar actividad
    logActivity($conn, $_SESSION['email'], 'confirmar_turno', "Turno confirmado ID: $event_id");
    
    header("Location: calendar.php?confirmado=1");
    
} catch (Exception $e) {
    error_log("Error confirmando turno: " . $e->getMessage());
    header("Location: calendar.php?error=1");
}
?>