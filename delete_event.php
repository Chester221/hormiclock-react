<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';

    if (!filter_var($id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    if (!$stmt->execute([$id])) {
        http_response_code(500);
    }
}   