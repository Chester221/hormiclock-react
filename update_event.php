<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';

    if (!filter_var($id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        exit();
    }

    $stmt = $conn->prepare("UPDATE events SET start = ?, end = ? WHERE id = ?");
    if (!$stmt->execute([$start, $end, $id])) {
        http_response_code(500);
    }
}   