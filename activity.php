<?php
// ============================================
// PÁGINA DE ACTIVIDAD - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}

$es_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

$target_user_id = $_SESSION['user_id'] ?? 0;

if (isset($_GET['id']) && $es_admin) {
    $id_from_url = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id_from_url) {
        $target_user_id = $id_from_url;
    }
}

$email_target = $_SESSION['email'];
$nombre_visto = "Mi Perfil";

if ($target_user_id != $_SESSION['user_id']) {
    $stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user_data = $stmt->fetch();
    if ($user_data) {
        $email_target = $user_data['email'];
        $nombre_visto = $user_data['name'] . " (ID: " . $target_user_id . ")";
    } else {
        header("Location: activity.php");
        exit();
    }
}

// Obtener límite de la URL (por defecto 100)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$allowed_limits = [25, 50, 100];
if (!in_array($limit, $allowed_limits)) {
    $limit = 100;
}

// Obtener filtro de acción (opcional)
$filtro_accion = isset($_GET['accion']) ? $_GET['accion'] : '';

try {
    // Construir consulta con filtro opcional
    $sql = "SELECT action, details, created_at FROM user_activity WHERE user_email = ?";
    $params = [$email_target];
    
    if (!empty($filtro_accion)) {
        $sql .= " AND action = ?";
        $params[] = $filtro_accion;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt_activity = $conn->prepare($sql);
    $stmt_activity->execute($params);
    $actividades = $stmt_activity->fetchAll();
    
    // Obtener acciones disponibles para el filtro
    $stmt_acciones = $conn->prepare("SELECT DISTINCT action FROM user_activity WHERE user_email = ? ORDER BY action");
    $stmt_acciones->execute([$email_target]);
    $acciones_disponibles = $stmt_acciones->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Error en activity.php: " . $e->getMessage());
    $actividades = [];
    $acciones_disponibles = [];
}

function getIconForAction($action) {
    switch ($action) {
        case 'login': return 'fa-right-to-bracket';
        case 'logout': return 'fa-right-from-bracket';
        case 'update_profile': return 'fa-user-pen';
        case 'create_event': return 'fa-calendar-plus';
        case 'update_event': return 'fa-calendar-pen';
        case 'delete_event': return 'fa-calendar-xmark';
        case 'confirmar_turno': return 'fa-circle-check';
        case 'solicitar_ausencia': return 'fa-clock';
        case 'timeout': return 'fa-hourglass-end';
        default: return 'fa-circle-info';
    }
}

function getBadgeColor($action) {
    switch ($action) {
        case 'login': return '#e3f2fd';
        case 'logout': return '#ffebee';
        case 'update_profile': return '#e8f5e9';
        case 'create_event': return '#fff3e0';
        case 'update_event': return '#fff3e0';
        case 'delete_event': return '#ffebee';
        case 'confirmar_turno': return '#e8f5e9';
        case 'solicitar_ausencia': return '#fff3e0';
        case 'timeout': return '#ffebee';
        default: return '#eef2ff';
    }
}

function getActionText($action) {
    switch ($action) {
        case 'login': return 'Inicio de sesión';
        case 'logout': return 'Cierre de sesión';
        case 'update_profile': return 'Actualización de perfil';
        case 'create_event': return 'Evento creado';
        case 'update_event': return 'Evento actualizado';
        case 'delete_event': return 'Evento eliminado';
        case 'confirmar_turno': return 'Turno confirmado';
        case 'solicitar_ausencia': return 'Ausencia solicitada';
        case 'timeout': return 'Sesión expirada';
        default: return ucfirst(str_replace('_', ' ', $action));
    }
}

$tema = $_COOKIE['tema'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividad de <?= htmlspecialchars($nombre_visto) ?> | BytesClock</title>
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

        /* Header de la página */
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

        .status-badge {
            background: #fff3e0;
            color: #ef6c00;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #ffe0b2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .status-badge {
            background: #1e3a5f;
            color: #90caf9;
            border-color: #334155;
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

        .filter-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 14px;
            background: white;
            color: #0f172a;
            min-width: 200px;
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

        .limit-buttons {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .btn-filter {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            background: #f1f5f9;
            color: #64748b;
            transition: all 0.2s;
        }

        body.dark-mode .btn-filter {
            background: #0f172a;
            color: #94a3b8;
        }

        .btn-filter:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-filter.active {
            background: #3b82f6;
            color: white;
        }

        /* Contenedor de actividad */
        .activity-container {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        body.dark-mode .activity-container {
            background: #1e293b;
            border-color: #334155;
        }

        .activity-stats {
            display: flex;
            gap: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        body.dark-mode .activity-stats {
            border-bottom-color: #334155;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: #eef2ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-size: 18px;
        }

        .stat-info h4 {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-info p {
            color: #0f172a;
            font-size: 18px;
            font-weight: 600;
        }

        body.dark-mode .stat-info p {
            color: #f1f5f9;
        }

        /* Tabla de actividad */
        .table-responsive {
            overflow-x: auto;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th {
            text-align: left;
            padding: 15px 20px;
            color: #64748b;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        body.dark-mode .activity-table th {
            border-bottom-color: #334155;
            color: #94a3b8;
        }

        .activity-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #0f172a;
            font-size: 14px;
        }

        body.dark-mode .activity-table td {
            border-bottom-color: #334155;
            color: #f1f5f9;
        }

        .activity-table tr:hover td {
            background: #f8fafc;
        }

        body.dark-mode .activity-table tr:hover td {
            background: #0f172a;
        }

        .action-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .action-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .action-name {
            font-weight: 500;
            text-transform: capitalize;
        }

        .date-cell {
            color: #0f172a;
            font-weight: 500;
        }

        body.dark-mode .date-cell {
            color: #f1f5f9;
        }

        .time-cell {
            color: #64748b;
            font-size: 13px;
        }

        .details-cell {
            color: #64748b;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Estados vacíos */
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .activity-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .limit-buttons {
                margin-left: 0;
                justify-content: center;
            }
            
            .activity-table td {
                white-space: nowrap;
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
                <i class="fa-regular fa-clock-rotate-left" style="color: #3b82f6;"></i>
                Historial de Actividad
                <?php if($es_admin && $target_user_id != $_SESSION['user_id']): ?>
                    <span class="status-badge">
                        <i class="fa-regular fa-eye"></i> Modo supervisor
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <?php if($es_admin): ?>
        <div class="filters-section">
            <div class="filter-label">
                <i class="fa-regular fa-magnifying-glass"></i>
                Supervisar otro usuario:
            </div>
            <form action="activity.php" method="GET" style="display: flex; gap: 10px; flex: 1;">
                <input type="number" name="id" placeholder="ID de usuario" 
                       value="<?= isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '' ?>"
                       style="flex: 1; padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 30px;">
                <button type="submit" style="padding: 10px 24px; background: #3b82f6; color: white; border: none; border-radius: 30px; cursor: pointer;">
                    <i class="fa-regular fa-arrow-right"></i>
                </button>
                <?php if(isset($_GET['id'])): ?>
                    <a href="activity.php" style="padding: 10px 24px; background: #f1f5f9; color: #64748b; border-radius: 30px; text-decoration: none;">
                        <i class="fa-regular fa-rotate-right"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <div class="activity-container">
            <div class="activity-stats">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fa-regular fa-list"></i>
                    </div>
                    <div class="stat-info">
                        <h4>Total registros</h4>
                        <p><?= count($actividades) ?></p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fa-regular fa-filter"></i>
                    </div>
                    <div class="stat-info">
                        <h4>Filtro actual</h4>
                        <p><?= $filtro_accion ?: 'Todos' ?></p>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <form action="activity.php" method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <?php if(isset($_GET['id'])): ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($_GET['id']) ?>">
                    <?php endif; ?>
                    
                    <select name="accion" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($acciones_disponibles as $accion): ?>
                            <option value="<?= htmlspecialchars($accion) ?>" <?= $filtro_accion == $accion ? 'selected' : '' ?>>
                                <?= htmlspecialchars(getActionText($accion)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if(!empty($filtro_accion)): ?>
                        <a href="?<?= isset($_GET['id']) ? 'id=' . htmlspecialchars($_GET['id']) : '' ?>" class="btn-filter" style="padding: 10px 16px;">
                            <i class="fa-regular fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
                
                <div class="limit-buttons">
                    <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 25])) ?>" class="btn-filter <?= $limit == 25 ? 'active' : '' ?>">25</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 50])) ?>" class="btn-filter <?= $limit == 50 ? 'active' : '' ?>">50</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 100])) ?>" class="btn-filter <?= $limit == 100 ? 'active' : '' ?>">100</a>
                </div>
            </div>

            <div class="table-responsive">
                <?php if (count($actividades) > 0): ?>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Acción</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actividades as $act): 
                                $fecha = new DateTime($act['created_at']);
                                $fecha_formateada = $fecha->format('d/m/Y');
                                $hora_formateada = $fecha->format('H:i');
                            ?>
                            <tr>
                                <td>
                                    <div class="date-cell"><?= $fecha_formateada ?></div>
                                    <div class="time-cell"><?= $hora_formateada ?></div>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <span class="action-icon" style="background: <?= getBadgeColor($act['action']) ?>;">
                                            <i class="fa-regular <?= getIconForAction($act['action']) ?>" style="color: #3b82f6;"></i>
                                        </span>
                                        <span class="action-name"><?= htmlspecialchars(getActionText($act['action'])) ?></span>
                                    </div>
                                </td>
                                <td class="details-cell" title="<?= htmlspecialchars($act['details'] ?? '') ?>">
                                    <?= htmlspecialchars($act['details'] ?? '') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-clock"></i>
                        <h3>No hay registros de actividad</h3>
                        <p><?= $filtro_accion ? 'No se encontraron actividades con ese filtro' : 'Este usuario no tiene actividad registrada' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="script.js"></script>
</body>
</html>