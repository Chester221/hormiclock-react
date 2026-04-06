<?php
// test_grafico.php - Prueba directa de datos
require_once 'config.php';
session_start();

$userId = $_SESSION['user_id'] ?? 41; // Usa el ID de Mario

echo "<h2>DATOS DE REGISTROS_TIEMPO</h2>";

$stmt = $conn->prepare("SELECT id, fecha, hora_entrada, hora_salida, horas_trabajadas, horas_normales, horas_extras FROM registros_tiempo WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$userId]);
$datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($datos) > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Trabajadas</th><th>Normales</th><th>Extras</th></tr>";
    foreach ($datos as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "<td>" . ($row['hora_entrada'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['hora_salida'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['horas_trabajadas'] . "</td>";
        echo "<td>" . $row['horas_normales'] . "</td>";
        echo "<td>" . $row['horas_extras'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay registros para user_id = $userId</p>";
}

echo "<h2>USUARIOS DISPONIBLES</h2>";
$stmt = $conn->query("SELECT id, name, email FROM users LIMIT 10");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Email</th></tr>";
foreach ($users as $u) {
    echo "<tr><td>" . $u['id'] . "</td><td>" . $u['name'] . "</td><td>" . $u['email'] . "</td></tr>";
}
echo "</table>";
?>