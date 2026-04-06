<?php
require_once 'config.php';

// Verificar sesión
if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}

// Determinar si es admin para ver datos completos o solo los propios
$es_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// Obtener estadísticas generales
try {
    // Total de empleados (usuarios con rol 'user')
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stmt->execute();
    $total_empleados = $stmt->fetchColumn();
    
    // Empleados activos (con login en los últimos 7 días)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT user_email) 
        FROM user_activity 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND action = 'login'
    ");
    $stmt->execute();
    $empleados_activos = $stmt->fetchColumn() ?: 0;
    
    // Nuevos empleados este mes
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE role = 'user' 
        AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $stmt->execute();
    $nuevos_empleados = $stmt->fetchColumn();
    
    // Empleados en vacaciones (simulado - asumiendo que hay una tabla de ausencias)
    // Por ahora lo dejamos en 0 hasta que se cree la tabla
    $en_vacaciones = 0;
    
    // Horas totales trabajadas esta semana (simulado con eventos)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(
            TIMESTAMPDIFF(HOUR, 
                start, 
                COALESCE(end, DATE_ADD(start, INTERVAL 8 HOUR))
            )
        ), 0) as total_horas
        FROM events 
        WHERE YEARWEEK(start, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->execute();
    $horas_totales = $stmt->fetchColumn();
    
    // Promedio de horas por empleado activo
    $promedio_horas = $empleados_activos > 0 ? round($horas_totales / $empleados_activos, 1) : 0;
    
    // Turnos de hoy
    $stmt = $conn->prepare("
        SELECT e.*, u.name as employee_name, u.profile_image
        FROM events e
        JOIN users u ON e.user_id = u.id
        WHERE DATE(e.start) = CURDATE()
        ORDER BY e.start
    ");
    $stmt->execute();
    $turnos_hoy = $stmt->fetchAll();
    
    // Total turnos hoy
    $total_turnos_hoy = count($turnos_hoy);
    
    // Empleados en turno ahora (evento activo en este momento)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.user_id) as en_turno
        FROM events e
        WHERE e.start <= NOW() 
        AND e.end >= NOW()
    ");
    $stmt->execute();
    $en_turno_ahora = $stmt->fetchColumn() ?: 0;
    
    // Distribución por cargo (puestos)
    $stmt = $conn->prepare("
        SELECT p.name as position_name, COUNT(u.id) as count,
               ROUND(COUNT(u.id) * 100.0 / (
                   SELECT COUNT(*) FROM users WHERE role = 'user'
               )) as percentage
        FROM positions p
        LEFT JOIN users u ON u.position_id = p.id AND u.role = 'user'
        GROUP BY p.id, p.name
        HAVING count > 0
        ORDER BY count DESC
    ");
    $stmt->execute();
    $distribucion_cargos = $stmt->fetchAll();
    
    // Turnos por día de la semana (para el gráfico)
    $dias_semana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $turnos_por_dia = [];
    
    for ($i = 0; $i < 7; $i++) {
        $fecha = date('Y-m-d', strtotime("monday this week +$i days"));
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM events 
            WHERE DATE(start) = ?
        ");
        $stmt->execute([$fecha]);
        $turnos_por_dia[$dias_semana[$i]] = $stmt->fetchColumn();
    }
    
    // Días cubiertos esta semana (días con al menos un turno)
    $dias_cubiertos = count(array_filter($turnos_por_dia));
    
    // Cobertura semanal (porcentaje)
    $cobertura_semanal = round(($dias_cubiertos / 7) * 100);
    
    // Próximas ausencias (simulado)
    $proximas_ausencias = [];
    // Aquí iría la consulta real cuando tengas la tabla de ausencias
    
} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    // Valores por defecto en caso de error
    $total_empleados = 4;
    $nuevos_empleados = 3;
    $empleados_activos = 4;
    $horas_totales = 56;
    $promedio_horas = 14;
    $total_turnos_hoy = 7;
    $en_turno_ahora = 3;
    $dias_cubiertos = 3;
    $cobertura_semanal = 43;
    $en_vacaciones = 0;
    $turnos_por_dia = ['Lun' => 3, 'Mar' => 5, 'Mié' => 2, 'Jue' => 6, 'Vie' => 4, 'Sáb' => 3, 'Dom' => 1];
    $distribucion_cargos = [
        ['position_name' => 'Cajera', 'percentage' => 25],
        ['position_name' => 'Supervisor', 'percentage' => 25],
        ['position_name' => 'Gerente de Ventas', 'percentage' => 25],
        ['position_name' => 'Vendedor', 'percentage' => 25]
    ];
}

// Obtener el nombre del usuario
$userName = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$firstName = explode(' ', trim($userName))[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Turnos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos para el dashboard de turnos */
        .dashboard-content {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .welcome-section {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: #29365c;
        }

        .date-badge {
            background: #eef2ff;
            padding: 10px 20px;
            border-radius: 40px;
            color: #7494ec;
            font-weight: 600;
        }

        /* Grid principal */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        /* Columna izquierda - Métricas principales */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .metric-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(116, 148, 236, 0.1);
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(116, 148, 236, 0.15);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .metric-header h3 {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-icon {
            width: 45px;
            height: 45px;
            background: #eef2ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7494ec;
            font-size: 1.5rem;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #29365c;
            margin-bottom: 5px;
        }

        .metric-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            color: #28a745;
        }

        .metric-trend i {
            font-size: 0.8rem;
        }

        .metric-sub {
            color: #888;
            font-size: 0.9rem;
        }

        /* Turnos por día */
        .weekly-shifts-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.3rem;
            color: #29365c;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .days-container {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .day-block {
            flex: 1;
            text-align: center;
            background: #f8fafd;
            border-radius: 16px;
            padding: 15px 10px;
            transition: all 0.3s ease;
        }

        .day-block:hover {
            background: #eef2ff;
            transform: translateY(-3px);
        }

        .day-name {
            font-weight: 600;
            color: #29365c;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .bar-container {
            height: 8px;
            background: #e0e7ff;
            border-radius: 20px;
            margin: 10px 0;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #7494ec, #a7bcff);
            border-radius: 20px;
            transition: width 0.3s ease;
        }

        .day-value {
            font-weight: 700;
            color: #29365c;
            font-size: 1.1rem;
        }

        .day-value small {
            font-size: 0.7rem;
            color: #888;
            font-weight: 400;
        }

        /* Distribución por cargo */
        .roles-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .role-item {
            margin-bottom: 20px;
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #29365c;
            font-weight: 500;
        }

        .role-percent {
            color: #7494ec;
            font-weight: 600;
        }

        .progress-bar {
            height: 10px;
            background: #eef2ff;
            border-radius: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 20px;
            transition: width 0.3s ease;
        }

        .progress-fill.cajera { background: #5fe996; }
        .progress-fill.supervisor { background: #ca6eff; }
        .progress-fill.gerente { background: #6d8eff; }
        .progress-fill.vendedor { background: #ffaa5e; }

        /* Turnos de hoy */
        .today-shifts-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .shift-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafd;
            border-radius: 16px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .shift-item:hover {
            background: #eef2ff;
        }

        .shift-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #7494ec;
        }

        .shift-info {
            flex: 1;
        }

        .shift-name {
            font-weight: 600;
            color: #29365c;
            margin-bottom: 5px;
        }

        .shift-time {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9rem;
        }

        .shift-time i {
            color: #7494ec;
            font-size: 0.9rem;
        }

        .shift-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Vacaciones y ausencias */
        .vacations-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .vacation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .vacation-header h2 {
            font-size: 1.3rem;
            color: #29365c;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .vacation-badge {
            background: #fff3e0;
            color: #ef6c00;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .vacation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafd;
            border-radius: 16px;
            margin-bottom: 10px;
        }

        .vacation-icon {
            width: 45px;
            height: 45px;
            background: #fff3e0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef6c00;
            font-size: 1.3rem;
        }

        .vacation-dates {
            font-size: 0.9rem;
            color: #666;
            margin-top: 3px;
        }

        .alert-card {
            background: #fff3e0;
            border-radius: 16px;
            padding: 15px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            background: #ef6c00;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            color: #29365c;
            margin-bottom: 3px;
        }

        .alert-text {
            font-size: 0.9rem;
            color: #666;
        }

        .btn-add {
            background: #eef2ff;
            color: #7494ec;
            border: none;
            padding: 12px 20px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-add:hover {
            background: #7494ec;
            color: white;
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .days-container {
                flex-wrap: wrap;
            }
            
            .day-block {
                min-width: calc(33.33% - 10px);
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <!-- ============================================ -->
<!-- VERIFICACIÓN DE ICONOS (visible solo para admin) -->
<!-- ============================================ -->
<?php if ($es_admin): ?>
<div style="background: #f8fafc; padding: 15px; margin-bottom: 20px; border-radius: 12px; border: 2px solid #e2e8f0;">
    <h4 style="margin-bottom: 10px;">🔍 Diagnóstico de Iconos</h4>
    
    <?php
    $iconos_a_verificar = [
        'users.svg', 'user-check.svg', 'clock-in.svg', 'clock-out.svg',
        'calendar.svg', 'calendar-check.svg', 'hours.svg', 'overtime.svg',
        'briefcase.svg', 'building.svg', 'sick.svg', 'vacation.svg',
        'personal.svg', 'settings.svg', 'break.svg', 'completed.svg',
        'punctuality.svg', 'average.svg', 'dashboard.svg'
    ];
    
    echo "<div style='display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;'>";
    
    foreach ($iconos_a_verificar as $icono) {
        $ruta_completa = 'icons/dashboard/' . $icono;
        $ruta_absoluta = $_SERVER['DOCUMENT_ROOT'] . '/icons/dashboard/' . $icono;
        
        // Verificar si el archivo existe en el sistema
        $existe_en_disco = file_exists($ruta_absoluta);
        
        // Verificar si se puede acceder via HTTP
        $url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/icons/dashboard/' . $icono;
        $headers = @get_headers($url);
        $accesible_via_http = $headers && strpos($headers[0], '200') !== false;
        
        $color = ($existe_en_disco && $accesible_via_http) ? '#22c55e' : '#ef4444';
        $mensaje = $existe_en_disco ? '✅ En disco' : '❌ No en disco';
        $mensaje .= $accesible_via_http ? ' · ✅ HTTP' : ' · ❌ HTTP';
        
        echo "<div style='padding: 10px; background: white; border-radius: 8px; border-left: 4px solid $color;'>";
        echo "<strong>$icono</strong><br>";
        echo "<span style='font-size: 12px; color: #666;'>$mensaje</span><br>";
        
        // Intentar cargar el icono para ver si se ve
        echo "<img src='icons/dashboard/$icono' alt='$icono' style='width: 32px; height: 32px; margin-top: 5px; border: 1px solid #ddd;' onerror='this.style.borderColor=\"red\"'>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Información de rutas
    echo "<p style='margin-top: 15px; font-size: 13px;'>";
    echo "<strong>Ruta relativa:</strong> icons/dashboard/<br>";
    echo "<strong>Ruta absoluta en disco:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "/icons/dashboard/<br>";
    echo "<strong>URL base:</strong> " . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . "/<br>";
    echo "</p>";
    ?>
</div>
<?php endif; ?>
    
    <main class="main-content">
        <div class="dashboard-content">
            <!-- Encabezado con saludo -->
            <div class="welcome-section">
                <div>
                    <h1>Panel de Control, <?= $firstName ?> 👋</h1>
                    <p style="color: #666;">Resumen general de turnos y empleados</p>
                </div>
                <div class="date-badge">
                    <i class="fa-regular fa-calendar"></i> <?= date('d/m/Y') ?>
                </div>
            </div>

            <!-- Grid principal de 2 columnas -->
            <div class="dashboard-grid">
                <!-- Columna izquierda -->
                <div>
                    <!-- Métricas principales -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-header">
                                <h3>Total Empleados</h3>
                                <div class="metric-icon">
                                    <img src="icons/dashboard/users.svg" alt="Empleados" style="width: 30px; height: 30px;">
                                </div>
                            </div>
                            <div class="metric-value"><?= $total_empleados ?></div>
                            <div class="metric-trend">
                                <i class="fa-solid fa-arrow-up"></i> +<?= $nuevos_empleados ?> este mes
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <h3>Turnos Semanales</h3>
                                <div class="metric-icon">
    <img src="icons/dashboard/calendar.svg" alt="Turnos" style="width: 30px; height: 30px;">
</div>
                            </div>
                            <div class="metric-value">7</div>
                            <div class="metric-sub"><?= $dias_cubiertos ?> días cubiertos</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <h3>Horas Totales</h3>
                                <div class="metric-icon">
    <img src="icons/dashboard/hours.svg" alt="Horas" style="width: 30px; height: 30px;">
</div>
                            </div>
                            <div class="metric-value"><?= $horas_totales ?>h</div>
                            <div class="metric-sub">Esta semana</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <h3>Empleados Activos</h3>
                                <div class="metric-icon">
    <img src="icons/dashboard/user-check.svg" alt="Activos" style="width: 30px; height: 30px;">
</div>
                            </div>
                            <div class="metric-value"><?= $empleados_activos ?></div>
                            <div class="metric-sub"><?= $promedio_horas ?>h promedio</div>
                        </div>
                    </div>

                    <!-- Turnos por día -->
                    <div class="weekly-shifts-card">
                        <div class="section-header">
                            <h2>
                                <i class="fa-solid fa-chart-simple" style="color: #7494ec;"></i>
                                Turnos por Día
                            </h2>
                            <span style="color: #666;">Distribución semanal</span>
                        </div>

                        <div class="days-container">
                            <?php foreach ($turnos_por_dia as $dia => $turnos): ?>
                            <div class="day-block">
                                <div class="day-name"><?= $dia ?></div>
                                <div class="bar-container">
                                    <div class="bar-fill" style="width: <?= min(100, ($turnos / 6) * 100) ?>%"></div>
                                </div>
                                <div class="day-value"><?= $turnos ?> <small>turnos</small></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Turnos de hoy -->
                    <div class="today-shifts-card">
                        <div class="section-header">
                            <h2>
                                <i class="fa-regular fa-clock" style="color: #7494ec;"></i>
                                Turnos de Hoy · <?= date('l', strtotime('today')) ?>
                            </h2>
                            <span class="shift-badge"><?= $en_turno_ahora ?> activos</span>
                        </div>

                        <?php if (!empty($turnos_hoy)): ?>
                            <?php foreach ($turnos_hoy as $turno): ?>
                            <div class="shift-item">
                                <img src="<?= $turno['profile_image'] ?? 'uploads/default.svg' ?>" 
                                     alt="Avatar" class="shift-avatar">
                                <div class="shift-info">
                                    <div class="shift-name"><?= htmlspecialchars($turno['employee_name']) ?></div>
                                    <div class="shift-time">
                                        <i class="fa-regular fa-clock"></i>
                                        <?= date('H:i', strtotime($turno['start'])) ?> - 
                                        <?= date('H:i', strtotime($turno['end'] ?? $turno['start'] . ' +8 hours')) ?>
                                    </div>
                                </div>
                                <span class="shift-badge">En turno</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #888; text-align: center; padding: 30px;">
                                <i class="fa-regular fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No hay turnos programados para hoy
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div>
                    <!-- Distribución por cargo -->
                    <div class="roles-card" style="margin-bottom: 25px;">
                        <div class="section-header">
                            <h2>
                                <i class="fa-solid fa-briefcase" style="color: #7494ec;"></i>
                                Distribución por Cargo
                            </h2>
                            <span style="color: #666;"><?= $total_empleados ?> empleados</span>
                        </div>

                        <?php foreach ($distribucion_cargos as $cargo): ?>
                        <div class="role-item">
                            <div class="role-header">
                                <span><?= htmlspecialchars($cargo['position_name']) ?></span>
                                <span class="role-percent"><?= $cargo['percentage'] ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?= strtolower(str_replace(' ', '', $cargo['position_name'])) ?>" 
                                     style="width: <?= $cargo['percentage'] ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Métricas rápidas de cargos -->
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <div style="flex: 1; background: #f8fafd; border-radius: 16px; padding: 15px; text-align: center;">
                                <div style="font-size: 1.8rem; font-weight: 700; color: #7494ec;"><?= $en_turno_ahora ?></div>
                                <div style="color: #666; font-size: 0.9rem;">Activos ahora</div>
                            </div>
                            <div style="flex: 1; background: #f8fafd; border-radius: 16px; padding: 15px; text-align: center;">
                                <div style="font-size: 1.8rem; font-weight: 700; color: #7494ec;"><?= round($total_turnos_hoy / max($total_empleados, 1), 1) ?></div>
                                <div style="color: #666; font-size: 0.9rem;">Turnos/empleado</div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 15px;">
                            <div style="flex: 1; background: #f8fafd; border-radius: 16px; padding: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fa-solid fa-chart-pie" style="color: #7494ec;"></i>
                                    <div>
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #29365c;"><?= $cobertura_semanal ?>%</div>
                                        <div style="color: #666; font-size: 0.8rem;">Cobertura semanal</div>
                                    </div>
                                </div>
                            </div>
                            <div style="flex: 1; background: #f8fafd; border-radius: 16px; padding: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fa-solid fa-umbrella-beach" style="color: #ef6c00;"></i>
                                    <div>
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #29365c;"><?= $en_vacaciones ?></div>
                                        <div style="color: #666; font-size: 0.8rem;">En vacaciones</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vacaciones y ausencias -->
                    <div class="vacations-card">
                        <div class="vacation-header">
                            <h2>
                                <i class="fa-regular fa-calendar-check" style="color: #7494ec;"></i>
                                Vacaciones y Ausencias
                            </h2>
                            <span class="vacation-badge">1 registradas</span>
                        </div>

                        <!-- Ejemplo de ausencia (puedes reemplazar con datos reales) -->
                        <div class="vacation-item">
                            <div class="vacation-icon">
                                <i class="fa-regular fa-sun"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #29365c;">Carlos Rodríguez</div>
                                <div class="vacation-dates">28 feb - 6 mar · Vacaciones de verano</div>
                            </div>
                        </div>

                        <button class="btn-add">
                            <i class="fa-regular fa-plus"></i>
                            Agregar Ausencia
                        </button>

                        <!-- Alertas -->
                        <div class="alert-card">
                            <div class="alert-icon">
                                <i class="fa-solid fa-exclamation"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">Días sin cobertura</div>
                                <div class="alert-text">4 día(s) sin turnos programados</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="script.js"></script>
</body>
</html>