<?php
require_once 'config.php';

// Activar visualización de errores temporalmente (para depurar)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Asegurar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Obtener el ID del usuario desde la URL
$user_id = $_GET['id'] ?? null;

// Validar que el ID sea un número entero
if (!$user_id || !filter_var($user_id, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de usuario inválido']);
    exit();
}

// Verificar permisos: solo admin puede ver otros usuarios
if ($user_id != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

// Verificar que la tabla events existe
try {
    $conn->query("SELECT 1 FROM events LIMIT 1");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'La tabla events no existe']);
    exit();
}

// Consulta segura con PDO
try {
    $stmt = $conn->prepare("SELECT id, title, start, end FROM events WHERE user_id = ? ORDER BY start");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Asegurar que las fechas estén en formato ISO 8601
    foreach ($events as &$event) {
        $event['start'] = date('c', strtotime($event['start']));
        if ($event['end']) {
            $event['end'] = date('c', strtotime($event['end']));
        }
    }

    header('Content-Type: application/json');
    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar eventos: ' . $e->getMessage()]);
}
?>