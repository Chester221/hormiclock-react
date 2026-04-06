<?php
// user_page.php - VERSIÓN FINAL CON ANIMACIONES
require_once 'config.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header("Location: admin_page.php");
    exit();
}

$userName = htmlspecialchars($_SESSION['name'] ?? 'Usuario');
$firstName = explode(' ', trim($userName))[0];
$userId = $_SESSION['user_id'] ?? 0;
$tema = $_COOKIE['tema'] ?? 'light';

$ahora_php = date('Y-m-d H:i:s');
$fecha_hoy = date('Y-m-d');

// ============================================
// ACCIONES
// ============================================
if (isset($_POST['accion'])) {
    
    if ($_POST['accion'] === 'entrada') {
        $conn->prepare("DELETE FROM registros_tiempo WHERE user_id = ? AND fecha = ?")->execute([$userId, $fecha_hoy]);
        $conn->prepare("INSERT INTO registros_tiempo (user_id, fecha, hora_entrada, estado) VALUES (?, ?, ?, 'trabajando')")->execute([$userId, $fecha_hoy, $ahora_php]);
        header("Location: user_page.php?success=entrada");
        exit();
    }

    if ($_POST['accion'] === 'descanso') {
        $stmt = $conn->prepare("SELECT id FROM registros_tiempo WHERE user_id = ? AND fecha = ? AND descanso_inicio IS NULL AND estado = 'trabajando'");
        $stmt->execute([$userId, $fecha_hoy]);
        if ($stmt->fetch()) {
            $conn->prepare("UPDATE registros_tiempo SET descanso_inicio = ?, estado = 'descanso' WHERE user_id = ? AND fecha = ?")->execute([$ahora_php, $userId, $fecha_hoy]);
        }
        header("Location: user_page.php?success=descanso");
        exit();
    }

    if ($_POST['accion'] === 'reanudar') {
        $stmt = $conn->prepare("SELECT descanso_inicio, tiempo_descanso FROM registros_tiempo WHERE user_id = ? AND fecha = ? AND descanso_inicio IS NOT NULL AND descanso_fin IS NULL");
        $stmt->execute([$userId, $fecha_hoy]);
        $reg = $stmt->fetch();

        if ($reg && $reg['descanso_inicio']) {
            $inicio = strtotime($reg['descanso_inicio']);
            $minutos = floor((time() - $inicio) / 60);
            $minutos = max(1, $minutos);
            $nuevo_total = ($reg['tiempo_descanso'] ?? 0) + $minutos;

            $conn->prepare("UPDATE registros_tiempo SET descanso_fin = ?, tiempo_descanso = ?, estado = 'trabajando' WHERE user_id = ? AND fecha = ?")->execute([$ahora_php, $nuevo_total, $userId, $fecha_hoy]);
        }
        header("Location: user_page.php?success=reanudar");
        exit();
    }

    if ($_POST['accion'] === 'salida') {
        $stmt = $conn->prepare("SELECT descanso_inicio, tiempo_descanso, hora_entrada FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
        $stmt->execute([$userId, $fecha_hoy]);
        $reg = $stmt->fetch();

        if ($reg && $reg['descanso_inicio'] && !$reg['descanso_fin']) {
            $inicio = strtotime($reg['descanso_inicio']);
            $minutos = floor((time() - $inicio) / 60);
            $minutos = max(1, $minutos);
            $nuevo_total = ($reg['tiempo_descanso'] ?? 0) + $minutos;

            $conn->prepare("UPDATE registros_tiempo SET descanso_fin = ?, tiempo_descanso = ?, hora_salida = ?, horas_trabajadas = ROUND((TIMESTAMPDIFF(MINUTE, hora_entrada, ?) - ?) / 60, 2), estado = 'completado' WHERE user_id = ? AND fecha = ?")->execute([$ahora_php, $nuevo_total, $ahora_php, $ahora_php, $nuevo_total, $userId, $fecha_hoy]);
        } else {
            $conn->prepare("UPDATE registros_tiempo SET hora_salida = ?, horas_trabajadas = ROUND(TIMESTAMPDIFF(MINUTE, hora_entrada, ?) / 60, 2), estado = 'completado' WHERE user_id = ? AND fecha = ? AND estado = 'trabajando'")->execute([$ahora_php, $ahora_php, $userId, $fecha_hoy]);
        }
        header("Location: user_page.php?success=salida");
        exit();
    }
}

// ============================================
// DATOS
// ============================================
$stmt = $conn->prepare("SELECT * FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
$stmt->execute([$userId, $fecha_hoy]);
$hoy = $stmt->fetch();

$tiene_entrada = !empty($hoy) && !empty($hoy['hora_entrada']);
$tiene_salida = !empty($hoy) && !empty($hoy['hora_salida']);
$en_descanso = !empty($hoy) && $hoy['estado'] === 'descanso' && empty($hoy['descanso_fin']);

$tiempo_total = 0;
if ($tiene_entrada && !$tiene_salida) {
    $entrada = strtotime($hoy['hora_entrada']);
    $tiempo_total = time() - $entrada;
    if ($tiempo_total < 0) $tiempo_total = 0;
}

// Funciones de formato
function formatearHorasDetalle($horas) {
    if ($horas <= 0) return '0h 0m';
    $horas_enteras = floor($horas);
    $minutos = round(($horas - $horas_enteras) * 60);
    return $horas_enteras . 'h ' . $minutos . 'm';
}

function formatearHorasTrabajadas($horas) {
    if ($horas <= 0) return '0.0h';
    $minutos_totales = round($horas * 60);
    if ($minutos_totales < 60) {
        return $minutos_totales . 'm';
    } else {
        return number_format($horas, 1) . 'h';
    }
}

function calcularDuracion($hora_inicio, $hora_fin) {
    $inicio = strtotime($hora_inicio);
    $fin = strtotime($hora_fin);
    $diferencia = ($fin - $inicio) / 3600;
    if ($diferencia <= 0) return '0h';
    $horas = floor($diferencia);
    $minutos = round(($diferencia - $horas) * 60);
    if ($minutos > 0) {
        return $horas . 'h ' . $minutos . 'm';
    }
    return $horas . 'h';
}

$horas_trabajadas = $hoy['horas_trabajadas'] ?? 0;
$horas_normales = $hoy['horas_normales'] ?? 0;
$horas_extras = $hoy['horas_extras'] ?? 0;

$trabajadas_formateado = formatearHorasTrabajadas($horas_trabajadas);
$normales_formateado = formatearHorasDetalle($horas_normales);
$extras_formateado = formatearHorasDetalle($horas_extras);

$mostrar_entrada = !$tiene_entrada || $tiene_salida;
$mostrar_trabajando = $tiene_entrada && !$tiene_salida && !$en_descanso;
$mostrar_descanso = $tiene_entrada && !$tiene_salida && $en_descanso;

// ============================================
// DATOS PARA NUEVAS TARJETAS Y ANÁLISIS SEMANAL
// ============================================

// Obtener última ubicación
$stmt_ubicacion = $conn->prepare("SELECT ubicacion_lat, ubicacion_lng, fecha FROM registros_tiempo WHERE user_id = ? AND ubicacion_lat IS NOT NULL ORDER BY fecha DESC LIMIT 1");
$stmt_ubicacion->execute([$userId]);
$ultima_ubicacion = $stmt_ubicacion->fetch();

// Obtener datos para gráfico semanal (últimos 7 días)
$fecha_inicio_semana = date('Y-m-d', strtotime('-6 days'));
$stmt_semanal = $conn->prepare("
    SELECT fecha, hora_entrada, hora_salida, horas_trabajadas, horas_normales, horas_extras 
    FROM registros_tiempo 
    WHERE user_id = ? AND fecha >= ? 
    ORDER BY fecha
");
$stmt_semanal->execute([$userId, $fecha_inicio_semana]);
$datos_semanales = $stmt_semanal->fetchAll();

// Preparar datos para gráficos
$dias_semana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$horas_por_dia = array_fill(0, 7, 0);
$objetivo_semanal = 40; // 40 horas semanales

// Calcular horas por día de la semana
foreach ($datos_semanales as $registro) {
    $fecha = new DateTime($registro['fecha']);
    $dia_semana_num = $fecha->format('N') - 1;
    if ($dia_semana_num >= 0 && $dia_semana_num < 7) {
        $horas_por_dia[$dia_semana_num] += floatval($registro['horas_trabajadas'] ?? 0);
    }
}

// Calcular total de horas trabajadas en la semana
$total_horas_semana = array_sum($horas_por_dia);
$cumplimiento = round(($total_horas_semana / $objetivo_semanal) * 100, 1);
$cumplimiento = min(100, $cumplimiento);

// Calcular métricas de rendimiento
$dias_trabajados = count(array_filter($horas_por_dia, function($h) { return $h > 0; }));
$asistencia = round(($dias_trabajados / 5) * 100, 1);
$asistencia = min(100, $asistencia);

// Calcular productividad
$productividad = round(($total_horas_semana / $objetivo_semanal) * 100, 1);
$productividad = min(100, $productividad);

// Calcular puntualidad
$puntualidad = round(($asistencia + $productividad) / 2, 1);
$colaboracion = 50;

// Calcular Eficiencia General (promedio simple)
$eficiencia_general = round(($cumplimiento + $asistencia + $puntualidad) / 3, 1);
$eficiencia_general = min(100, $eficiencia_general);

// Obtener datos para semana anterior
$fecha_inicio_anterior = date('Y-m-d', strtotime('-13 days'));
$stmt_anterior = $conn->prepare("
    SELECT fecha, horas_trabajadas 
    FROM registros_tiempo 
    WHERE user_id = ? AND fecha >= ? AND fecha < ?
    ORDER BY fecha
");
$fecha_fin_anterior = date('Y-m-d', strtotime('-6 days'));
$stmt_anterior->execute([$userId, $fecha_inicio_anterior, $fecha_fin_anterior]);
$datos_anterior = $stmt_anterior->fetchAll();

$horas_anterior_por_dia = array_fill(0, 7, 0);
foreach ($datos_anterior as $registro) {
    $fecha = new DateTime($registro['fecha']);
    $dia_semana_num = $fecha->format('N') - 1;
    if ($dia_semana_num >= 0 && $dia_semana_num < 7) {
        $horas_anterior_por_dia[$dia_semana_num] += floatval($registro['horas_trabajadas'] ?? 0);
    }
}
$total_horas_anterior = array_sum($horas_anterior_por_dia);
$variacion_horas = $total_horas_semana - $total_horas_anterior;
$variacion_porcentaje = $total_horas_anterior > 0 ? round(($variacion_horas / $total_horas_anterior) * 100, 1) : 100;

// ============================================
// DATOS PARA TURNOS PRÓXIMOS
// ============================================
$fecha_actual = date('Y-m-d');
$stmt_turnos_proximos = $conn->prepare("SELECT * FROM turnos WHERE user_id = ? AND fecha >= ? ORDER BY fecha ASC");
$stmt_turnos_proximos->execute([$userId, $fecha_actual]);
$turnos_proximos = $stmt_turnos_proximos->fetchAll(PDO::FETCH_ASSOC);

$total_turnos_proximos = count($turnos_proximos);
$pendientes_confirmar = 0;
foreach ($turnos_proximos as $turno) {
    if ($turno['estado'] == 'pendiente') {
        $pendientes_confirmar++;
    }
}
$necesita_cobertura = 0; // Funcionalidad futura

// ============================================
// DATOS DE ASISTENCIA EQUIPO (EJEMPLO - REEMPLAZAR CON DATOS REALES)
// ============================================
$asistencia_equipo = 84.9;
$variacion_asistencia = 3.2;
$presentes_hoy = 72;
$total_empleados = 86;

// ============================================
// DATOS DE EFICIENCIA GENERAL (VARIACIÓN MENSUAL)
// ============================================
$variacion_mensual = 5;
$variacion_mensual_positiva = true; // true = positivo, false = negativo

// Desempeño según eficiencia
if ($eficiencia_general >= 90) {
    $desempeno_texto = 'Desempeño Excelente';
    $desempeno_color = '#F59E0B'; // dorado/ámbar
} elseif ($eficiencia_general >= 60) {
    $desempeno_texto = 'Desempeño Normal';
    $desempeno_color = '#6B7280'; // gris
} elseif ($eficiencia_general >= 30) {
    $desempeno_texto = 'Desempeño Bajo';
    $desempeno_color = '#EF4444'; // rojo
} else {
    $desempeno_texto = 'Desempeño Pésimo';
    $desempeno_color = '#991B1B'; // rojo oscuro
}

// ============================================
// ACTUALIZAR ESTADOS DE TURNOS
// ============================================
$hoy_fecha = date('Y-m-d');
$ahora_hora = date('H:i:s');

$stmt_vencer = $conn->prepare("UPDATE turnos SET estado = 'vencido' WHERE user_id = ? AND fecha < ? AND estado NOT IN ('completado', 'confirmado')");
$stmt_vencer->execute([$userId, $hoy_fecha]);

$stmt_completar = $conn->prepare("UPDATE turnos SET estado = 'completado' WHERE user_id = ? AND fecha = ? AND hora_fin < ? AND estado = 'confirmado'");
$stmt_completar->execute([$userId, $hoy_fecha, $ahora_hora]);

$stmt_completar_ant = $conn->prepare("UPDATE turnos SET estado = 'completado' WHERE user_id = ? AND fecha < ? AND estado = 'confirmado'");
$stmt_completar_ant->execute([$userId, $hoy_fecha]);

// Obtener todos los turnos para el carrusel
$stmt_turnos_usuario = $conn->prepare("SELECT * FROM turnos WHERE user_id = ? ORDER BY fecha ASC");
$stmt_turnos_usuario->execute([$userId]);
$turnos_usuario = $stmt_turnos_usuario->fetchAll(PDO::FETCH_ASSOC);

// Mensajes de sesión
$mensaje_retardo = $_SESSION['mensaje_retardo'] ?? '';
$mensaje_salida = $_SESSION['mensaje_salida'] ?? '';
$mensaje_extra = $_SESSION['mensaje_extra'] ?? '';
$mensaje_completado = $_SESSION['mensaje_completado'] ?? '';

unset($_SESSION['mensaje_retardo']);
unset($_SESSION['mensaje_salida']);
unset($_SESSION['mensaje_extra']);
unset($_SESSION['mensaje_completado']);

// Días de la semana y meses
$dias_semana_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// Fechas para mostrar
$fecha_inicio_mostrar = date('d', strtotime('monday this week'));
$fecha_fin_mostrar = date('d', strtotime('sunday this week'));
$mes_actual = $meses[date('n') - 1];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= $tema ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Mi Panel · HormiClock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        [data-theme="light"] { 
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --text-light: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --btn-bg: #f1f5f9;
            --btn-text: #334155;
            --btn-border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --primary: #3b82f6;
            --extra: #8b5cf6;
            --entrada: #10b981;
            --salida: #ef4444;
            --normal: #3b82f6;
            --card-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
        }
        [data-theme="dark"] { 
            --bg: #0f172a;
            --surface: #1e293b;
            --text: #f1f5f9;
            --text-light: #cbd5e1;
            --text-muted: #94a3b8;
            --border: #334155;
            --btn-bg: #334155;
            --btn-text: #e2e8f0;
            --btn-border: #475569;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --primary: #3b82f6;
            --extra: #a78bfa;
            --entrada: #10b981;
            --salida: #ef4444;
            --normal: #60a5fa;
            --card-shadow: 0 10px 15px -3px rgba(0,0,0,0.3), 0 4px 6px -2px rgba(0,0,0,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: all 0.3s ease;
        }

        .main-content {
            margin-left: 260px;
            padding: 28px;
            transition: margin-left 0.3s;
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .welcome-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--text);
        }

        .welcome-header h1 span {
            background: linear-gradient(135deg, var(--primary), var(--extra));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .date-badge {
            background: var(--surface);
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
        }

        .mensaje {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            border-left: 4px solid;
        }

        .mensaje.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left-color: var(--success);
        }

        .mensaje.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-left-color: var(--warning);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== ANIMACIONES DE ENTRADA ========== */
        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== CONTROL CARD COMPACTO HORIZONTAL ========== */
        .control-card-horizontal {
            background: var(--surface);
            border-radius: 60px;
            padding: 12px 24px;
            margin-bottom: 28px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: var(--card-shadow);
            animation: fadeSlideUp 0.4s ease backwards;
            animation-delay: 0s;
        }

        .control-tiempo {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .control-tiempo i {
            font-size: 28px;
            color: var(--primary);
        }

        .contador-compacto {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: 2px;
        }

        .control-label {
            font-size: 12px;
            color: var(--text-muted);
            padding-left: 8px;
            border-left: 1px solid var(--border);
        }

        .control-botones {
            display: flex;
            gap: 12px;
        }

        .btn-control {
            padding: 8px 24px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 1px solid var(--btn-border);
        }

        .btn-control:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .btn-control.entrada:hover { background: var(--success); color: white; border-color: var(--success); }
        .btn-control.descanso:hover { background: var(--warning); color: white; border-color: var(--warning); }
        .btn-control.salida:hover { background: var(--danger); color: white; border-color: var(--danger); }
        .btn-control.reanudar:hover { background: var(--success); color: white; border-color: var(--success); }

        @media (max-width: 700px) {
            .control-card-horizontal {
                flex-direction: column;
                align-items: stretch;
                border-radius: 28px;
                text-align: center;
            }
            .control-tiempo {
                justify-content: center;
            }
            .control-botones {
                justify-content: center;
            }
        }

        /* ========== 4 TARJETAS ESTADÍSTICAS ========== */
        .stats-cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        /* Estilos comunes para todas las tarjetas */
        .stat-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            animation: fadeSlideUp 0.5s ease backwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .stat-card-title {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* ========== TARJETA 1: HORAS ESTA SEMANA ========== */
        .hours-card .stat-icon-wrapper {
            position: relative;
            display: inline-block;
        }

        .hours-card .stat-icon-container {
            width: 48px;
            height: 48px;
            background: #3B82F6;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
            transition: box-shadow 0.3s ease;
        }

        .hours-card:hover .stat-icon-container {
            box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.2);
        }

        .hours-card .stat-icon-container i {
            font-size: 24px;
            color: white;
        }

        .hours-card .stat-icon-circle-bg {
            position: absolute;
            bottom: -8px;
            left: -8px;
            width: 32px;
            height: 32px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            z-index: 1;
        }

        .hours-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }

        .hours-value small {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-muted);
        }

        .hours-variation {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 12px 0 16px;
            font-size: 13px;
        }

        .hours-variation.positive {
            color: var(--success);
        }

        .hours-variation.negative {
            color: var(--danger);
        }

        .hours-progress-bar {
            background: var(--border);
            border-radius: 10px;
            height: 6px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .hours-progress-fill {
            height: 100%;
            border-radius: 10px;
            background: #1f2937;
            transition: width 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            width: 0%;
        }

        .hours-footer {
            font-size: 12px;
            font-weight: 500;
            color: #3B82F6;
        }

        /* ========== TARJETA 2: ASISTENCIA EQUIPO ========== */
        .team-card .team-icon-bg {
            width: 48px;
            height: 48px;
            background: #10B981;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            transition: box-shadow 0.3s ease;
        }

        .team-card:hover .team-icon-bg {
            box-shadow: 0 0 0 8px rgba(16, 185, 129, 0.2);
        }

        .team-card .team-icon-bg i {
            font-size: 24px;
            color: white;
        }

        .team-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .team-variation {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .team-variation.positive {
            color: var(--success);
        }

        .team-variation.negative {
            color: var(--danger);
        }

        .team-progress-bar {
            background: var(--border);
            border-radius: 10px;
            height: 6px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .team-progress-fill {
            height: 100%;
            border-radius: 10px;
            background: #10B981;
            transition: width 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            width: 0%;
        }

        .team-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }

        /* ========== TARJETA 3: TURNOS PRÓXIMOS ========== */
        .turnos-card .turnos-icon-bg {
            width: 48px;
            height: 48px;
            background: #E91E63;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 0 0 0 rgba(233, 30, 99, 0.4);
            transition: box-shadow 0.3s ease;
        }

        .turnos-card:hover .turnos-icon-bg {
            box-shadow: 0 0 0 8px rgba(233, 30, 99, 0.2);
        }

        .turnos-card .turnos-icon-bg i:first-child {
            font-size: 24px;
            color: white;
        }

        .turnos-card .turnos-icon-bg .small-icon {
            position: absolute;
            bottom: 6px;
            right: 6px;
            font-size: 12px;
            color: white;
        }

        .turnos-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
        }

        .turnos-pendiente {
            font-size: 13px;
            color: #F59E0B;
            margin-bottom: 4px;
        }

        .turnos-cobertura {
            font-size: 13px;
            color: #F97316;
        }

        /* ========== TARJETA 4: EFICIENCIA GENERAL ========== */
        .eficiencia-card .eficiencia-icon-bg {
            width: 48px;
            height: 48px;
            background: #F97316;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4);
            transition: box-shadow 0.3s ease;
        }

        .eficiencia-card:hover .eficiencia-icon-bg {
            box-shadow: 0 0 0 8px rgba(249, 115, 22, 0.2);
        }

        .eficiencia-card .eficiencia-icon-bg i {
            font-size: 24px;
            color: white;
        }

        .eficiencia-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .eficiencia-variation {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .eficiencia-variation.positive {
            color: var(--success);
        }

        .eficiencia-variation.negative {
            color: var(--danger);
        }

        .eficiencia-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .stats-cards-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }

        @media (max-width: 550px) {
            .stats-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ========== UBICACIÓN Y TURNOS EN GRID ========== */
        .ubicacion-turnos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .tarjeta {
            background: var(--surface);
            border-radius: 24px;
            padding: 20px 24px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
        }

        .tarjeta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .tarjeta-titulo {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tarjeta-titulo i {
            color: var(--primary);
            font-size: 20px;
        }

        .btn-agregar {
            background: var(--primary);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
        }

        .btn-agregar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-agregar.danger {
            background: var(--danger);
        }

        .ubicacion-coords {
            background: var(--bg);
            padding: 14px;
            border-radius: 16px;
            font-family: monospace;
            font-size: 14px;
            color: var(--primary);
            margin-bottom: 16px;
            border: 1px solid var(--border);
            text-align: center;
        }

        .btn-mapa {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-mapa:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .ubicacion-sin-datos {
            text-align: center;
            color: var(--text-light);
            padding: 40px;
        }

        /* ========== TURNOS CARRUSEL ========== */
        .turnos-container {
            position: relative;
            width: 100%;
            overflow: visible;
            margin: 0;
            padding: 0 30px;
        }

        .turnos-wrapper {
            position: relative;
            width: 100%;
            max-width: 662px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 20px;
        }

        @media (max-width: 900px) {
            .turnos-wrapper {
                max-width: 392px;
            }
            .turnos-container {
                padding: 0 20px;
            }
        }

        @media (max-width: 600px) {
            .turnos-wrapper {
                max-width: 176px;
            }
            .turnos-container {
                padding: 0 15px;
            }
        }

        .turnos-scroll {
            overflow: hidden;
            width: 100%;
        }

        .turnos-horizontal {
            display: flex;
            gap: 16px;
            transition: transform 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            will-change: transform;
            width: fit-content;
        }

        .turno-card {
            background: var(--bg);
            border-radius: 20px;
            padding: 14px;
            width: 210px;
            min-width: 210px;
            flex-shrink: 0;
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            animation: fadeSlideUp 0.4s ease backwards;
            position: relative;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .turno-card {
                width: 180px;
                min-width: 180px;
                padding: 12px;
            }
            .turno-dia-numero {
                font-size: 36px;
            }
        }

        @media (max-width: 600px) {
            .turno-card {
                width: 160px;
                min-width: 160px;
                padding: 10px;
            }
            .turno-dia-numero {
                font-size: 32px;
            }
            .turno-horario {
                font-size: 12px;
            }
        }

        .turno-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 20px -10px rgba(0,0,0,0.15);
            border-color: var(--primary);
        }

        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .turno-card-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .turno-card:hover .turno-card-actions {
            opacity: 1;
        }

        .turno-card-actions button {
            background: rgba(0,0,0,0.5);
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            cursor: pointer;
            color: white;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .turno-card-actions button:hover {
            transform: scale(1.1);
        }

        .turno-card-actions .edit-btn:hover {
            background: var(--primary);
        }

        .turno-card-actions .delete-btn:hover {
            background: var(--danger);
        }

        .turno-dia-numero {
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, var(--text), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2px;
        }

        .turno-fecha-completa {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .turno-estado-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 600;
            margin-bottom: 10px;
            width: fit-content;
        }

        .turno-estado-badge.pendiente {
            background: rgba(100, 116, 139, 0.15);
            color: #64748b;
        }

        .turno-estado-badge.confirmado {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .turno-estado-badge.completado {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .turno-estado-badge.vencido {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .turno-horario {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

        .turno-duracion {
            font-size: 10px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .turno-ubicacion {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-light);
            margin-bottom: 4px;
            padding: 6px 0;
            border-top: 1px solid var(--border);
        }

        .turno-departamento {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .turno-buttons {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .turno-btn-confirmar,
        .turno-btn-declinar {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .turno-btn-confirmar:hover {
            background: var(--success);
            border-color: var(--success);
            color: white;
            transform: translateY(-2px);
        }

        .turno-btn-declinar:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .scroll-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s;
            box-shadow: var(--card-shadow);
        }

        .scroll-arrow:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-50%) scale(1.05);
        }

        .scroll-arrow.left {
            left: -8px;
        }

        .scroll-arrow.right {
            right: -8px;
        }

        .scroll-arrow.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .sin-turnos {
            text-align: center;
            color: var(--text-light);
            padding: 40px 20px;
        }

        /* ========== INDICADORES DE ACTIVIDAD ========== */
        .indicadores-section {
            margin-bottom: 28px;
        }

        .indicadores-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .indicador-card {
            background: var(--surface);
            border-radius: 20px;
            padding: 18px 12px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
        }

        .indicador-card:hover {
            transform: translateY(-2px);
        }

        .indicador-valor {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .indicador-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .indicador-card.entrada .indicador-valor { color: var(--success); }
        .indicador-card.descanso .indicador-valor { color: var(--warning); }
        .indicador-card.salida .indicador-valor { color: var(--danger); }
        .indicador-card.trabajadas .indicador-valor { color: var(--text); }

        .extra-badge {
            color: var(--extra);
            font-weight: 600;
            font-size: 12px;
            margin-left: 4px;
        }

        .extra-message {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid var(--extra);
            border-radius: 14px;
            padding: 12px 18px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* ========== ANÁLISIS SEMANAL ========== */
        .analisis-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 28px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
        }

        .analisis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .analisis-titulo h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }

        .analisis-titulo p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .analisis-objetivo {
            background: rgba(59, 130, 246, 0.1);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card-analisis {
            background: var(--bg);
            border-radius: 20px;
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .stat-change {
            font-size: 11px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        .graficos-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .grafico-box {
            background: var(--bg);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        .grafico-box h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .grafico-box h3 i {
            color: var(--primary);
        }

        .chart-container {
            position: relative;
            height: 250px;
        }

        .metricas-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 24px;
        }

        .metrica-item {
            text-align: center;
            padding: 16px;
            background: var(--bg);
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        .metrica-valor {
            font-size: 24px;
            font-weight: 700;
        }

        .metrica-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--border);
            border-radius: 10px;
            margin-top: 12px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .progress-fill.puntualidad { background: #10b981; }
        .progress-fill.colaboracion { background: #f59e0b; }
        .progress-fill.asistencia { background: #3b82f6; }
        .progress-fill.productividad { background: #8b5cf6; }

        @media (max-width: 768px) {
            .main-content { margin-left: 80px; padding: 20px; }
            .ubicacion-turnos-grid { grid-template-columns: 1fr; }
            .indicadores-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .graficos-container { grid-template-columns: 1fr; }
            .metricas-grid { grid-template-columns: repeat(2, 1fr); }
            .scroll-arrow {
                width: 30px;
                height: 30px;
            }
            .scroll-arrow.left {
                left: -5px;
            }
            .scroll-arrow.right {
                right: -5px;
            }
        }

        @media (max-width: 480px) {
            .indicadores-grid { grid-template-columns: 1fr; }
            .scroll-arrow {
                width: 28px;
                height: 28px;
            }
        }

        /* ========== MODAL MODERNO ========== */
        .modern-modal {
            max-width: 580px;
            border-radius: 32px;
            overflow: hidden;
        }

        .modern-modal-body {
            padding: 24px 28px;
            background: var(--surface);
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .wall-clock-icon {
            font-size: 24px;
            color: var(--primary);
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .form-group-modern {
            margin-bottom: 24px;
            animation: fadeSlideUpForm 0.3s ease backwards;
        }

        .form-group-modern:nth-child(1) { animation-delay: 0.05s; }
        .form-group-modern:nth-child(2) { animation-delay: 0.1s; }
        .form-group-modern:nth-child(3) { animation-delay: 0.15s; }
        .form-group-modern:nth-child(4) { animation-delay: 0.2s; }
        .form-group-modern:nth-child(5) { animation-delay: 0.25s; }
        .form-group-modern:nth-child(6) { animation-delay: 0.3s; }

        @keyframes fadeSlideUpForm {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-label-modern {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
        }

        .icon-modern {
            font-size: 16px;
            color: var(--primary);
        }

        .form-input-modern {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 16px;
            background: var(--bg);
            color: var(--text);
            font-size: 15px;
            transition: all 0.2s ease;
        }

        .form-input-modern:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-1px);
        }

        .time-row {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .half {
            flex: 1;
        }

        .time-input-wrapper {
            position: relative;
        }

        .time-icon-inner {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .time-input {
            padding-left: 38px;
        }

        .duracion-preview {
            background: rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-light);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .duracion-preview i {
            color: var(--primary);
        }

        .duracion-preview strong {
            color: var(--primary);
            font-size: 14px;
        }

        .tipo-selector {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .tipo-option {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 12px 8px;
            background: var(--bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-light);
        }

        .tipo-option i {
            font-size: 20px;
        }

        .tipo-option span {
            font-weight: 600;
            font-size: 13px;
        }

        .tipo-option small {
            font-size: 10px;
            color: var(--text-muted);
        }

        .tipo-option.active {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }

        .tipo-option.active small {
            color: var(--primary);
        }

        .tipo-option:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .input-icon-wrapper {
            position: relative;
        }

        .input-icon-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            pointer-events: none;
        }

        .input-icon-wrapper input {
            padding-left: 42px;
        }

        .preview-container {
            margin-top: 24px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(139, 92, 246, 0.05));
            border-radius: 20px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }

        .preview-header {
            background: rgba(59, 130, 246, 0.1);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 1px solid rgba(59, 130, 246, 0.2);
        }

        .preview-content {
            padding: 16px;
        }

        .preview-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .preview-item:last-child {
            border-bottom: none;
        }

        .preview-label {
            font-size: 13px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-value {
            font-weight: 600;
            color: var(--text);
        }

        .validation-message, .validation-message-global {
            font-size: 12px;
            margin-top: 8px;
            padding: 6px 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .validation-message.error, .validation-message-global.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .validation-message.success, .validation-message-global.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .modern-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 28px;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .btn-modern {
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-cancel {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 1px solid var(--border);
        }

        .btn-cancel:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .btn-preview {
            background: var(--warning);
            color: white;
        }

        .btn-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-save {
            background: var(--primary);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        /* DATEPICKER */
        .datepicker-container {
            position: relative;
        }

        .datepicker-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
            z-index: 100;
            display: none;
            animation: fadeInDown 0.2s ease;
            overflow: hidden;
        }

        .datepicker-dropdown.active {
            display: block;
        }

        .datepicker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
        }

        .datepicker-nav {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--surface);
            cursor: pointer;
            color: var(--text);
            transition: all 0.2s;
        }

        .datepicker-nav:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .datepicker-month-year {
            display: flex;
            gap: 8px;
            cursor: pointer;
        }

        .datepicker-month, .datepicker-year {
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .datepicker-month:hover, .datepicker-year:hover {
            background: var(--primary);
            color: white;
        }

        .datepicker-calendar {
            padding: 12px;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            margin-bottom: 8px;
        }

        .calendar-weekday {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 6px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text);
        }

        .calendar-day:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .calendar-day.selected {
            background: var(--primary);
            color: white;
        }

        .calendar-day.other-month {
            color: var(--text-muted);
            opacity: 0.5;
        }

        .datepicker-month-selector, .datepicker-year-selector {
            padding: 12px;
            background: var(--surface);
            animation: fadeIn 0.2s ease;
        }

        .month-grid, .year-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .month-option, .year-option {
            text-align: center;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
        }

        .month-option:hover, .year-option:hover {
            background: var(--primary);
            color: white;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 600px) {
            .modern-modal-body {
                padding: 20px;
            }
            .time-row {
                flex-direction: column;
                gap: 16px;
            }
            .tipo-selector {
                flex-direction: column;
            }
            .modern-footer {
                flex-direction: column-reverse;
            }
            .btn-modern {
                justify-content: center;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .modal {
            background: var(--surface);
            border-radius: 32px;
            width: 90%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalSlide 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-light);
            transition: all 0.2s;
        }

        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 28px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--bg);
            color: var(--text);
            font-size: 15px;
            transition: all 0.2s;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="dashboard-container">
            <!-- HEADER -->
            <div class="welcome-header">
                <h1>Hola, <span><?= $firstName ?></span></h1>
                <div class="date-badge"><i class="far fa-calendar-alt"></i> <?= date('d/m/Y') ?></div>
            </div>

            <!-- MENSAJES -->
            <?php if (isset($_GET['success'])): ?>
                <div class="mensaje success"><i class="fas fa-check-circle"></i> 
                    <?php
                    if ($_GET['success'] === 'entrada') echo '✅ Entrada registrada';
                    if ($_GET['success'] === 'descanso') echo '⏸️ Descanso iniciado';
                    if ($_GET['success'] === 'reanudar') echo '▶️ Jornada reanudada';
                    if ($_GET['success'] === 'salida') echo '👋 Salida registrada';
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_retardo): ?>
                <div class="mensaje warning">
                    <i class="fas fa-exclamation-triangle"></i> <?= $mensaje_retardo ?>
                    <button onclick="justificarRetardo()" style="margin-left: 15px; background: #f59e0b; border: none; padding: 4px 12px; border-radius: 20px; color: white; cursor: pointer;">Justificar</button>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_salida): ?>
                <div class="mensaje warning">
                    <i class="fas fa-exclamation-triangle"></i> <?= $mensaje_salida ?>
                    <button onclick="justificarSalidaTemprana()" style="margin-left: 15px; background: #f59e0b; border: none; padding: 4px 12px; border-radius: 20px; color: white; cursor: pointer;">Justificar</button>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_extra): ?>
                <div class="mensaje warning">
                    <i class="fas fa-exclamation-triangle"></i> <?= $mensaje_extra ?>
                    <button onclick="justificarHorasExtras()" style="margin-left: 15px; background: #f59e0b; border: none; padding: 4px 12px; border-radius: 20px; color: white; cursor: pointer;">Justificar</button>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_completado): ?>
                <div class="mensaje success">
                    <i class="fas fa-check-circle"></i> <?= $mensaje_completado ?>
                </div>
            <?php endif; ?>

            <!-- CONTROL CARD COMPACTO HORIZONTAL -->
            <div class="control-card-horizontal">
                <div class="control-tiempo">
                    <i class="fas fa-clock"></i>
                    <span class="contador-compacto" id="contadorHoras">00</span>
                    <span class="contador-compacto">:</span>
                    <span class="contador-compacto" id="contadorMinutos">00</span>
                    <span class="contador-compacto">:</span>
                    <span class="contador-compacto" id="contadorSegundos">00</span>
                    <span class="control-label">tiempo trabajado hoy</span>
                </div>
                <div class="control-botones">
                    <?php if ($mostrar_entrada): ?>
                        <form method="POST">
                            <input type="hidden" name="accion" value="entrada">
                            <button class="btn-control entrada"><i class="fas fa-sign-in-alt"></i> ENTRADA</button>
                        </form>
                    <?php elseif ($mostrar_trabajando): ?>
                        <form method="POST">
                            <input type="hidden" name="accion" value="descanso">
                            <button class="btn-control descanso"><i class="fas fa-mug-hot"></i> DESCANSO</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('¿Finalizar jornada?')">
                            <input type="hidden" name="accion" value="salida">
                            <button class="btn-control salida"><i class="fas fa-sign-out-alt"></i> SALIDA</button>
                        </form>
                    <?php elseif ($mostrar_descanso): ?>
                        <form method="POST">
                            <input type="hidden" name="accion" value="reanudar">
                            <button class="btn-control reanudar"><i class="fas fa-play"></i> REANUDAR</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('¿Finalizar jornada?')">
                            <input type="hidden" name="accion" value="salida">
                            <button class="btn-control salida"><i class="fas fa-sign-out-alt"></i> SALIDA</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ========== 4 NUEVAS TARJETAS ESTADÍSTICAS ========== -->
            <div class="stats-cards-grid">
                <!-- TARJETA 1: HORAS ESTA SEMANA -->
                <div class="stat-card hours-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">HORAS ESTA SEMANA</span>
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon-container">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-icon-circle-bg"></div>
                        </div>
                    </div>
                    <div class="hours-value">
                        <span class="stat-value-large" id="hoursCount"><?= number_format($total_horas_semana, 1) ?></span> <small>/ <?= $objetivo_semanal ?>h</small>
                    </div>
                    <div class="hours-variation <?= $variacion_horas >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $variacion_horas >= 0 ? 'up' : 'down' ?>"></i>
                        <span><?= abs($variacion_horas) ?>h vs semana pasada</span>
                    </div>
                    <div class="hours-progress-bar">
                        <div class="hours-progress-fill" id="hoursProgressFill"></div>
                    </div>
                    <div class="hours-footer">
                        <span><?= $cumplimiento ?>% completado</span>
                    </div>
                </div>

                <!-- TARJETA 2: ASISTENCIA EQUIPO -->
                <div class="stat-card team-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">ASISTENCIA EQUIPO</span>
                        <div class="team-icon-bg">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="team-value"><?= $asistencia_equipo ?>%</div>
                    <div class="team-variation positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+<?= $variacion_asistencia ?>% vs ayer</span>
                    </div>
                    <div class="team-progress-bar">
                        <div class="team-progress-fill" id="teamProgressFill"></div>
                    </div>
                    <div class="team-footer">
                        <i class="fas fa-user-check"></i>
                        <span><?= $presentes_hoy ?> / <?= $total_empleados ?> empleados</span>
                    </div>
                </div>

                <!-- TARJETA 3: TURNOS PRÓXIMOS -->
                <div class="stat-card turnos-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">TURNOS PRÓXIMOS</span>
                        <div class="turnos-icon-bg">
                            <i class="fas fa-calendar-alt"></i>
                            <i class="fas fa-clock small-icon"></i>
                        </div>
                    </div>
                    <div class="turnos-value"><?= $total_turnos_proximos ?></div>
                    <div class="turnos-pendiente">
                        <i class="fas fa-clock"></i> <?= $pendientes_confirmar ?> pendientes confirmar
                    </div>
                    <div class="turnos-cobertura">
                        <i class="fas fa-exclamation-triangle"></i> <?= $necesita_cobertura ?> necesita cobertura
                    </div>
                </div>

                <!-- TARJETA 4: EFICIENCIA GENERAL -->
                <div class="stat-card eficiencia-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">EFICIENCIA GENERAL</span>
                        <div class="eficiencia-icon-bg">
                            <i class="fas fa-bullseye"></i>
                        </div>
                    </div>
                    <div class="eficiencia-value"><?= $eficiencia_general ?>%</div>
                    <div class="eficiencia-variation <?= $variacion_mensual_positiva ? 'positive' : 'negative' ?>">
                        <i class="fas fa-arrow-<?= $variacion_mensual_positiva ? 'up' : 'down' ?>"></i>
                        <span><?= $variacion_mensual_positiva ? '+' : '-' ?><?= abs($variacion_mensual) ?>% este mes</span>
                    </div>
                    <div class="eficiencia-badge" style="background: <?= $desempeno_color ?>20; color: <?= $desempeno_color ?>;">
                        <i class="fas fa-medal"></i> <?= $desempeno_texto ?>
                    </div>
                </div>
            </div>

            <!-- ========== UBICACIÓN (IZQUIERDA) + MIS TURNOS (DERECHA) ========== -->
            <div class="ubicacion-turnos-grid">
                <!-- ÚLTIMA UBICACIÓN -->
                <div class="tarjeta">
                    <div class="tarjeta-header">
                        <div class="tarjeta-titulo">
                            <i class="fas fa-map-marker-alt"></i> Última ubicación
                        </div>
                    </div>
                    
                    <?php if ($ultima_ubicacion): ?>
                        <div class="ubicacion-coords">
                            <?= $ultima_ubicacion['ubicacion_lat'] ?>, <?= $ultima_ubicacion['ubicacion_lng'] ?>
                            <div style="font-size:0.7rem; color:var(--text-light); margin-top:4px;">
                                <?= date('d/m/Y H:i', strtotime($ultima_ubicacion['fecha'])) ?>
                            </div>
                        </div>
                        <a href="https://www.google.com/maps?q=<?= $ultima_ubicacion['ubicacion_lat'] ?>,<?= $ultima_ubicacion['ubicacion_lng'] ?>" 
                           target="_blank" 
                           class="btn-mapa">
                            <i class="fas fa-map-marked-alt"></i> Ver en Google Maps
                        </a>
                    <?php else: ?>
                        <div class="ubicacion-sin-datos">
                            <i class="fas fa-map-pin" style="font-size: 2rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                            No hay ubicaciones registradas
                        </div>
                    <?php endif; ?>
                </div>

                <!-- MIS TURNOS (CARRUSEL) -->
                <div class="tarjeta">
                    <div class="tarjeta-header">
                        <div class="tarjeta-titulo">
                            <i class="fas fa-calendar-check"></i> Mis turnos
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-agregar" onclick="abrirModalTurno()" title="Agregar turno">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn-agregar" onclick="eliminarTurnosPasados()" title="Eliminar turnos pasados" style="background: var(--danger);">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="turnos-container">
                        <?php if (empty($turnos_usuario)): ?>
                            <div class="sin-turnos">
                                <i class="fas fa-calendar-alt" style="font-size: 2rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                                No hay turnos programados
                                <br>
                                <button class="btn-agregar" onclick="abrirModalTurno()" style="margin-top: 15px; background: var(--primary); color: white; border-radius: 30px; padding: 8px 20px; width: auto;">
                                    <i class="fas fa-plus"></i> Agregar turno
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="turnos-wrapper">
                                <div class="turnos-scroll" id="turnosScroll">
                                    <div class="turnos-horizontal" id="turnosHorizontal">
                                        <?php foreach ($turnos_usuario as $turno): 
                                            $fecha_obj = new DateTime($turno['fecha']);
                                            $dia_numero = $fecha_obj->format('d');
                                            $dia_semana = $dias_semana_es[$fecha_obj->format('w')];
                                            $mes = $meses[$fecha_obj->format('n') - 1];
                                            $fecha_completa = $dia_semana . ' ' . $dia_numero . ' ' . $mes;
                                            $duracion = calcularDuracion($turno['hora_inicio'], $turno['hora_fin']);
                                            $es_pasado = strtotime($turno['fecha']) < strtotime(date('Y-m-d'));
                                            
                                            $horas_faltantes = '';
                                            if ($es_pasado && $turno['estado'] != 'completado') {
                                                $fecha_hora_fin = new DateTime($turno['fecha'] . ' ' . $turno['hora_fin']);
                                                $ahora_dt = new DateTime();
                                                if ($fecha_hora_fin < $ahora_dt) {
                                                    $diferencia = $ahora_dt->diff($fecha_hora_fin);
                                                    if ($diferencia->h > 0 || $diferencia->i > 0) {
                                                        $horas_faltantes = " (faltaron " . $diferencia->format('%hh %im') . ")";
                                                    }
                                                }
                                            }
                                            
                                            $estado_clase = $turno['estado'];
                                            
                                            if ($turno['estado'] == 'completado') {
                                                $estado_texto = '✓ Completado';
                                            } elseif ($es_pasado && $turno['estado'] == 'confirmado') {
                                                $estado_texto = '⚠️ No se registró asistencia' . $horas_faltantes;
                                                $estado_clase = 'vencido';
                                            } elseif ($es_pasado) {
                                                $estado_texto = '📅 Vencido' . $horas_faltantes;
                                                $estado_clase = 'vencido';
                                            } elseif ($turno['estado'] == 'confirmado') {
                                                $estado_texto = '✅ Confirmado';
                                            } else {
                                                $estado_texto = '⏳ Pendiente';
                                            }
                                            
                                            $icono_tipo = $turno['tipo'] == 'diurna' ? '🌞' : ($turno['tipo'] == 'nocturna' ? '🌙' : '⚡');
                                            $ubicacion = $turno['ubicacion'] ?? 'Oficina Principal';
                                            $departamento = $turno['departamento'] ?? 'General';
                                        ?>
                                        <div class="turno-card" data-id="<?= $turno['id'] ?>" data-fecha="<?= $turno['fecha'] ?>" data-hora-inicio="<?= $turno['hora_inicio'] ?>" data-hora-fin="<?= $turno['hora_fin'] ?>" data-tipo="<?= $turno['tipo'] ?>" data-ubicacion="<?= htmlspecialchars($ubicacion) ?>" data-departamento="<?= htmlspecialchars($departamento) ?>" data-estado="<?= $turno['estado'] ?>">
                                            <div class="turno-card-actions">
                                                <?php if (!$es_pasado && $turno['estado'] != 'completado'): ?>
                                                <button class="edit-btn" onclick="event.stopPropagation(); editarTurno(<?= $turno['id'] ?>, '<?= $turno['fecha'] ?>', '<?= $turno['hora_inicio'] ?>', '<?= $turno['hora_fin'] ?>', '<?= $turno['tipo'] ?>', '<?= addslashes($ubicacion) ?>', '<?= addslashes($departamento) ?>')" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="delete-btn" onclick="event.stopPropagation(); eliminarTurno(<?= $turno['id'] ?>)" title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            
                                            <div class="turno-dia-numero"><?= $dia_numero ?></div>
                                            <div class="turno-fecha-completa"><?= $fecha_completa ?></div>
                                            
                                            <div class="turno-estado-badge <?= $estado_clase ?>">
                                                <?= $estado_texto ?>
                                            </div>
                                            
                                            <div class="turno-horario">
                                                <?= substr($turno['hora_inicio'], 0, 5) ?> - <?= substr($turno['hora_fin'], 0, 5) ?>
                                            </div>
                                            <div class="turno-duracion"><?= $duracion ?></div>
                                            
                                            <div class="turno-ubicacion">
                                                <i class="fas fa-location-dot"></i> <?= htmlspecialchars($ubicacion) ?>
                                            </div>
                                            <div class="turno-departamento">
                                                <i class="fas fa-building"></i> <?= htmlspecialchars($departamento) ?> · <?= $icono_tipo ?> <?= ucfirst($turno['tipo']) ?>
                                            </div>
                                            
                                            <?php if (!$es_pasado && $turno['estado'] != 'completado' && $turno['estado'] != 'confirmado'): ?>
                                            <div class="turno-buttons">
                                                <button class="turno-btn-confirmar" onclick="event.stopPropagation(); confirmarTurno(<?= $turno['id'] ?>, this)">
                                                    <i class="fas fa-check"></i> Confirmar
                                                </button>
                                                <button class="turno-btn-declinar" onclick="event.stopPropagation(); declinarTurno(<?= $turno['id'] ?>)">
                                                    <i class="fas fa-times"></i> Declinar
                                                </button>
                                            </div>
                                            <?php elseif ($turno['estado'] == 'confirmado' && !$es_pasado): ?>
                                            <div style="margin-top: 8px; text-align: center; color: var(--success); font-size: 10px;">
                                                <i class="fas fa-check-circle"></i> Turno confirmado
                                            </div>
                                            <?php elseif ($es_pasado): ?>
                                            <div style="margin-top: 8px; text-align: center; color: var(--text-muted); font-size: 10px;">
                                                <i class="fas fa-calendar-times"></i> Turno vencido
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="scroll-arrow left" id="scrollArrowLeft" onclick="scrollTurnosCarrusel('left')">
                                <i class="fas fa-chevron-left"></i>
                            </div>
                            <div class="scroll-arrow right" id="scrollArrowRight" onclick="scrollTurnosCarrusel('right')">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ========== INDICADORES DE ACTIVIDAD ========== -->
            <div class="indicadores-section">
                <div class="indicadores-grid">
                    <div class="indicador-card entrada">
                        <div class="indicador-valor"><?= !empty($hoy['hora_entrada']) ? date('H:i',strtotime($hoy['hora_entrada'])) : '--:--' ?></div>
                        <div class="indicador-label">ENTRADA</div>
                    </div>
                    <div class="indicador-card descanso">
                        <div class="indicador-valor"><?= floor(($hoy['tiempo_descanso']??0)/60) ?>h <?= ($hoy['tiempo_descanso']??0)%60 ?>m</div>
                        <div class="indicador-label">DESCANSO</div>
                    </div>
                    <div class="indicador-card salida">
                        <div class="indicador-valor"><?= !empty($hoy['hora_salida']) ? date('H:i',strtotime($hoy['hora_salida'])) : '--:--' ?></div>
                        <div class="indicador-label">SALIDA</div>
                    </div>
                    <div class="indicador-card trabajadas">
                        <div class="indicador-valor">
                            <?= $trabajadas_formateado ?>
                            <?php if ($horas_extras > 0): ?>
                                <span class="extra-badge">+<?= $extras_formateado ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="indicador-label">TRABAJADAS</div>
                    </div>
                </div>
                
                <?php if ($horas_extras > 0): ?>
                <div class="extra-message">
                    <span style="font-size:18px; color:var(--extra);">⚡</span>
                    <div>
                        <span style="color:var(--extra); font-weight:600;">+<?= $extras_formateado ?> extras</span>
                        <span style="color:var(--text-light); font-size:0.8rem; margin-left:8px;">(<?= $normales_formateado ?> normales)</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ========== ANÁLISIS SEMANAL ========== -->
            <div class="analisis-card">
                <div class="analisis-header">
                    <div class="analisis-titulo">
                        <h2>📊 Análisis Semanal de Horas</h2>
                        <p><?= $fecha_inicio_mostrar ?> - <?= $fecha_fin_mostrar ?> <?= $mes_actual ?> <?= date('Y') ?></p>
                    </div>
                    <div class="analisis-objetivo">
                        <i class="fas fa-bullseye"></i> Objetivo: <?= $objetivo_semanal ?>h
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card-analisis">
                        <div class="stat-value"><?= number_format($total_horas_semana, 1) ?>h</div>
                        <div class="stat-label">Horas Trabajadas</div>
                        <div class="stat-change <?= $variacion_porcentaje >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-arrow-<?= $variacion_porcentaje >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($variacion_porcentaje) ?>% vs semana anterior
                        </div>
                    </div>
                    <div class="stat-card-analisis">
                        <div class="stat-value"><?= $cumplimiento ?>%</div>
                        <div class="stat-label">Cumplimiento</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $cumplimiento ?>%; background: var(--primary);"></div>
                        </div>
                    </div>
                    <div class="stat-card-analisis">
                        <div class="stat-value"><?= $dias_trabajados ?>/5</div>
                        <div class="stat-label">Días Trabajados</div>
                    </div>
                    <div class="stat-card-analisis">
                        <div class="stat-value"><?= $horas_extras > 0 ? '+' . $extras_formateado : '0h' ?></div>
                        <div class="stat-label">Horas Extras</div>
                    </div>
                </div>

                <div class="graficos-container">
                    <div class="grafico-box">
                        <h3><i class="fas fa-chart-bar"></i> Horas por Día</h3>
                        <div class="chart-container">
                            <canvas id="horasSemanalesChart"></canvas>
                        </div>
                    </div>
                    <div class="grafico-box">
                        <h3><i class="fas fa-chart-pie"></i> Métricas de Rendimiento</h3>
                        <div class="chart-container">
                            <canvas id="metricasChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="metricas-grid">
                    <div class="metrica-item">
                        <div class="metrica-valor" style="color: #10b981;"><?= $puntualidad ?>%</div>
                        <div class="metrica-label">Puntualidad</div>
                        <div class="progress-bar">
                            <div class="progress-fill puntualidad" style="width: <?= $puntualidad ?>%;"></div>
                        </div>
                    </div>
                    <div class="metrica-item">
                        <div class="metrica-valor" style="color: #f59e0b;"><?= $colaboracion ?>%</div>
                        <div class="metrica-label">Colaboración</div>
                        <div class="progress-bar">
                            <div class="progress-fill colaboracion" style="width: <?= $colaboracion ?>%;"></div>
                        </div>
                    </div>
                    <div class="metrica-item">
                        <div class="metrica-valor" style="color: #3b82f6;"><?= $asistencia ?>%</div>
                        <div class="metrica-label">Asistencia</div>
                        <div class="progress-bar">
                            <div class="progress-fill asistencia" style="width: <?= $asistencia ?>%;"></div>
                        </div>
                    </div>
                    <div class="metrica-item">
                        <div class="metrica-valor" style="color: #8b5cf6;"><?= $productividad ?>%</div>
                        <div class="metrica-label">Productividad</div>
                        <div class="progress-bar">
                            <div class="progress-fill productividad" style="width: <?= $productividad ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL PARA AGREGAR/EDITAR TURNOS - MODERNO -->
    <div id="modalTurno" class="modal-overlay">
        <div class="modal modern-modal">
            <div class="modal-header">
                <h3 id="modalTurnoTitulo">
                    <i class="fas fa-clock wall-clock-icon"></i> 
                    <span id="modalTitleText">Agregar turno</span>
                </h3>
                <button class="modal-close" onclick="cerrarModalTurno()">&times;</button>
            </div>
            <div class="modal-body modern-modal-body">
                <input type="hidden" id="turno_id">
                
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-calendar-alt icon-modern"></i>
                        <span>Fecha del turno</span>
                    </label>
                    <div class="datepicker-container">
                        <input type="text" id="turno_fecha_display" class="form-input-modern" placeholder="Selecciona una fecha" readonly autocomplete="off">
                        <input type="hidden" id="turno_fecha" name="turno_fecha">
                        <div id="datepickerDropdown" class="datepicker-dropdown">
                            <div class="datepicker-header">
                                <button class="datepicker-nav" id="prevMonthBtn"><i class="fas fa-chevron-left"></i></button>
                                <div class="datepicker-month-year">
                                    <span id="datepickerMonth" class="datepicker-month">Marzo</span>
                                    <span id="datepickerYear" class="datepicker-year">2026</span>
                                </div>
                                <button class="datepicker-nav" id="nextMonthBtn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="datepicker-month-selector" id="monthSelector" style="display: none;">
                                <div class="month-grid" id="monthGrid"></div>
                            </div>
                            <div class="datepicker-year-selector" id="yearSelector" style="display: none;">
                                <div class="year-grid" id="yearGrid"></div>
                            </div>
                            <div class="datepicker-calendar" id="datepickerCalendar"></div>
                        </div>
                    </div>
                    <div class="validation-message" id="fechaValidation"></div>
                </div>

                <div class="time-row">
                    <div class="form-group-modern half">
                        <label class="form-label-modern">
                            <i class="fas fa-hourglass-start icon-modern"></i>
                            <span>Hora inicio</span>
                        </label>
                        <div class="time-input-wrapper">
                            <i class="fas fa-clock time-icon-inner"></i>
                            <input type="time" id="turno_hora_inicio" class="form-input-modern time-input" value="09:00">
                        </div>
                    </div>
                    <div class="form-group-modern half">
                        <label class="form-label-modern">
                            <i class="fas fa-hourglass-end icon-modern"></i>
                            <span>Hora fin</span>
                        </label>
                        <div class="time-input-wrapper">
                            <i class="fas fa-clock time-icon-inner"></i>
                            <input type="time" id="turno_hora_fin" class="form-input-modern time-input" value="17:00">
                        </div>
                    </div>
                </div>

                <div class="duracion-preview" id="duracionPreview">
                    <i class="fas fa-chart-line"></i>
                    <span>Duración estimada: <strong id="duracionCalculada">8h 0m</strong></span>
                </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-sun icon-modern"></i>
                        <span>Tipo de jornada</span>
                    </label>
                    <div class="tipo-selector">
                        <button type="button" class="tipo-option" data-tipo="diurna">
                            <i class="fas fa-sun"></i>
                            <span>Diurna</span>
                            <small>🌞 06:00 - 18:00</small>
                        </button>
                        <button type="button" class="tipo-option" data-tipo="nocturna">
                            <i class="fas fa-moon"></i>
                            <span>Nocturna</span>
                            <small>🌙 18:00 - 06:00</small>
                        </button>
                        <button type="button" class="tipo-option" data-tipo="extra">
                            <i class="fas fa-bolt"></i>
                            <span>Extras</span>
                            <small>⚡ Horas adicionales</small>
                        </button>
                    </div>
                    <input type="hidden" id="turno_tipo" value="diurna">
                </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-location-dot icon-modern"></i>
                        <span>Ubicación</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-building location-icon"></i>
                        <input type="text" id="turno_ubicacion" class="form-input-modern" placeholder="Ej: Oficina Principal, Sucursal Norte, Remoto" value="Oficina Principal">
                    </div>
                </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-users icon-modern"></i>
                        <span>Departamento / Área</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-briefcase dept-icon"></i>
                        <input type="text" id="turno_departamento" class="form-input-modern" placeholder="Ej: Desarrollo, Ventas, Soporte" value="General">
                    </div>
                </div>

                <div class="preview-container" id="previewContainer" style="display: none;">
                    <div class="preview-header">
                        <i class="fas fa-eye"></i>
                        <span>Vista previa de tu turno</span>
                    </div>
                    <div class="preview-content" id="previewContent"></div>
                </div>

                <div class="validation-message-global" id="globalValidation"></div>
            </div>
            <div class="modal-footer modern-footer">
                <button class="btn-modern btn-cancel" onclick="cerrarModalTurno()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button class="btn-modern btn-preview" id="previewBtn" onclick="mostrarPreview()">
                    <i class="fas fa-eye"></i> Vista previa
                </button>
                <button class="btn-modern btn-save" id="saveBtn" onclick="guardarTurno()" style="display: none;">
                    <i class="fas fa-save"></i> Guardar turno
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE DETALLE DEL TURNO -->
    <div id="modalDetalleTurno" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Detalles del turno</h3>
                <button class="modal-close" onclick="cerrarModalDetalle()">&times;</button>
            </div>
            <div class="modal-body" id="detalleTurnoBody">
            </div>
            <div class="modal-footer">
                <button class="btn" style="background: var(--danger);" onclick="cerrarModalDetalle()">Cerrar</button>
                <button class="btn" style="background: var(--primary);" id="detalleEditarBtn" onclick="">Editar turno</button>
            </div>
        </div>
    </div>

    <!-- MODAL PARA JUSTIFICAR INCIDENCIAS -->
    <div id="modalJustificacion" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalJustificacionTitulo">Justificar incidencia</h3>
                <button class="modal-close" onclick="cerrarModalJustificacion()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="justificacion_tipo">
                <div class="form-group">
                    <label>Motivo</label>
                    <textarea id="justificacion_motivo" rows="4" placeholder="Describe el motivo..." style="width:100%; padding:12px; border-radius:12px; border:1px solid var(--border); background:var(--bg); color:var(--text);"></textarea>
                </div>
                <div class="form-group">
                    <label>Adjuntar archivo (opcional)</label>
                    <input type="file" id="justificacion_archivo" accept="image/*,video/*,application/pdf">
                    <small style="color: var(--text-light);">Puedes subir fotos, capturas o documentos como prueba</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" style="background: var(--danger);" onclick="cerrarModalJustificacion()">Cancelar</button>
                <button class="btn" style="background: var(--primary);" onclick="enviarJustificacion()">Enviar justificación</button>
            </div>
        </div>
    </div>

    <script>
        <?php if ($tiene_entrada && !$tiene_salida): ?>
        const entrada = <?= strtotime($hoy['hora_entrada']) ?>;
        
        function actualizarContador() {
            const ahora = Math.floor(Date.now() / 1000);
            let s = ahora - entrada;
            if (s < 0) s = 0;
            
            const horas = Math.floor(s / 3600);
            const minutos = Math.floor((s % 3600) / 60);
            const segundos = s % 60;
            
            document.getElementById('contadorHoras').innerText = horas.toString().padStart(2, '0');
            document.getElementById('contadorMinutos').innerText = minutos.toString().padStart(2, '0');
            document.getElementById('contadorSegundos').innerText = segundos.toString().padStart(2, '0');
        }
        actualizarContador();
        setInterval(actualizarContador, 1000);

        <?php if ($en_descanso): ?>
        const inicioDesc = <?= strtotime($hoy['descanso_inicio']) ?>;
        const LIMITE = 3600;

        function actualizarDescanso() {
            const ahora = Math.floor(Date.now() / 1000);
            const transcurrido = ahora - inicioDesc;
            const restante = LIMITE - transcurrido;

            const box = document.getElementById('descansoTiempo');
            if (!box) return;

            if (restante > 0) {
                const minutos = Math.floor(restante / 60);
                const segundos = restante % 60;
                box.innerText = `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
            } else {
                box.innerText = '00:00';
            }
        }
        actualizarDescanso();
        setInterval(actualizarDescanso, 1000);
        <?php endif; ?>
        <?php endif; ?>

        // Inicializar contador si no hay jornada activa
        <?php if (!$tiene_entrada || $tiene_salida): ?>
        document.getElementById('contadorHoras').innerText = '00';
        document.getElementById('contadorMinutos').innerText = '00';
        document.getElementById('contadorSegundos').innerText = '00';
        <?php endif; ?>

        // ========== ANIMACIÓN CONTADOR HORAS ==========
        function animateCounter(element, start, end, duration) {
            if (!element) return;
            
            const range = end - start;
            let current = start;
            let startTime = null;
            
            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                const elapsed = timestamp - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                current = start + (range * easeOutCubic);
                
                element.textContent = current.toFixed(1);
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    element.textContent = end.toFixed(1);
                }
            }
            
            requestAnimationFrame(step);
        }

        // ========== ANIMACIÓN DE BARRAS DE PROGRESO ==========
        function animateProgressBar(element, targetWidth) {
            if (!element) return;
            setTimeout(() => {
                element.style.width = targetWidth + '%';
            }, 200);
        }

        // ========== INICIALIZAR ANIMACIONES ==========
        function initCardAnimations() {
            // Animación barra horas
            const hoursFill = document.getElementById('hoursProgressFill');
            if (hoursFill) {
                animateProgressBar(hoursFill, <?= $cumplimiento ?>);
            }
            
            // Animación barra asistencia equipo
            const teamFill = document.getElementById('teamProgressFill');
            if (teamFill) {
                animateProgressBar(teamFill, 84.9);
            }
            
            // Animación contador horas
            const counterElement = document.getElementById('hoursCount');
            if (counterElement) {
                const finalValue = <?= $total_horas_semana ?>;
                animateCounter(counterElement, 0, finalValue, 1200);
            }
        }

        // ========== GRÁFICOS ==========
        document.addEventListener('DOMContentLoaded', function() {
            const ctxBar = document.getElementById('horasSemanalesChart').getContext('2d');
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($dias_semana) ?>,
                    datasets: [
                        {
                            label: 'Horas Trabajadas',
                            data: <?= json_encode($horas_por_dia) ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            borderRadius: 8
                        },
                        {
                            label: 'Objetivo (8h)',
                            data: Array(7).fill(8),
                            type: 'line',
                            borderColor: '#f59e0b',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: getComputedStyle(document.body).getPropertyValue('--text') } },
                        tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + context.raw.toFixed(1) + ' horas'; } } }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 12, title: { display: true, text: 'Horas', color: getComputedStyle(document.body).getPropertyValue('--text-light') }, grid: { color: getComputedStyle(document.body).getPropertyValue('--border') } },
                        x: { grid: { display: false }, ticks: { color: getComputedStyle(document.body).getPropertyValue('--text-light') } }
                    }
                }
            });

            const ctxPie = document.getElementById('metricasChart').getContext('2d');
            new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ['Puntualidad', 'Colaboración', 'Asistencia', 'Productividad'],
                    datasets: [{
                        data: [<?= $puntualidad ?>, <?= $colaboracion ?>, <?= $asistencia ?>, <?= $productividad ?>],
                        backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6'],
                        borderWidth: 0,
                        cutout: '60%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: getComputedStyle(document.body).getPropertyValue('--text'), font: { size: 11 } } },
                        tooltip: { callbacks: { label: function(context) { return context.label + ': ' + context.raw + '%'; } } }
                    }
                }
            });
            
            // Iniciar animaciones de tarjetas
            initCardAnimations();
        });

        // ========== DATEPICKER ==========
        const monthsList = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const weekdaysList = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'];
        let currentDatePicker = new Date();
        let selectedDateObj = null;

        function initDatepicker() {
            const displayInput = document.getElementById('turno_fecha_display');
            const hiddenInput = document.getElementById('turno_fecha');
            const dropdown = document.getElementById('datepickerDropdown');
            
            if (!displayInput) return;
            
            if (!selectedDateObj) {
                const hoy = new Date();
                selectedDateObj = hoy;
                currentDatePicker = new Date(hoy);
                updateFechaDisplay();
            }
            
            displayInput.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('active');
                renderCalendar();
            });
            
            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target) && e.target !== displayInput) {
                    dropdown.classList.remove('active');
                    document.getElementById('monthSelector').style.display = 'none';
                    document.getElementById('yearSelector').style.display = 'none';
                }
            });
            
            const prevBtn = document.getElementById('prevMonthBtn');
            const nextBtn = document.getElementById('nextMonthBtn');
            if (prevBtn) prevBtn.onclick = () => { currentDatePicker.setMonth(currentDatePicker.getMonth() - 1); renderCalendar(); };
            if (nextBtn) nextBtn.onclick = () => { currentDatePicker.setMonth(currentDatePicker.getMonth() + 1); renderCalendar(); };
            
            const monthEl = document.getElementById('datepickerMonth');
            const yearEl = document.getElementById('datepickerYear');
            const monthSelector = document.getElementById('monthSelector');
            const yearSelector = document.getElementById('yearSelector');
            
            if (monthEl) monthEl.onclick = () => { monthSelector.style.display = 'block'; yearSelector.style.display = 'none'; renderMonthSelector(); };
            if (yearEl) yearEl.onclick = () => { yearSelector.style.display = 'block'; monthSelector.style.display = 'none'; renderYearSelector(); };
        }

        function renderCalendar() {
            const year = currentDatePicker.getFullYear();
            const month = currentDatePicker.getMonth();
            
            const monthEl = document.getElementById('datepickerMonth');
            const yearEl = document.getElementById('datepickerYear');
            if (monthEl) monthEl.innerText = monthsList[month];
            if (yearEl) yearEl.innerText = year;
            
            const firstDay = new Date(year, month, 1);
            const startDay = firstDay.getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrevMonth = new Date(year, month, 0).getDate();
            
            let calendarHtml = '<div class="calendar-weekdays">';
            weekdaysList.forEach(day => { calendarHtml += `<div class="calendar-weekday">${day}</div>`; });
            calendarHtml += '</div><div class="calendar-days">';
            
            for (let i = 0; i < startDay; i++) {
                const prevDay = daysInPrevMonth - startDay + i + 1;
                calendarHtml += `<div class="calendar-day other-month" data-day="${prevDay}" data-month="${month - 1}" data-year="${year}">${prevDay}</div>`;
            }
            
            for (let day = 1; day <= daysInMonth; day++) {
                const isSelected = selectedDateObj && selectedDateObj.getDate() === day && selectedDateObj.getMonth() === month && selectedDateObj.getFullYear() === year;
                calendarHtml += `<div class="calendar-day ${isSelected ? 'selected' : ''}" data-day="${day}" data-month="${month}" data-year="${year}">${day}</div>`;
            }
            
            const remainingDays = 42 - (startDay + daysInMonth);
            for (let i = 1; i <= remainingDays; i++) {
                calendarHtml += `<div class="calendar-day other-month" data-day="${i}" data-month="${month + 1}" data-year="${year}">${i}</div>`;
            }
            
            calendarHtml += '</div>';
            const calendarDiv = document.getElementById('datepickerCalendar');
            if (calendarDiv) calendarDiv.innerHTML = calendarHtml;
            
            document.querySelectorAll('.calendar-day').forEach(dayEl => {
                dayEl.addEventListener('click', () => {
                    const day = parseInt(dayEl.dataset.day);
                    const month = parseInt(dayEl.dataset.month);
                    const year = parseInt(dayEl.dataset.year);
                    selectedDateObj = new Date(year, month, day);
                    updateFechaDisplay();
                    const dropdown = document.getElementById('datepickerDropdown');
                    if (dropdown) dropdown.classList.remove('active');
                    validarFechaPasada();
                    actualizarPreview();
                });
            });
        }

        function renderMonthSelector() {
            const monthGrid = document.getElementById('monthGrid');
            if (!monthGrid) return;
            monthGrid.innerHTML = '';
            monthsList.forEach((month, index) => {
                const monthEl = document.createElement('div');
                monthEl.className = 'month-option';
                monthEl.innerText = month;
                monthEl.onclick = () => { currentDatePicker.setMonth(index); document.getElementById('monthSelector').style.display = 'none'; renderCalendar(); };
                monthGrid.appendChild(monthEl);
            });
        }

        function renderYearSelector() {
            const yearGrid = document.getElementById('yearGrid');
            if (!yearGrid) return;
            yearGrid.innerHTML = '';
            for (let year = 1990; year <= 2050; year++) {
                const yearEl = document.createElement('div');
                yearEl.className = 'year-option';
                yearEl.innerText = year;
                yearEl.onclick = () => { currentDatePicker.setFullYear(year); document.getElementById('yearSelector').style.display = 'none'; renderCalendar(); };
                yearGrid.appendChild(yearEl);
            }
        }

        function updateFechaDisplay() {
            const displayInput = document.getElementById('turno_fecha_display');
            const hiddenInput = document.getElementById('turno_fecha');
            if (selectedDateObj && displayInput && hiddenInput) {
                const day = selectedDateObj.getDate();
                const month = monthsList[selectedDateObj.getMonth()];
                const year = selectedDateObj.getFullYear();
                displayInput.value = `${day} ${month} ${year}`;
                const yearNum = selectedDateObj.getFullYear();
                const monthNum = String(selectedDateObj.getMonth() + 1).padStart(2, '0');
                const dayNum = String(selectedDateObj.getDate()).padStart(2, '0');
                hiddenInput.value = `${yearNum}-${monthNum}-${dayNum}`;
            }
        }

        function validarFechaPasada() {
            const fechaInput = document.getElementById('turno_fecha');
            const validationDiv = document.getElementById('fechaValidation');
            
            if (fechaInput && fechaInput.value) {
                const fechaSeleccionada = new Date(fechaInput.value);
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                if (fechaSeleccionada < hoy) {
                    if (validationDiv) { validationDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ⚠️ Esta fecha ya pasó. ¿Estás seguro?'; validationDiv.className = 'validation-message error'; }
                    return false;
                } else {
                    if (validationDiv) { validationDiv.innerHTML = '<i class="fas fa-check-circle"></i> ✅ Fecha válida'; validationDiv.className = 'validation-message success'; }
                    return true;
                }
            }
            return true;
        }

        // ========== TIPO SELECTOR ==========
        function initTipoSelector() {
            const tipoOptions = document.querySelectorAll('.tipo-option');
            tipoOptions.forEach(option => {
                option.addEventListener('click', () => {
                    tipoOptions.forEach(opt => opt.classList.remove('active'));
                    option.classList.add('active');
                    const tipoInput = document.getElementById('turno_tipo');
                    if (tipoInput) tipoInput.value = option.dataset.tipo;
                    actualizarPreview();
                });
            });
        }

        // ========== DURACIÓN ==========
        function calcularDuracionPreview() {
            const horaInicio = document.getElementById('turno_hora_inicio');
            const horaFin = document.getElementById('turno_hora_fin');
            const duracionSpan = document.getElementById('duracionCalculada');
            
            if (horaInicio && horaFin && horaInicio.value && horaFin.value) {
                const inicio = new Date(`2000-01-01T${horaInicio.value}`);
                const fin = new Date(`2000-01-01T${horaFin.value}`);
                const diffMs = fin - inicio;
                if (diffMs > 0) {
                    const horas = Math.floor(diffMs / 3600000);
                    const minutos = Math.floor((diffMs % 3600000) / 60000);
                    const duracionTexto = `${horas}h ${minutos > 0 ? minutos + 'm' : ''}`;
                    if (duracionSpan) duracionSpan.innerText = duracionTexto;
                    return duracionTexto;
                }
            }
            if (duracionSpan) duracionSpan.innerText = '--h --m';
            return '--h --m';
        }

        // ========== PREVIEW ==========
        function mostrarPreview() {
            const previewContainer = document.getElementById('previewContainer');
            const previewBtn = document.getElementById('previewBtn');
            const saveBtn = document.getElementById('saveBtn');
            
            if (!validarFechaPasada()) {
                const globalValidation = document.getElementById('globalValidation');
                if (globalValidation) { globalValidation.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Por favor selecciona una fecha válida'; globalValidation.className = 'validation-message-global error'; }
                return;
            }
            
            actualizarPreview();
            if (previewContainer) previewContainer.style.display = 'block';
            if (previewBtn) previewBtn.style.display = 'none';
            if (saveBtn) saveBtn.style.display = 'flex';
            if (previewContainer) previewContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function actualizarPreview() {
            const fechaDisplay = document.getElementById('turno_fecha_display');
            const horaInicio = document.getElementById('turno_hora_inicio');
            const horaFin = document.getElementById('turno_hora_fin');
            const tipo = document.getElementById('turno_tipo');
            const ubicacion = document.getElementById('turno_ubicacion');
            const departamento = document.getElementById('turno_departamento');
            const previewContent = document.getElementById('previewContent');
            
            if (!previewContent) return;
            
            const fechaTexto = fechaDisplay ? fechaDisplay.value : 'No seleccionada';
            const horaInicioTexto = horaInicio ? horaInicio.value : '--:--';
            const horaFinTexto = horaFin ? horaFin.value : '--:--';
            const tipoValor = tipo ? tipo.value : 'diurna';
            const ubicacionTexto = ubicacion ? ubicacion.value : 'No especificada';
            const departamentoTexto = departamento ? departamento.value : 'No especificado';
            const tipoIcon = tipoValor === 'diurna' ? '🌞 Diurna' : (tipoValor === 'nocturna' ? '🌙 Nocturna' : '⚡ Extras');
            const duracion = calcularDuracionPreview();
            
            const previewHtml = `
                <div class="preview-item"><span class="preview-label"><i class="fas fa-calendar-alt"></i> Fecha</span><span class="preview-value">${fechaTexto}</span></div>
                <div class="preview-item"><span class="preview-label"><i class="fas fa-clock"></i> Horario</span><span class="preview-value">${horaInicioTexto} - ${horaFinTexto}</span></div>
                <div class="preview-item"><span class="preview-label"><i class="fas fa-hourglass-half"></i> Duración</span><span class="preview-value">${duracion}</span></div>
                <div class="preview-item"><span class="preview-label"><i class="fas fa-tag"></i> Tipo</span><span class="preview-value">${tipoIcon}</span></div>
                <div class="preview-item"><span class="preview-label"><i class="fas fa-location-dot"></i> Ubicación</span><span class="preview-value">${ubicacionTexto}</span></div>
                <div class="preview-item"><span class="preview-label"><i class="fas fa-building"></i> Departamento</span><span class="preview-value">${departamentoTexto}</span></div>
            `;
            previewContent.innerHTML = previewHtml;
        }

        // ========== MODAL TURNO ==========
        function abrirModalTurno(id = null, fecha = '', hora_inicio = '', hora_fin = '', tipo = '', ubicacion = '', departamento = '') {
            const modal = document.getElementById('modalTurno');
            const titulo = document.getElementById('modalTitleText');
            const previewContainer = document.getElementById('previewContainer');
            const previewBtn = document.getElementById('previewBtn');
            const saveBtn = document.getElementById('saveBtn');
            
            if (previewContainer) previewContainer.style.display = 'none';
            if (previewBtn) previewBtn.style.display = 'flex';
            if (saveBtn) saveBtn.style.display = 'none';
            
            const fechaValidation = document.getElementById('fechaValidation');
            if (fechaValidation) fechaValidation.innerHTML = '';
            const globalValidation = document.getElementById('globalValidation');
            if (globalValidation) globalValidation.innerHTML = '';
            
            if (id) {
                if (titulo) titulo.innerText = '✏️ Editar turno';
                const turnoId = document.getElementById('turno_id');
                if (turnoId) turnoId.value = id;
                
                const fechaObj = new Date(fecha);
                selectedDateObj = fechaObj;
                currentDatePicker = new Date(fechaObj);
                updateFechaDisplay();
                
                const horaInicioInput = document.getElementById('turno_hora_inicio');
                const horaFinInput = document.getElementById('turno_hora_fin');
                if (horaInicioInput) horaInicioInput.value = hora_inicio.substring(0, 5);
                if (horaFinInput) horaFinInput.value = hora_fin.substring(0, 5);
                
                const tipoInput = document.getElementById('turno_tipo');
                if (tipoInput) tipoInput.value = tipo;
                
                const ubicacionInput = document.getElementById('turno_ubicacion');
                if (ubicacionInput) ubicacionInput.value = ubicacion || 'Oficina Principal';
                
                const departamentoInput = document.getElementById('turno_departamento');
                if (departamentoInput) departamentoInput.value = departamento || 'General';
                
                document.querySelectorAll('.tipo-option').forEach(opt => {
                    if (opt.dataset.tipo === tipo) opt.classList.add('active');
                    else opt.classList.remove('active');
                });
            } else {
                if (titulo) titulo.innerText = '✨ Crear nuevo turno';
                const turnoId = document.getElementById('turno_id');
                if (turnoId) turnoId.value = '';
                
                const hoy = new Date();
                selectedDateObj = hoy;
                currentDatePicker = new Date(hoy);
                updateFechaDisplay();
                
                const horaInicioInput = document.getElementById('turno_hora_inicio');
                const horaFinInput = document.getElementById('turno_hora_fin');
                if (horaInicioInput) horaInicioInput.value = '09:00';
                if (horaFinInput) horaFinInput.value = '17:00';
                
                const tipoInput = document.getElementById('turno_tipo');
                if (tipoInput) tipoInput.value = 'diurna';
                
                const ubicacionInput = document.getElementById('turno_ubicacion');
                if (ubicacionInput) ubicacionInput.value = 'Oficina Principal';
                
                const departamentoInput = document.getElementById('turno_departamento');
                if (departamentoInput) departamentoInput.value = 'General';
                
                document.querySelectorAll('.tipo-option').forEach(opt => {
                    if (opt.dataset.tipo === 'diurna') opt.classList.add('active');
                    else opt.classList.remove('active');
                });
            }
            
            calcularDuracionPreview();
            if (modal) modal.classList.add('active');
        }

        function cerrarModalTurno() {
            const modal = document.getElementById('modalTurno');
            if (modal) modal.classList.remove('active');
        }

        function guardarTurno() {
            const id = document.getElementById('turno_id');
            const fecha = document.getElementById('turno_fecha');
            const hora_inicio = document.getElementById('turno_hora_inicio');
            const hora_fin = document.getElementById('turno_hora_fin');
            const tipo = document.getElementById('turno_tipo');
            const ubicacion = document.getElementById('turno_ubicacion');
            const departamento = document.getElementById('turno_departamento');
            const globalValidation = document.getElementById('globalValidation');
            const saveBtn = document.getElementById('saveBtn');
            
            if (!fecha || !fecha.value || !hora_inicio || !hora_inicio.value || !hora_fin || !hora_fin.value) {
                if (globalValidation) { globalValidation.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Por favor completa todos los campos'; globalValidation.className = 'validation-message-global error'; }
                return;
            }
            
            // Animación de carga en el botón
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Guardando...';
            saveBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('accion', 'guardar');
            formData.append('id', id ? id.value : '');
            formData.append('fecha', fecha.value);
            formData.append('hora_inicio', hora_inicio.value);
            formData.append('hora_fin', hora_fin.value);
            formData.append('tipo', tipo ? tipo.value : 'diurna');
            formData.append('ubicacion', ubicacion ? ubicacion.value : 'Oficina Principal');
            formData.append('departamento', departamento ? departamento.value : 'General');
            
            fetch('ajax_turnos.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalTurno();
                        // Mostrar mensaje de éxito antes de recargar
                        const successMsg = document.createElement('div');
                        successMsg.className = 'mensaje success';
                        successMsg.innerHTML = '<i class="fas fa-check-circle"></i> ✅ Turno agregado correctamente';
                        successMsg.style.animation = 'slideDown 0.4s ease';
                        document.querySelector('.dashboard-container').insertBefore(successMsg, document.querySelector('.stats-cards-grid'));
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        if (globalValidation) { globalValidation.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Error al guardar'); globalValidation.className = 'validation-message-global error'; }
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    }
                });
        }

        function editarTurno(id, fecha, hora_inicio, hora_fin, tipo, ubicacion, departamento) { abrirModalTurno(id, fecha, hora_inicio, hora_fin, tipo, ubicacion, departamento); }

        function eliminarTurno(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este turno de tu agenda?')) {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id', id);
                fetch('ajax_turnos.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); else alert('Error al eliminar el turno'); });
            }
        }

        function eliminarTurnosPasados() {
            if (confirm('¿Eliminar todos los turnos que ya pasaron? Esta acción no se puede deshacer.')) {
                const formData = new FormData();
                formData.append('accion', 'eliminar_pasados');
                fetch('ajax_turnos.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); else alert('Error al eliminar turnos pasados'); });
            }
        }

        function confirmarTurno(id, buttonElement) {
            const formData = new FormData();
            formData.append('accion', 'cambiar_estado');
            formData.append('id', id);
            formData.append('estado', 'confirmado');
            
            fetch('ajax_turnos.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tarjeta = buttonElement.closest('.turno-card');
                        if (tarjeta) {
                            const badge = tarjeta.querySelector('.turno-estado-badge');
                            const buttonsDiv = tarjeta.querySelector('.turno-buttons');
                            if (badge) { badge.className = 'turno-estado-badge confirmado'; badge.innerHTML = '✅ Confirmado'; }
                            if (buttonsDiv) buttonsDiv.style.display = 'none';
                            const confirmadoDiv = document.createElement('div');
                            confirmadoDiv.style.marginTop = '8px';
                            confirmadoDiv.style.textAlign = 'center';
                            confirmadoDiv.style.color = 'var(--success)';
                            confirmadoDiv.style.fontSize = '10px';
                            confirmadoDiv.innerHTML = '<i class="fas fa-check-circle"></i> Turno confirmado';
                            tarjeta.appendChild(confirmadoDiv);
                            tarjeta.setAttribute('data-estado', 'confirmado');
                        }
                    } else { alert('Error al confirmar el turno'); }
                });
        }

        function declinarTurno(id) { if (confirm('¿Estás seguro de que quieres declinar este turno?')) eliminarTurno(id); }

        function mostrarDetalleTurno(element) {
            const id = element.getAttribute('data-id');
            const fecha = element.getAttribute('data-fecha');
            const horaInicio = element.getAttribute('data-hora-inicio');
            const horaFin = element.getAttribute('data-hora-fin');
            const tipo = element.getAttribute('data-tipo');
            const ubicacion = element.getAttribute('data-ubicacion');
            const departamento = element.getAttribute('data-departamento');
            const estado = element.getAttribute('data-estado');
            
            const fechaObj = new Date(fecha);
            const diasSemanaLista = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            const mesesLista = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const diaSemana = diasSemanaLista[fechaObj.getDay()];
            const fechaFormateada = diaSemana + ' ' + fechaObj.getDate() + ' de ' + mesesLista[fechaObj.getMonth()] + ' de ' + fechaObj.getFullYear();
            const duracion = calcularDuracionDesdeHoras(horaInicio, horaFin);
            const iconoTipo = tipo === 'diurna' ? '🌞' : (tipo === 'nocturna' ? '🌙' : '⚡');
            const nombreTipo = tipo === 'diurna' ? 'Diurna' : (tipo === 'nocturna' ? 'Nocturna' : 'Extras');
            let estadoTexto = estado, estadoClase = estado;
            if (estado === 'pendiente') { estadoTexto = '⏳ Pendiente'; estadoClase = 'pendiente'; }
            else if (estado === 'confirmado') { estadoTexto = '✅ Confirmado'; estadoClase = 'confirmado'; }
            else if (estado === 'completado') { estadoTexto = '✓ Completado'; estadoClase = 'completado'; }
            else if (estado === 'vencido') { estadoTexto = '📅 Vencido'; estadoClase = 'vencido'; }
            
            const html = `
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="text-align: center;"><div style="font-size: 48px; font-weight: 800;">${fechaObj.getDate()}</div><div style="color: var(--text-light);">${fechaFormateada}</div></div>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);"><span><i class="far fa-clock"></i> Horario</span><span style="font-weight: 600;">${horaInicio.substring(0,5)} - ${horaFin.substring(0,5)}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);"><span><i class="fas fa-hourglass-half"></i> Duración</span><span style="font-weight: 600;">${duracion}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);"><span><i class="fas fa-tag"></i> Tipo</span><span style="font-weight: 600;">${iconoTipo} ${nombreTipo}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);"><span><i class="fas fa-location-dot"></i> Ubicación</span><span style="font-weight: 600;">${ubicacion || 'Oficina Principal'}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);"><span><i class="fas fa-building"></i> Departamento</span><span style="font-weight: 600;">${departamento || 'General'}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0;"><span><i class="fas fa-flag-checkered"></i> Estado</span><span class="turno-estado-badge ${estadoClase}" style="margin:0;">${estadoTexto}</span></div>
                </div>
            `;
            
            const detalleBody = document.getElementById('detalleTurnoBody');
            const detalleEditarBtn = document.getElementById('detalleEditarBtn');
            const modalDetalle = document.getElementById('modalDetalleTurno');
            if (detalleBody) detalleBody.innerHTML = html;
            if (detalleEditarBtn) detalleEditarBtn.onclick = function() { cerrarModalDetalle(); editarTurno(id, fecha, horaInicio, horaFin, tipo, ubicacion, departamento); };
            if (modalDetalle) modalDetalle.classList.add('active');
        }

        function cerrarModalDetalle() { const modal = document.getElementById('modalDetalleTurno'); if (modal) modal.classList.remove('active'); }

        function calcularDuracionDesdeHoras(inicio, fin) {
            const inicioTs = new Date(`2000-01-01T${inicio}`).getTime();
            const finTs = new Date(`2000-01-01T${fin}`).getTime();
            const diffHoras = (finTs - inicioTs) / 3600000;
            if (diffHoras <= 0) return '0h';
            const horas = Math.floor(diffHoras);
            const minutos = Math.round((diffHoras - horas) * 60);
            if (minutos > 0) return `${horas}h ${minutos}m`;
            return `${horas}h`;
        }

        // ========== CARRUSEL ==========
        let currentIndex = 0, totalCards = 0, cardsPerView = 3, cardWidth = 226;

        function actualizarCardsPorVista() {
            const width = window.innerWidth;
            if (width <= 600) { cardsPerView = 1; cardWidth = 176; }
            else if (width <= 900) { cardsPerView = 2; cardWidth = 196; }
            else { cardsPerView = 3; cardWidth = 226; }
            actualizarFlechas();
        }

        function actualizarFlechas() {
            const leftArrow = document.getElementById('scrollArrowLeft');
            const rightArrow = document.getElementById('scrollArrowRight');
            const container = document.getElementById('turnosHorizontal');
            if (!container || !leftArrow || !rightArrow) return;
            totalCards = container.children.length;
            const maxIndex = Math.max(0, totalCards - cardsPerView);
            if (currentIndex <= 0) leftArrow.classList.add('disabled'); else leftArrow.classList.remove('disabled');
            if (currentIndex >= maxIndex) rightArrow.classList.add('disabled'); else rightArrow.classList.remove('disabled');
            if (totalCards <= cardsPerView) { leftArrow.style.display = 'none'; rightArrow.style.display = 'none'; }
            else { leftArrow.style.display = 'flex'; rightArrow.style.display = 'flex'; }
        }

        function scrollTurnosCarrusel(direccion) {
            const container = document.getElementById('turnosHorizontal');
            if (!container) return;
            totalCards = container.children.length;
            const maxIndex = Math.max(0, totalCards - cardsPerView);
            if (direccion === 'left' && currentIndex > 0) { currentIndex -= cardsPerView; if (currentIndex < 0) currentIndex = 0; }
            else if (direccion === 'right' && currentIndex < maxIndex) { currentIndex += cardsPerView; if (currentIndex > maxIndex) currentIndex = maxIndex; }
            else return;
            const desplazamiento = currentIndex * cardWidth;
            container.style.transform = `translateX(-${desplazamiento}px)`;
            actualizarFlechas();
        }

        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                actualizarCardsPorVista();
                if (currentIndex > 0) {
                    const container = document.getElementById('turnosHorizontal');
                    if (container) { const desplazamiento = currentIndex * cardWidth; container.style.transform = `translateX(-${desplazamiento}px)`; }
                }
                actualizarFlechas();
            }, 150);
        });

        // ========== JUSTIFICACIONES ==========
        let justificacionData = {};

        function justificarRetardo() { justificacionData = { tipo: 'retardo' }; document.getElementById('modalJustificacionTitulo').innerText = 'Justificar retardo'; document.getElementById('modalJustificacion').classList.add('active'); }
        function justificarSalidaTemprana() { justificacionData = { tipo: 'salida_temprana' }; document.getElementById('modalJustificacionTitulo').innerText = 'Justificar salida temprana'; document.getElementById('modalJustificacion').classList.add('active'); }
        function justificarHorasExtras() { justificacionData = { tipo: 'extra_no_programada' }; document.getElementById('modalJustificacionTitulo').innerText = 'Justificar horas extras'; document.getElementById('modalJustificacion').classList.add('active'); }
        function cerrarModalJustificacion() { document.getElementById('modalJustificacion').classList.remove('active'); document.getElementById('justificacion_motivo').value = ''; document.getElementById('justificacion_archivo').value = ''; }
        function enviarJustificacion() {
            const motivo = document.getElementById('justificacion_motivo').value;
            const archivo = document.getElementById('justificacion_archivo').files[0];
            if (!motivo) { alert('Por favor escribe un motivo'); return; }
            const formData = new FormData();
            formData.append('tipo', justificacionData.tipo);
            formData.append('motivo', motivo);
            if (archivo) formData.append('archivo', archivo);
            fetch('ajax_justificaciones.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => { if (data.success) { alert('Justificación enviada correctamente. El administrador la revisará.'); cerrarModalJustificacion(); location.reload(); } else { alert('Error al enviar la justificación'); } });
        }

        // ========== INICIALIZACIÓN ==========
        document.addEventListener('DOMContentLoaded', function() {
            initDatepicker();
            initTipoSelector();
            actualizarCardsPorVista();
            
            window.addEventListener('scroll', function() {
                const counterElement = document.getElementById('hoursCount');
                if (counterElement && !counterElement.hasAttribute('data-animated')) {
                    const rect = counterElement.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        initCardAnimations();
                    }
                }
            });
            
            const cards = document.querySelectorAll('.turno-card');
            cards.forEach((card, index) => {
                card.style.animation = 'fadeSlideUp 0.4s ease backwards';
                card.style.animationDelay = `${index * 0.05}s`;
                card.addEventListener('click', function(e) { if (e.target.closest('.turno-card-actions') || e.target.closest('.turno-buttons')) return; mostrarDetalleTurno(this); });
            });
        });
    </script>
    <script src="script.js"></script>
</body>
</html>