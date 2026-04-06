<?php
// ============================================
// ADMIN PAGE - DASHBOARD CON DATOS REALES
// ============================================

require_once 'config.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: user_page.php");
    exit();
}

$userName = htmlspecialchars($_SESSION['name'] ?? 'Administrador');
$firstName = explode(' ', trim($userName))[0];

// ============================================
// OBTENER DATOS REALES DE LA BASE DE DATOS
// ============================================

try {
    // 1. TOTAL EMPLEADOS (usuarios con rol 'user')
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stmt->execute();
    $total_empleados = $stmt->fetchColumn() ?: 0;
    
    // 2. ACTIVOS AHORA (usuarios con actividad en los últimos 15 minutos)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) 
        FROM user_activity 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute();
    $activos_ahora = $stmt->fetchColumn() ?: 0;
    
    // 3. TURNOS HOY (eventos programados para hoy)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM events 
        WHERE DATE(start) = CURDATE()
    ");
    $stmt->execute();
    $turnos_hoy = $stmt->fetchColumn() ?: 0;
    
    // 4. AUSENCIAS (contar ausencias activas - asumiendo que tienes tabla de ausencias)
    // Si no tienes la tabla, esto devolverá 0
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM ausencias 
        WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()
    ");
    $stmt->execute();
    $ausencias = $stmt->fetchColumn() ?: 0;
    
    // 5. ÚLTIMAS AUSENCIAS (para la tabla)
    $stmt = $conn->prepare("
        SELECT a.*, u.name as employee_name 
        FROM ausencias a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.fecha_inicio DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $ultimas_ausencias = $stmt->fetchAll();
    
    // 6. ÚLTIMOS EMPLEADOS (para la tabla)
    $stmt = $conn->prepare("
        SELECT name, email, created_at 
        FROM users 
        WHERE role = 'user'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $ultimos_empleados = $stmt->fetchAll();
    
    // 7. VARIACIÓN respecto al mes anterior (para el trend)
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as nuevos,
            (SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as anteriores
    ");
    $stmt->execute();
    $variacion = $stmt->fetch();
    $nuevos_mes = $variacion['nuevos'] ?? 0;
    $anteriores_mes = $variacion['anteriores'] ?? 0;
    
    if ($anteriores_mes > 0) {
        $porcentaje_variacion = round((($nuevos_mes - $anteriores_mes) / $anteriores_mes) * 100);
    } else {
        $porcentaje_variacion = $nuevos_mes > 0 ? 100 : 0;
    }
    $variacion_positivo = $porcentaje_variacion >= 0;
    
    // 8. Porcentaje de actividad (activos / total * 100)
    $porcentaje_actividad = $total_empleados > 0 ? round(($activos_ahora / $total_empleados) * 100) : 0;
    
} catch (Exception $e) {
    // Si hay error, valores por defecto
    error_log("Error en dashboard admin: " . $e->getMessage());
    $total_empleados = 0;
    $activos_ahora = 0;
    $turnos_hoy = 0;
    $ausencias = 0;
    $ultimas_ausencias = [];
    $ultimos_empleados = [];
    $nuevos_mes = 0;
    $porcentaje_variacion = 0;
    $variacion_positivo = true;
    $porcentaje_actividad = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard · BytesClock</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    
    <!-- Estilos principales -->
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* ============================================ */
        /* VARIABLES - MODO LIGHT (por defecto) */
        /* ============================================ */
        
        :root {
            --bg-primary: #f5f7fa;
            --card-bg: #FFFFFF;
            --card-border: #E6ECF8;
            --card-shadow: rgba(21, 66, 139, 0.08);
            --text-primary: #0B1B2B;
            --text-secondary: #4a5568;
            
            /* Colores de tarjetas */
            --color-1: #1E88E5; /* Azul - Total Empleados */
            --color-2: #26C6DA; /* Cian - Activos Ahora */
            --color-3: #7E57C2; /* Morado - Turnos Hoy */
            --color-4: #F57C00; /* Naranja - Ausencias */
            
            /* Estados */
            --success: #2ECC71;
            --danger: #E74C3C;
            --warning: #F39C12;
            
            /* Alternado para filas */
            --row-alt: #f8fafc;
        }
        
        /* ============================================ */
        /* MODO DARK */
        /* ============================================ */
        
        body.dark-mode {
            --bg-primary: #121212;
            --card-bg: #1E1E1E;
            --card-border: #2A2A2A;
            --card-shadow: rgba(0, 0, 0, 0.45);
            --text-primary: #E8F0FF;
            --text-secondary: #a0aec0;
            
            --color-1: #1E88E5;
            --color-2: #26C6DA;
            --color-3: #7E57C2;
            --color-4: #F57C00;
            
            --row-alt: #2A2A2A;
        }
        
        /* ============================================ */
        /* ESTILOS BASE */
        /* ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Layout */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }
        
        /* ============================================ */
        /* HEADER DEL DASHBOARD */
        /* ============================================ */
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .dashboard-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            animation: slideInLeft 0.5s ease;
        }
        
        .dashboard-header h1 span {
            color: var(--color-1);
            position: relative;
        }
        
        .dashboard-header h1 span::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--color-1), transparent);
            border-radius: 3px;
        }
        
        .date-badge {
            background: var(--card-bg);
            padding: 12px 24px;
            border-radius: 40px;
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 10px var(--card-shadow);
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.5s ease;
        }
        
        .date-badge i {
            color: var(--color-1);
            font-size: 1.1rem;
        }
        
        /* ============================================ */
        /* STATS GRID - ANIMACIÓN POR TARJETA */
        /* ============================================ */
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--card-border);
            box-shadow: 0 8px 20px var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            opacity: 0;
            animation: cardAppear 0.5s ease forwards;
        }
        
        /* Animaciones individuales por tarjeta */
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        @keyframes cardAppear {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Efecto hover con aumento de saturación */
        .stat-card:hover {
            transform: translateY(-5px);
            filter: saturate(1.08);
            box-shadow: 0 15px 30px var(--card-shadow);
        }
        
        /* Línea decorativa superior */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: currentColor;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        /* Colores específicos para cada tarjeta */
        .stat-card[data-card="1"] { color: var(--color-1); }
        .stat-card[data-card="2"] { color: var(--color-2); }
        .stat-card[data-card="3"] { color: var(--color-3); }
        .stat-card[data-card="4"] { color: var(--color-4); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            font-size: 2.2rem;
            color: currentColor;
            opacity: 0.9;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }
        
        .stat-value {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.2;
        }
        
        .stat-footer {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }
        
        .stat-footer i {
            color: currentColor;
        }
        
        /* Indicadores de estado */
        .trend-up {
            color: var(--success);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }
        
        .trend-down {
            color: var(--danger);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }
        
        .badge-status {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            background: color-mix(in srgb, currentColor 10%, transparent);
            color: currentColor;
        }
        
        /* ============================================ */
        /* CONTENT GRID */
        /* ============================================ */
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--card-border);
            box-shadow: 0 8px 20px var(--card-shadow);
            opacity: 0;
            animation: cardAppear 0.5s ease 0.5s forwards;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--card-border);
        }
        
        .card-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h2 i {
            color: var(--color-1);
        }
        
        .btn-link {
            color: var(--color-1);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.3s ease;
        }
        
        .btn-link:hover {
            gap: 10px;
            text-decoration: underline;
        }
        
        /* Tabla con filas alternadas */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            text-align: left;
            padding: 12px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--card-border);
        }
        
        .table td {
            padding: 12px 10px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--card-border);
        }
        
        .table tbody tr:nth-child(even) {
            background: var(--row-alt);
        }
        
        .table tbody tr:hover {
            background: color-mix(in srgb, var(--color-1) 5%, transparent);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
            color: currentColor;
        }
        
        /* Acciones rápidas */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 16px;
            color: var(--text-primary);
            text-decoration: none;
            border: 1px solid var(--card-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: currentColor;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .action-btn:hover::before {
            transform: translateX(0);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            filter: saturate(1.08);
            box-shadow: 0 10px 25px var(--card-shadow);
        }
        
        .action-btn i {
            font-size: 2rem;
            color: var(--color-1);
            transition: transform 0.3s ease;
        }
        
        .action-btn:hover i {
            transform: scale(1.1);
        }
        
        /* Colores específicos para cada acción */
        .action-btn:nth-child(1) i { color: var(--color-2); }
        .action-btn:nth-child(2) i { color: var(--color-4); }
        .action-btn:nth-child(3) i { color: var(--color-1); }
        .action-btn:nth-child(4) i { color: var(--color-3); }
        
        /* ============================================ */
        /* TOGGLE DE TEMA */
        /* ============================================ */
        
        .theme-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
        }
        
        .theme-toggle-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--color-1);
            color: var(--color-1);
            font-size: 1.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px var(--card-shadow);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }
        
        .theme-toggle-btn:hover {
            transform: scale(1.1) rotate(180deg);
            background: var(--color-1);
            color: white;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 5px 20px var(--card-shadow); }
            50% { box-shadow: 0 5px 30px var(--color-1); }
            100% { box-shadow: 0 5px 20px var(--card-shadow); }
        }
        
        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 80px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stat-value {
                font-size: 2.2rem;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="<?= isset($_COOKIE['tema']) && $_COOKIE['tema'] === 'dark' ? 'dark-mode' : '' ?>">
    
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-container">
            
            <!-- Header -->
            <div class="dashboard-header">
                <h1>Bienvenido, <span><?= $firstName ?></span></h1>
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i>
                    <?= date('d/m/Y') ?>
                </div>
            </div>
            
            <!-- Stats Grid con DATOS REALES -->
            <div class="stats-grid">
                
                <!-- Total Empleados - Azul #1E88E5 -->
                <div class="stat-card" data-card="1">
                    <div class="stat-header">
                        <h3>Total Empleados</h3>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-value"><?= $total_empleados ?></div>
                    <div class="stat-footer">
                        <?php if ($nuevos_mes > 0): ?>
                        <span class="trend-up">
                            <i class="fas fa-arrow-up"></i> +<?= $nuevos_mes ?>
                        </span>
                        nuevo(s) este mes
                        <?php else: ?>
                        <span>Sin cambios este mes</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Activos Ahora - Cian #26C6DA -->
                <div class="stat-card" data-card="2">
                    <div class="stat-header">
                        <h3>Activos Ahora</h3>
                        <i class="fas fa-user-check stat-icon"></i>
                    </div>
                    <div class="stat-value"><?= $activos_ahora ?></div>
                    <div class="stat-footer">
                        <?php if ($total_empleados > 0): ?>
                        <span class="badge-status" style="color: var(--color-2);">
                            <?= $porcentaje_actividad ?>% del total
                        </span>
                        <?php else: ?>
                        <span>Últimos 15 minutos</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Turnos Hoy - Morado #7E57C2 -->
                <div class="stat-card" data-card="3">
                    <div class="stat-header">
                        <h3>Turnos Hoy</h3>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-value"><?= $turnos_hoy ?></div>
                    <div class="stat-footer">
                        <i class="fas fa-calendar"></i>
                        Programados para hoy
                    </div>
                </div>
                
                <!-- Ausencias - Naranja #F57C00 -->
                <div class="stat-card" data-card="4">
                    <div class="stat-header">
                        <h3>Ausencias</h3>
                        <i class="fas fa-umbrella-beach stat-icon"></i>
                    </div>
                    <div class="stat-value"><?= $ausencias ?></div>
                    <div class="stat-footer">
                        <i class="fas fa-building"></i>
                        En período de ausencia
                    </div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                
                <!-- Últimas Ausencias -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-times"></i> Últimas Ausencias</h2>
                        <a href="ausencias.php" class="btn-link">
                            Ver todas <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (!empty($ultimas_ausencias)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Período</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimas_ausencias as $ausencia): ?>
                            <tr>
                                <td><?= htmlspecialchars($ausencia['employee_name'] ?? '') ?></td>
                                <td>
                                    <?= date('d/m', strtotime($ausencia['fecha_inicio'])) ?> - 
                                    <?= date('d/m', strtotime($ausencia['fecha_fin'])) ?>
                                </td>
                                <td>
                                    <?php 
                                    $hoy = new DateTime();
                                    $inicio = new DateTime($ausencia['fecha_inicio']);
                                    $fin = new DateTime($ausencia['fecha_fin']);
                                    if ($hoy >= $inicio && $hoy <= $fin) {
                                        echo '<span class="badge-status" style="color: var(--color-4);">En curso</span>';
                                    } elseif ($hoy < $inicio) {
                                        echo '<span class="badge-status" style="color: var(--color-2);">Próxima</span>';
                                    } else {
                                        echo '<span class="badge-status" style="color: var(--text-secondary);">Finalizada</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No hay ausencias registradas</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Últimos Empleados -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Últimos Empleados</h2>
                        <a href="users_list.php" class="btn-link">
                            Ver todos <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (!empty($ultimos_empleados)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_empleados as $empleado): ?>
                            <tr>
                                <td><?= htmlspecialchars($empleado['name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($empleado['email'] ?? '') ?></td>
                                <td><?= date('d/m/Y', strtotime($empleado['created_at'] ?? 'now')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No hay empleados registrados</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Acciones Rápidas -->
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2>
                    </div>
                    
                    <div class="quick-actions">
                        <a href="calendar.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Nuevo Turno</span>
                        </a>
                        <a href="ausencias.php" class="action-btn">
                            <i class="fas fa-calendar-times"></i>
                            <span>Registrar Ausencia</span>
                        </a>
                        <a href="users_list.php" class="action-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Nuevo Empleado</span>
                        </a>
                        <a href="departments.php" class="action-btn">
                            <i class="fas fa-building"></i>
                            <span>Nuevo Departamento</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Botón flotante para cambiar tema -->
    <div class="theme-toggle">
        <button class="theme-toggle-btn" onclick="toggleTheme()" title="Cambiar tema">
            <i class="fas <?= isset($_COOKIE['tema']) && $_COOKIE['tema'] === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
        </button>
    </div>
    
    <script src="script.js"></script>
    
    <script>
        function toggleTheme() {
            const body = document.body;
            body.classList.toggle('dark-mode');
            
            const icon = document.querySelector('.theme-toggle-btn i');
            const isDark = body.classList.contains('dark-mode');
            
            if (isDark) {
                icon.className = 'fas fa-sun';
                document.cookie = 'tema=dark; path=/; max-age=31536000';
            } else {
                icon.className = 'fas fa-moon';
                document.cookie = 'tema=light; path=/; max-age=31536000';
            }
            
            // Animación extra al cambiar tema
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                card.style.animation = 'none';
                setTimeout(() => {
                    card.style.animation = `cardAppear 0.5s ease ${index * 0.1}s forwards`;
                }, 10);
            });
        }
        
        // Actualizar cada 30 segundos
        setInterval(() => {
            location.reload(); // Recarga suave para actualizar datos
        }, 30000);
    </script>
</body>
</html>