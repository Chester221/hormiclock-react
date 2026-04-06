<?php
// ============================================
// GESTIÓN DE AUSENCIAS - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}

$es_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
$user_id = $_SESSION['user_id'];
$mensaje = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de validación. Intenta de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';
        
        if ($accion === 'solicitar') {
            $tipo = $_POST['tipo'] ?? '';
            $fecha_inicio = $_POST['fecha_inicio'] ?? '';
            $fecha_fin = $_POST['fecha_fin'] ?? '';
            $motivo = trim($_POST['motivo'] ?? '');
            
            if (empty($tipo) || empty($fecha_inicio) || empty($fecha_fin)) {
                $error = 'Todos los campos son obligatorios.';
            } elseif (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
                $error = 'La fecha de fin debe ser posterior a la fecha de inicio.';
            } else {
                try {
                    // Verificar si la tabla existe, si no, crearla
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS ausencias (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            tipo ENUM('vacaciones', 'enfermedad', 'personal', 'otro') NOT NULL,
                            fecha_inicio DATE NOT NULL,
                            fecha_fin DATE NOT NULL,
                            motivo TEXT,
                            estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                            aprobado_por INT NULL,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (aprobado_por) REFERENCES users(id) ON DELETE SET NULL
                        )
                    ");
                    
                    // Verificar si ya existe una solicitud en el mismo período
                    $check = $conn->prepare("
                        SELECT COUNT(*) FROM ausencias 
                        WHERE user_id = ? 
                        AND estado IN ('pendiente', 'aprobado')
                        AND (
                            (fecha_inicio <= ? AND fecha_fin >= ?) OR
                            (fecha_inicio <= ? AND fecha_fin >= ?) OR
                            (fecha_inicio >= ? AND fecha_fin <= ?)
                        )
                    ");
                    $check->execute([
                        $user_id, 
                        $fecha_inicio, $fecha_inicio,
                        $fecha_fin, $fecha_fin,
                        $fecha_inicio, $fecha_fin
                    ]);
                    
                    if ($check->fetchColumn() > 0) {
                        $error = 'Ya tienes una solicitud de ausencia en ese período.';
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO ausencias (user_id, tipo, fecha_inicio, fecha_fin, motivo, estado)
                            VALUES (?, ?, ?, ?, ?, 'pendiente')
                        ");
                        $stmt->execute([$user_id, $tipo, $fecha_inicio, $fecha_fin, $motivo]);
                        
                        $mensaje = 'Solicitud de ausencia enviada correctamente.';
                        
                        // Notificar a administradores
                        if ($es_admin) {
                            // Si es admin, notificar a otros admins
                            $admins = $conn->query("SELECT id FROM users WHERE role = 'admin' AND id != $user_id")->fetchAll();
                        } else {
                            // Si es usuario, notificar a todos los admins
                            $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                        }
                        
                        foreach ($admins as $admin) {
                            Notificaciones::nuevaSolicitudAusencia(
                                $admin['id'],
                                $_SESSION['name'],
                                $tipo,
                                $fecha_inicio,
                                $fecha_fin
                            );
                        }
                        
                        // Registrar actividad
                        logActivity($conn, $_SESSION['email'], 'solicitar_ausencia', "Tipo: $tipo");
                    }
                    
                } catch (Exception $e) {
                    $error = 'Error al guardar la solicitud: ' . $e->getMessage();
                }
            }
            
        } elseif ($accion === 'aprobar' && $es_admin) {
            $id = (int)$_POST['id'];
            try {
                $stmt = $conn->prepare("
                    UPDATE ausencias 
                    SET estado = 'aprobado', aprobado_por = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $id]);
                
                // Obtener información para notificar
                $info = $conn->prepare("
                    SELECT a.*, u.name, u.email, u.id as user_id
                    FROM ausencias a
                    JOIN users u ON a.user_id = u.id
                    WHERE a.id = ?
                ");
                $info->execute([$id]);
                $ausencia = $info->fetch();
                
                Notificaciones::estadoAusencia(
                    $ausencia['user_id'],
                    $ausencia['tipo'],
                    'aprobado',
                    $ausencia['fecha_inicio'],
                    $ausencia['fecha_fin']
                );
                
                $mensaje = 'Ausencia aprobada.';
            } catch (Exception $e) {
                $error = 'Error al aprobar la ausencia.';
            }
            
        } elseif ($accion === 'rechazar' && $es_admin) {
            $id = (int)$_POST['id'];
            try {
                $stmt = $conn->prepare("
                    UPDATE ausencias 
                    SET estado = 'rechazado', aprobado_por = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $id]);
                
                // Obtener información para notificar
                $info = $conn->prepare("
                    SELECT a.*, u.name, u.email, u.id as user_id
                    FROM ausencias a
                    JOIN users u ON a.user_id = u.id
                    WHERE a.id = ?
                ");
                $info->execute([$id]);
                $ausencia = $info->fetch();
                
                Notificaciones::estadoAusencia(
                    $ausencia['user_id'],
                    $ausencia['tipo'],
                    'rechazado',
                    $ausencia['fecha_inicio'],
                    $ausencia['fecha_fin']
                );
                
                $mensaje = 'Ausencia rechazada.';
            } catch (Exception $e) {
                $error = 'Error al rechazar la ausencia.';
            }
            
        } elseif ($accion === 'eliminar') {
            $id = (int)$_POST['id'];
            
            try {
                // Verificar permisos
                if ($es_admin) {
                    $stmt = $conn->prepare("DELETE FROM ausencias WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("DELETE FROM ausencias WHERE id = ? AND user_id = ? AND estado = 'pendiente'");
                    $stmt->execute([$id, $user_id]);
                }
                $stmt->execute([$id]);
                $mensaje = 'Ausencia eliminada.';
            } catch (Exception $e) {
                $error = 'Error al eliminar la ausencia.';
            }
        }
    }
}

