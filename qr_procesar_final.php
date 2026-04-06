<?php
// qr_procesar_final.php - VERSIÓN CON DETECCIÓN DE TURNOS
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accion = $_GET['accion'] ?? '';

if (!isset($_SESSION['user_id'])) {
    header("Location: qr_final.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$hoy = date('Y-m-d');
$ahora = date('Y-m-d H:i:s');
$hora_actual = date('H:i:s');

// Función para obtener el turno activo del día
function obtenerTurnoActivo($conn, $user_id, $fecha, $hora_actual) {
    $stmt = $conn->prepare("
        SELECT * FROM turnos 
        WHERE user_id = ? AND fecha = ? 
        AND estado IN ('pendiente', 'confirmado')
        ORDER BY hora_inicio DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $fecha]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para registrar retardo
function registrarRetardo($conn, $user_id, $turno_id, $fecha, $minutos, $hora_programada, $hora_real) {
    $stmt = $conn->prepare("
        INSERT INTO retardos (user_id, turno_id, fecha, minutos_retardo, hora_programada, hora_real, estado) 
        VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([$user_id, $turno_id, $fecha, $minutos, $hora_programada, $hora_real]);
    
    // Crear notificación
    $stmt_notif = $conn->prepare("
        INSERT INTO notificaciones (user_id, tipo, mensaje, leida) 
        VALUES (?, 'retardo', ?, 0)
    ");
    $mensaje = "⚠️ Retardo de $minutos minutos en tu turno de hoy. Programa: $hora_programada, Real: $hora_real";
    $stmt_notif->execute([$user_id, $mensaje]);
}

// Función para registrar salida temprana
function registrarSalidaTemprana($conn, $user_id, $turno_id, $fecha, $minutos, $hora_programada, $hora_real) {
    $stmt = $conn->prepare("
        INSERT INTO salidas_tempranas (user_id, turno_id, fecha, minutos_anticipacion, hora_programada, hora_real, estado) 
        VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([$user_id, $turno_id, $fecha, $minutos, $hora_programada, $hora_real]);
    
    $stmt_notif = $conn->prepare("
        INSERT INTO notificaciones (user_id, tipo, mensaje, leida) 
        VALUES (?, 'salida_temprana', ?, 0)
    ");
    $mensaje = "⚠️ Saliste $minutos minutos antes de lo programado ($hora_programada). Justifica por favor.";
    $stmt_notif->execute([$user_id, $mensaje]);
}

// Función para registrar horas extras no programadas
function registrarHorasExtrasNoProgramadas($conn, $user_id, $fecha, $minutos_extra) {
    $stmt = $conn->prepare("
        INSERT INTO horas_extras_no_programadas (user_id, fecha, minutos_extra, estado) 
        VALUES (?, ?, ?, 'pendiente')
    ");
    $stmt->execute([$user_id, $fecha, $minutos_extra]);
    
    $stmt_notif = $conn->prepare("
        INSERT INTO notificaciones (user_id, tipo, mensaje, leida) 
        VALUES (?, 'extra_no_programada', ?, 0)
    ");
    $mensaje = "⚠️ Registraste $minutos_extra minutos extras no programados. ¿Olvidaste marcar salida o fueron horas extras?";
    $stmt_notif->execute([$user_id, $mensaje]);
}

try {
    $stmt = $conn->prepare("SELECT * FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
    $stmt->execute([$user_id, $hoy]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $turno_activo = obtenerTurnoActivo($conn, $user_id, $hoy, $hora_actual);

    switch ($accion) {
        case 'entrada':
            if (!$registro) {
                $insert = $conn->prepare("INSERT INTO registros_tiempo (user_id, fecha, hora_entrada, estado, origen_marcacion) VALUES (?, ?, ?, 'trabajando', 'qr')");
                $insert->execute([$user_id, $hoy, $ahora]);
                
                // Verificar si hay turno y calcular retardo
                if ($turno_activo) {
                    $hora_programada = $turno_activo['hora_inicio'];
                    $hora_programada_ts = strtotime($hora_programada);
                    $hora_real_ts = strtotime($hora_actual);
                    
                    if ($hora_real_ts > $hora_programada_ts) {
                        $minutos_retardo = round(($hora_real_ts - $hora_programada_ts) / 60);
                        registrarRetardo($conn, $user_id, $turno_activo['id'], $hoy, $minutos_retardo, $hora_programada, $hora_actual);
                        
                        // Mensaje para el usuario (se mostrará en el dashboard)
                        $_SESSION['mensaje_retardo'] = "⚠️ Llegaste $minutos_retardo minutos tarde. Tu turno comenzaba a las " . date('H:i', strtotime($hora_programada));
                    }
                }
            }
            break;

        case 'descanso':
            if ($registro && $registro['estado'] == 'trabajando' && empty($registro['descanso_inizio'])) {
                $update = $conn->prepare("UPDATE registros_tiempo SET descanso_inizio = ?, estado = 'descanso' WHERE id = ?");
                $update->execute([$ahora, $registro['id']]);
            }
            break;

        case 'reanudar':
            if ($registro && $registro['estado'] == 'descanso' && !empty($registro['descanso_inizio']) && empty($registro['descanso_fin'])) {
                $inizio = strtotime($registro['descanso_inizio']);
                $fin = time();
                $tiempo_descanso = round(($fin - $inizio) / 60);
                
                $update = $conn->prepare("UPDATE registros_tiempo SET descanso_fin = ?, tiempo_descanso = ?, estado = 'trabajando' WHERE id = ?");
                $update->execute([$ahora, $tiempo_descanso, $registro['id']]);
            }
            break;

        case 'salida':
            if ($registro && $registro['estado'] == 'trabajando') {
                $entrada_ts = strtotime($registro['hora_entrada']);
                $salida_ts = time();
                $total_minutos = ($salida_ts - $entrada_ts) / 60;
                
                // Restar descanso si existe
                if (!empty($registro['descanso_inizio']) && empty($registro['descanso_fin'])) {
                    $inizio_desc = strtotime($registro['descanso_inizio']);
                    $fin_desc = $salida_ts;
                    $tiempo_descanso = round(($fin_desc - $inizio_desc) / 60);
                    $total_minutos -= $tiempo_descanso;
                    
                    $update_descanso = $conn->prepare("UPDATE registros_tiempo SET descanso_fin = ?, tiempo_descanso = ? WHERE id = ?");
                    $update_descanso->execute([$ahora, $tiempo_descanso, $registro['id']]);
                }
                
                $total_horas = round($total_minutos / 60, 2);
                $horas_normales = min(8, $total_horas);
                $horas_extras = max(0, $total_horas - 8);
                
                // Verificar contra el turno
                if ($turno_activo) {
                    $hora_fin_programada = $turno_activo['hora_fin'];
                    $hora_fin_programada_ts = strtotime($hora_fin_programada);
                    $hora_salida_ts = $salida_ts;
                    
                    // Calcular diferencia con la hora programada de fin
                    $diferencia_minutos = round(($hora_salida_ts - $hora_fin_programada_ts) / 60);
                    
                    if ($diferencia_minutos < -5) {
                        // Salió temprano (más de 5 minutos antes)
                        $minutos_anticipacion = abs($diferencia_minutos);
                        registrarSalidaTemprana($conn, $user_id, $turno_activo['id'], $hoy, $minutos_anticipacion, $hora_fin_programada, $hora_actual);
                        $_SESSION['mensaje_salida'] = "⚠️ Saliste $minutos_anticipacion minutos antes de lo programado. Justifica por favor.";
                    } elseif ($diferencia_minutos > 15) {
                        // Salió más de 15 minutos después (posible extra no programada)
                        registrarHorasExtrasNoProgramadas($conn, $user_id, $hoy, $diferencia_minutos);
                        $_SESSION['mensaje_extra'] = "⚠️ Registraste $diferencia_minutos minutos extras no programados. ¿Olvidaste marcar salida o fueron horas extras?";
                    } elseif (abs($diferencia_minutos) <= 5) {
                        // Salió dentro del margen de 5 minutos - COMPLETAR TURNO
                        $update_turno = $conn->prepare("UPDATE turnos SET estado = 'completado' WHERE id = ?");
                        $update_turno->execute([$turno_activo['id']]);
                        $_SESSION['mensaje_completado'] = "✅ ¡Turno completado! Excelente trabajo.";
                    }
                } else {
                    // No hay turno programado, verificar si son horas extras
                    if ($total_horas > 8) {
                        $minutos_extra = round(($total_horas - 8) * 60);
                        registrarHorasExtrasNoProgramadas($conn, $user_id, $hoy, $minutos_extra);
                        $_SESSION['mensaje_extra'] = "⚠️ Registraste $minutos_extra minutos extras no programados.";
                    }
                }
                
                $update = $conn->prepare("UPDATE registros_tiempo SET 
                    hora_salida = ?, 
                    horas_trabajadas = ?, 
                    horas_normales = ?, 
                    horas_extras = ?, 
                    estado = 'completado' 
                    WHERE id = ?");
                $update->execute([$ahora, $total_horas, $horas_normales, $horas_extras, $registro['id']]);
            }
            break;
    }
    
    header("Location: qr_final.php");
    exit();

} catch (PDOException $e) {
    error_log("Error en qr_procesar: " . $e->getMessage());
    header("Location: qr_final.php?error=sistema");
    exit();
}
?>