<?php
// test_salida.php - Probar que la salida funciona
require_once 'config.php';

session_start();

// Buscar un registro sin salida
$user_id = 41; // El ID de galletasdechocolates

$stmt = $conn->prepare("SELECT * FROM registros_tiempo WHERE user_id = ? AND hora_salida IS NULL ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if ($registro) {
    echo "<h2>Registro encontrado sin salida:</h2>";
    echo "<pre>";
    print_r($registro);
    echo "</pre>";
    
    echo "<h2>Calculando horas:</h2>";
    
    $entrada_ts = strtotime($registro['hora_entrada']);
    $salida_ts = time();
    
    $total_minutos = ($salida_ts - $entrada_ts) / 60;
    $total_horas = round($total_minutos / 60, 2);
    $horas_normales = min(8, $total_horas);
    $horas_extras = max(0, $total_horas - 8);
    
    echo "Hora entrada: " . $registro['hora_entrada'] . "<br>";
    echo "Hora actual: " . date('Y-m-d H:i:s') . "<br>";
    echo "Total minutos: $total_minutos<br>";
    echo "Total horas: $total_horas<br>";
    echo "Horas normales: $horas_normales<br>";
    echo "Horas extras: $horas_extras<br>";
    
    echo "<h2>SQL de actualización:</h2>";
    echo "UPDATE registros_tiempo SET 
        hora_salida = '" . date('Y-m-d H:i:s') . "', 
        horas_trabajadas = $total_horas, 
        horas_normales = $horas_normales, 
        horas_extras = $horas_extras, 
        estado = 'completado' 
        WHERE id = " . $registro['id'];
        
} else {
    echo "No hay registros sin salida";
}
?>