// Obtener ausencias según el rol
try {
    if ($es_admin) {
        // Admin ve todas las ausencias con filtros
        $filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
        $filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
        
        $sql = "
            SELECT a.*, 
                   u.name as empleado_nombre, 
                   u.email,
                   u.department_id,
                   d.name as department_name,
                   ap.name as aprobado_por_nombre
            FROM ausencias a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN users ap ON a.aprobado_por = ap.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filtro_estado)) {
            $sql .= " AND a.estado = ?";
            $params[] = $filtro_estado;
        }
        
        if (!empty($filtro_tipo)) {
            $sql .= " AND a.tipo = ?";
            $params[] = $filtro_tipo;
        }
        
        $sql .= " ORDER BY a.fecha_inicio DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $ausencias = $stmt->fetchAll();
        
    } else {
        // Usuario normal solo ve las suyas
        $stmt = $conn->prepare("
            SELECT a.*, 
                   u.name as aprobado_por_nombre
            FROM ausencias a
            LEFT JOIN users u ON a.aprobado_por = u.id
            WHERE a.user_id = ? 
            ORDER BY a.fecha_inicio DESC
        ");
        $stmt->execute([$user_id]);
        $ausencias = $stmt->fetchAll();
    }
    
    // Estadísticas
    $stats = [
        'total' => count($ausencias),
        'pendientes' => 0,
        'aprobadas' => 0,
        'rechazadas' => 0,
        'dias_totales' => 0,
        'vacaciones_restantes' => 15
    ];
    
    foreach ($ausencias as $a) {
        $dias = (strtotime($a['fecha_fin']) - strtotime($a['fecha_inicio'])) / 86400 + 1;
        $stats['dias_totales'] += $dias;
        
        switch ($a['estado']) {
            case 'pendiente': $stats['pendientes']++; break;
            case 'aprobado': $stats['aprobadas']++; break;
            case 'rechazado': $stats['rechazadas']++; break;
        }
    }
    
    // Calcular vacaciones restantes (para el usuario)
    if (!$es_admin) {
        try {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(DATEDIFF(fecha_fin, fecha_inicio) + 1), 0) as dias_tomados
                FROM ausencias 
                WHERE user_id = ? AND tipo = 'vacaciones' AND estado = 'aprobado'
                AND YEAR(fecha_inicio) = YEAR(CURDATE())
            ");
            $stmt->execute([$user_id]);
            $dias_tomados = $stmt->fetchColumn();
            $stats['vacaciones_restantes'] = max(0, 15 - $dias_tomados);
        } catch (Exception $e) {
            // Error, mantener valor por defecto
        }
    }
    
} catch (Exception $e) {
    $ausencias = [];
    $stats = [
        'total' => 0,
        'pendientes' => 0,
        'aprobadas' => 0,
        'rechazadas' => 0,
        'dias_totales' => 0,
        'vacaciones_restantes' => 15
    ];
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Verificar si se solicita el formulario
$mostrar_formulario = isset($_GET['action']) && $_GET['action'] === 'solicitar';

$tema = $_COOKIE['tema'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ausencias | BytesClock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f8fafc;
            display: flex;
            min-height: 100vh;
        }

        body.dark-mode {
            background: #0f172a;
        }

        .sidebar {
            width: 260px;
            background: white;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid #e2e8f0;
        }

        body.dark-mode .sidebar {
            background: #1e293b;
            border-right-color: #334155;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        body.dark-mode .page-header h1 {
            color: #f1f5f9;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59,130,246,0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            text-decoration: none;
        }

        body.dark-mode .btn-secondary {
            background: #0f172a;
            color: #94a3b8;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        body.dark-mode .btn-secondary:hover {
            background: #1e293b;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .stat-card {
            background: #1e293b;
            border-color: #334155;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.blue { background: #eef2ff; color: #3b82f6; }
        .stat-icon.green { background: #f0fdf4; color: #22c55e; }
        .stat-icon.orange { background: #fff7ed; color: #f97316; }
        .stat-icon.purple { background: #faf5ff; color: #8b5cf6; }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }

        body.dark-mode .stat-value {
            color: #f1f5f9;
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
        }

        /* Filtros */
        .filters-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        body.dark-mode .filters-section {
            background: #1e293b;
            border-color: #334155;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }

        .filter-select {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 13px;
            background: white;
            color: #0f172a;
            min-width: 150px;
        }

        body.dark-mode .filter-select {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
        }

        /* Formulario */
        .form-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        body.dark-mode .form-card {
            background: #1e293b;
            border-color: #334155;
        }

        .form-card h2 {
            color: #0f172a;
            margin-bottom: 25px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .form-card h2 {
            color: #f1f5f9;
        }

        .form-card h2 i {
            color: #3b82f6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
            color: #0f172a;
        }

        body.dark-mode .form-control {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .form-control:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-submit {
            flex: 1;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background: #2563eb;
        }

        .btn-cancel {
            flex: 1;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 14px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        body.dark-mode .btn-cancel {
            background: #0f172a;
            color: #94a3b8;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        /* Tabla */
        .table-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .table-card {
            background: #1e293b;
            border-color: #334155;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .ausencias-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ausencias-table th {
            text-align: left;
            padding: 15px 20px;
            color: #64748b;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        body.dark-mode .ausencias-table th {
            border-bottom-color: #334155;
        }

        .ausencias-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #0f172a;
            font-size: 14px;
        }

        body.dark-mode .ausencias-table td {
            border-bottom-color: #334155;
            color: #f1f5f9;
        }

        .ausencias-table tr:hover td {
            background: #f8fafc;
        }

        body.dark-mode .ausencias-table tr:hover td {
            background: #0f172a;
        }

        .badge-tipo {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
        }

        .tipo-vacaciones {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .tipo-enfermedad {
            background: #ffebee;
            color: #c62828;
        }

        .tipo-personal {
            background: #fff3e0;
            color: #f97316;
        }

        .tipo-otro {
            background: #eef2ff;
            color: #3b82f6;
        }

        .badge-estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
        }

        .estado-pendiente {
            background: #fff3e0;
            color: #f97316;
        }

        .estado-aprobado {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .estado-rechazado {
            background: #ffebee;
            color: #c62828;
        }

        .btn-accion {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 2px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-aprobar {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .btn-aprobar:hover {
            background: #2e7d32;
            color: white;
        }

        .btn-rechazar {
            background: #ffebee;
            color: #c62828;
        }

        .btn-rechazar:hover {
            background: #c62828;
            color: white;
        }

        .btn-eliminar {
            background: #ffebee;
            color: #c62828;
        }

        .btn-eliminar:hover {
            background: #c62828;
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        body.dark-mode .alert-success {
            background: #166534;
            border-color: #22c55e;
            color: #f0fdf4;
        }

        body.dark-mode .alert-error {
            background: #991b1b;
            border-color: #ef4444;
            color: #fef2f2;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 60px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 10px;
        }

        body.dark-mode .empty-state h3 {
            color: #f1f5f9;
        }

        .empty-state p {
            color: #64748b;
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .ausencias-table td {
                white-space: nowrap;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="<?= $tema === 'dark' ? 'dark-mode' : '' ?>">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>
                <i class="fa-regular fa-calendar-clock" style="color: #3b82f6;"></i>
                Gestión de Ausencias
            </h1>
            <?php if (!$mostrar_formulario): ?>
                <a href="?action=solicitar" class="btn-primary">
                    <i class="fa-regular fa-plus"></i>
                    Solicitar Ausencia
                </a>
            <?php else: ?>
                <a href="ausencias.php" class="btn-secondary">
                    <i class="fa-regular fa-arrow-left"></i>
                    Volver al listado
                </a>
            <?php endif; ?>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <i class="fa-regular fa-circle-check"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-regular fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total ausencias</h3>
                    <div class="stat-icon blue"><i class="fa-regular fa-clock"></i></div>
                </div>
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Registradas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Pendientes</h3>
                    <div class="stat-icon orange"><i class="fa-regular fa-hourglass"></i></div>
                </div>
                <div class="stat-value"><?= $stats['pendientes'] ?></div>
                <div class="stat-label">Por revisar</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Aprobadas</h3>
                    <div class="stat-icon green"><i class="fa-regular fa-circle-check"></i></div>
                </div>
                <div class="stat-value"><?= $stats['aprobadas'] ?></div>
                <div class="stat-label">Confirmadas</div>
            </div>

            <?php if (!$es_admin): ?>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Vacaciones</h3>
                    <div class="stat-icon purple"><i class="fa-regular fa-umbrella-beach"></i></div>
                </div>
                <div class="stat-value"><?= $stats['vacaciones_restantes'] ?></div>
                <div class="stat-label">Días restantes</div>
            </div>
            <?php else: ?>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Días totales</h3>
                    <div class="stat-icon purple"><i class="fa-regular fa-calendar"></i></div>
                </div>
                <div class="stat-value"><?= $stats['dias_totales'] ?></div>
                <div class="stat-label">Solicitados</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filtros (solo para admin) -->
        <?php if ($es_admin && !$mostrar_formulario && !empty($ausencias)): ?>
        <div class="filters-section">
            <div class="filter-group">
                <label>Filtrar por estado:</label>
                <select class="filter-select" onchange="window.location.href='?estado='+this.value">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= (isset($_GET['estado']) && $_GET['estado'] == 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                    <option value="aprobado" <?= (isset($_GET['estado']) && $_GET['estado'] == 'aprobado') ? 'selected' : '' ?>>Aprobado</option>
                    <option value="rechazado" <?= (isset($_GET['estado']) && $_GET['estado'] == 'rechazado') ? 'selected' : '' ?>>Rechazado</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Tipo:</label>
                <select class="filter-select" onchange="window.location.href='?tipo='+this.value">
                    <option value="">Todos</option>
                    <option value="vacaciones" <?= (isset($_GET['tipo']) && $_GET['tipo'] == 'vacaciones') ? 'selected' : '' ?>>Vacaciones</option>
                    <option value="enfermedad" <?= (isset($_GET['tipo']) && $_GET['tipo'] == 'enfermedad') ? 'selected' : '' ?>>Enfermedad</option>
                    <option value="personal" <?= (isset($_GET['tipo']) && $_GET['tipo'] == 'personal') ? 'selected' : '' ?>>Personal</option>
                    <option value="otro" <?= (isset($_GET['tipo']) && $_GET['tipo'] == 'otro') ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <?php if (isset($_GET['estado']) || isset($_GET['tipo'])): ?>
            <a href="ausencias.php" class="btn-secondary" style="padding: 8px 16px;">
                <i class="fa-regular fa-times"></i> Limpiar filtros
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Formulario de solicitud -->
        <?php if ($mostrar_formulario): ?>
            <div class="form-card">
                <h2>
                    <i class="fa-regular fa-calendar-plus"></i>
                    Solicitar Ausencia
                </h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="accion" value="solicitar">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de ausencia</label>
                            <select name="tipo" class="form-control" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="vacaciones">Vacaciones</option>
                                <option value="enfermedad">Enfermedad</option>
                                <option value="personal">Asuntos personales</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Fecha de inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Fecha de fin</label>
                            <input type="date" name="fecha_fin" class="form-control" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Días calculados</label>
                            <input type="text" class="form-control" id="diasCalculados" readonly value="0">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Motivo (opcional)</label>
                        <textarea name="motivo" class="form-control" 
                                  placeholder="Explica el motivo de tu ausencia..."></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="ausencias.php" class="btn-cancel">Cancelar</a>
                        <button type="submit" class="btn-submit">Enviar Solicitud</button>
                    </div>
                </form>
            </div>

            <script>
                // Calcular días automáticamente
                const fechaInicio = document.querySelector('input[name="fecha_inicio"]');
                const fechaFin = document.querySelector('input[name="fecha_fin"]');
                const diasCalculados = document.getElementById('diasCalculados');
                
                function calcularDias() {
                    if (fechaInicio.value && fechaFin.value) {
                        const inicio = new Date(fechaInicio.value);
                        const fin = new Date(fechaFin.value);
                        const diff = Math.ceil((fin - inicio) / (1000 * 60 * 60 * 24)) + 1;
                        diasCalculados.value = diff > 0 ? diff : 0;
                    }
                }
                
                fechaInicio.addEventListener('change', calcularDias);
                fechaFin.addEventListener('change', calcularDias);
            </script>
        <?php endif; ?>

        <!-- Listado de ausencias -->
        <?php if (!$mostrar_formulario): ?>
        <div class="table-card">
            <h2 style="color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-regular fa-list"></i>
                <?= $es_admin ? 'Todas las solicitudes' : 'Mis solicitudes' ?>
            </h2>

            <?php if (empty($ausencias)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-calendar-check"></i>
                    <h3>No hay ausencias registradas</h3>
                    <p><?= $es_admin ? 'Los empleados aún no han solicitado ausencias' : 'Solicita tu primera ausencia' ?></p>
                    <?php if (!$es_admin): ?>
                        <a href="?action=solicitar" class="btn-primary" style="margin-top: 20px; display: inline-flex;">
                            <i class="fa-regular fa-plus"></i> Solicitar ausencia
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ausencias-table">
                        <thead>
                            <tr>
                                <?php if ($es_admin): ?>
                                    <th>Empleado</th>
                                    <th>Departamento</th>
                                <?php endif; ?>
                                <th>Tipo</th>
                                <th>Período</th>
                                <th>Días</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Gestionado por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ausencias as $ausencia): 
                                $dias = (strtotime($ausencia['fecha_fin']) - strtotime($ausencia['fecha_inicio'])) / 86400 + 1;
                            ?>
                            <tr>
                                <?php if ($es_admin): ?>
                                    <td>
                                        <strong><?= htmlspecialchars($ausencia['empleado_nombre']) ?></strong>
                                        <br>
                                        <small style="color: #64748b;"><?= htmlspecialchars($ausencia['email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($ausencia['department_name'] ?? 'Sin depto') ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge-tipo tipo-<?= $ausencia['tipo'] ?>">
                                        <?= ucfirst($ausencia['tipo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($ausencia['fecha_inicio'])) ?><br>
                                    <small style="color: #64748b;">al <?= date('d/m/Y', strtotime($ausencia['fecha_fin'])) ?></small>
                                </td>
                                <td><strong><?= $dias ?></strong> día(s)</td>
                                <td>
                                    <?php if ($ausencia['motivo']): ?>
                                        <span title="<?= htmlspecialchars($ausencia['motivo']) ?>">
                                            <?= htmlspecialchars(substr($ausencia['motivo'], 0, 30)) ?>...
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-estado estado-<?= $ausencia['estado'] ?>">
                                        <?= ucfirst($ausencia['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ausencia['aprobado_por_nombre']): ?>
                                        <?= htmlspecialchars($ausencia['aprobado_por_nombre']) ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <?php if ($es_admin && $ausencia['estado'] == 'pendiente'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="id" value="<?= $ausencia['id'] ?>">
                                                <button type="submit" name="accion" value="aprobar" 
                                                        class="btn-accion btn-aprobar" title="Aprobar">
                                                    <i class="fa-regular fa-circle-check"></i>
                                                </button>
                                            </form>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="id" value="<?= $ausencia['id'] ?>">
                                                <button type="submit" name="accion" value="rechazar" 
                                                        class="btn-accion btn-rechazar" title="Rechazar">
                                                    <i class="fa-regular fa-circle-xmark"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($ausencia['estado'] == 'pendiente' && (!$es_admin || $ausencia['user_id'] == $user_id)): ?>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('¿Eliminar esta solicitud?');">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="id" value="<?= $ausencia['id'] ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <button type="submit" class="btn-accion btn-eliminar" title="Eliminar">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <script src="script.js"></script>
</body>
</html>