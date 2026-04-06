<?php
require_once 'config.php';
require_once 'notificaciones_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $user_id = $_POST['user_id'] ?? '';

    if (empty($title) || empty($start) || !filter_var($user_id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO events (title, start, end, user_id) VALUES (?, ?, ?, ?)");
    
    if ($stmt->execute([$title, $start, $end, $user_id])) {
        $id = $conn->lastInsertId();
        
        // ============================================
        // NOTIFICACIÓN: Nuevo turno asignado
        // ============================================
        $hora_inicio = date('H:i', strtotime($start));
        $hora_fin = date('H:i', strtotime($end));
        Notificaciones::nuevoTurno($user_id, $start, $hora_inicio, $hora_fin);
        
        echo json_encode(['id' => $id, 'success' => true]);
    } else {
        http_response_code(500);
    }
}