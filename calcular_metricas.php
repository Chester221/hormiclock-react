<?php
// calcular_metricas.php - Guarda métricas semanales automáticamente
require_once 'config.php';

function calcularYGuardarMetricas($conn, $user_id, $fecha_semana) {
    // Calcular inicio y fin de semana (lunes a domingo)
    $semana_inicio = date('Y-m-d', strtotime('monday this week', strtotime($fecha_semana)));
    $semana_fin = date('Y-m-d', strtotime('sunday this week', strtotime($fecha_semana)));
    
    // Obtener registros de la semana
    $stmt = $conn->prepare("
        SELECT fecha, horas_trabajadas, horas_extras 
        FROM registros_tiempo 
        WHERE user_id = ? AND fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $semana_inicio, $semana_fin]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registros)) {
        return false; // No hay datos esta semana
    }
    
    // Calcular totales
    $total_horas = 0;
    $total_extras = 0;
    $dias_trabajados = 0;
    
    foreach ($registros as $reg) {
        $total_horas += floatval($reg['horas_trabajadas'] ?? 0);
        $total_extras += floatval($reg['horas_extras'] ?? 0);
        if (($reg['horas_trabajadas'] ?? 0) > 0) {
            $dias_trabajados++;
        }
    }
    
    // Calcular métricas
    $objetivo_semanal = 40;
    $asistencia = round(($dias_trabajados / 5) * 100, 1);
    $asistencia = min(100, $asistencia);
    
    $productividad = round(($total_horas / $objetivo_semanal) * 100, 1);
    $productividad = min(100, $productividad);
    
    $puntualidad = round(($asistencia + $productividad) / 2, 1);
    $puntualidad = min(100, $puntualidad);
    
    // Colaboración: promedio de asistencia y puntualidad (puedes ajustar la fórmula)
    $colaboracion = round(($asistencia + $puntualidad) / 2, 1);
    $colaboracion = min(100, $colaboracion);
    
    // Guardar o actualizar en metricas_semanales
    $stmt = $conn->prepare("
        INSERT INTO metricas_semanales 
        (user_id, semana_inicio, puntualidad, colaboracion, asistencia, productividad, horas_totales, horas_extras) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        puntualidad = VALUES(puntualidad),
        colaboracion = VALUES(colaboracion),
        asistencia = VALUES(asistencia),
        productividad = VALUES(productividad),
        horas_totales = VALUES(horas_totales),
        horas_extras = VALUES(horas_extras),
        fecha_registro = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $user_id, 
        $semana_inicio, 
        $puntualidad, 
        $colaboracion, 
        $asistencia, 
        $productividad, 
        $total_horas, 
        $total_extras
    ]);
    
    return true;
}
?>