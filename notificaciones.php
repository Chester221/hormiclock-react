<?php
// ============================================
// PÁGINA DE NOTIFICACIONES - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php");
    exit();
}

$userName = htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8');
$userId = $_SESSION['user_id'] ?? 0;
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'user';

// Asegurar que la tabla existe
Notificaciones::crearTabla();

// Obtener todas las notificaciones del usuario
try {
    $stmt = $conn->prepare("
        SELECT * FROM notificaciones 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC
    ");
    $stmt->execute([$userId]);
    $notificaciones = $stmt->fetchAll();
    
    // Contar no leídas
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM notificaciones 
        WHERE usuario_id = ? AND leida = FALSE
    ");
    $stmt->execute([$userId]);
    $no_leidas = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $notificaciones = [];
    $no_leidas = 0;
}

// Procesar acciones de marcar como leída
if (isset($_POST['marcar_todas'])) {
    try {
        Notificaciones::marcarTodasComoLeidas($userId);
        header("Location: notificaciones.php?marcadas=todas");
        exit();
    } catch (Exception $e) {
        $error = "Error al marcar notificaciones";
    }
}

if (isset($_POST['marcar_id'])) {
    $notif_id = (int)$_POST['marcar_id'];
    try {
        $stmt = $conn->prepare("
            UPDATE notificaciones 
            SET leida = TRUE, fecha_lectura = NOW() 
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$notif_id, $userId]);
        header("Location: notificaciones.php?marcada=ok");
        exit();
    } catch (Exception $e) {
        $error = "Error al marcar notificación";
    }
}

$tema = $_COOKIE['tema'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones | BytesClock</title>
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
            gap: 10px;
        }

        body.dark-mode .page-header h1 {
            color: #f1f5f9;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn-marcar-todas {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .btn-marcar-todas {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }

        .btn-marcar-todas:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .date-badge {
            background: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .date-badge {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }

        /* Grid de notificaciones */
        .notificaciones-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
        }

        body.dark-mode .stats-card {
            background: #1e293b;
            border-color: #334155;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
        }

        body.dark-mode .stat-label {
            color: #94a3b8;
        }

        .notificaciones-list {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        body.dark-mode .notificaciones-list {
            background: #1e293b;
            border-color: #334155;
        }

        .notificacion-item {
            display: flex;
            gap: 20px;
            padding: 25px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.2s;
            cursor: pointer;
        }

        body.dark-mode .notificacion-item {
            border-bottom-color: #334155;
        }

        .notificacion-item:last-child {
            border-bottom: none;
        }

        .notificacion-item:hover {
            background: #f8fafc;
        }

        body.dark-mode .notificacion-item:hover {
            background: #0f172a;
        }

        .notificacion-item.unread {
            background: #eff6ff;
        }

        body.dark-mode .notificacion-item.unread {
            background: #1e3a5f;
        }

        .notificacion-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-size: 22px;
        }

        body.dark-mode .notificacion-icon {
            background: #0f172a;
        }

        .notificacion-content {
            flex: 1;
        }

        .notificacion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notificacion-titulo {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }

        body.dark-mode .notificacion-titulo {
            color: #f1f5f9;
        }

        .notificacion-tiempo {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .read-status {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .read-status i {
            font-size: 14px;
            color: #94a3b8;
        }

        .read-status i.read {
            color: #3b82f6;
        }

        .notificacion-mensaje {
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        body.dark-mode .notificacion-mensaje {
            color: #94a3b8;
        }

        .notificacion-acciones {
            display: flex;
            gap: 15px;
        }

        .btn-ver {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-ver:hover {
            background: #2563eb;
        }

        .btn-marcar {
            background: transparent;
            border: 1px solid #e2e8f0;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        body.dark-mode .btn-marcar {
            border-color: #334155;
            color: #94a3b8;
        }

        .btn-marcar:hover {
            background: #e2e8f0;
        }

        body.dark-mode .btn-marcar:hover {
            background: #0f172a;
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
            color: #1e293b;
            margin-bottom: 10px;
        }

        body.dark-mode .empty-state h3 {
            color: #f1f5f9;
        }

        .empty-state p {
            color: #64748b;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .alert-success {
            background: #166534;
            border-color: #22c55e;
            color: #f0fdf4;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-card {
                flex-direction: column;
                gap: 20px;
            }
            
            .notificacion-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .notificacion-acciones {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="<?= $tema === 'dark' ? 'dark-mode' : '' ?>">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    
    <main class="main-content">
        <div class="notificaciones-container">
            <!-- Header de la página -->
            <div class="page-header">
                <h1>
                    <i class="fa-regular fa-bell" style="color: #3b82f6;"></i>
                    Notificaciones
                </h1>
                <div class="header-actions">
                    <?php if ($no_leidas > 0): ?>
                    <form method="post" style="display: inline;">
                        <button type="submit" name="marcar_todas" class="btn-marcar-todas">
                            <i class="fa-regular fa-check-double"></i>
                            Marcar todas como leídas
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mensajes de éxito -->
            <?php if (isset($_GET['marcadas']) && $_GET['marcadas'] == 'todas'): ?>
            <div class="alert-success">
                <i class="fa-regular fa-circle-check"></i>
                Todas las notificaciones han sido marcadas como leídas
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['marcada']) && $_GET['marcada'] == 'ok'): ?>
            <div class="alert-success">
                <i class="fa-regular fa-circle-check"></i>
                Notificación marcada como leída
            </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-card">
                <div class="stat-item">
                    <div class="stat-value"><?= count($notificaciones) ?></div>
                    <div class="stat-label">Total notificaciones</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $no_leidas ?></div>
                    <div class="stat-label">No leídas</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= count($notificaciones) - $no_leidas ?></div>
                    <div class="stat-label">Leídas</div>
                </div>
            </div>

            <!-- Lista de notificaciones -->
            <div class="notificaciones-list">
                <?php if (empty($notificaciones)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell-slash"></i>
                    <h3>No hay notificaciones</h3>
                    <p>Cuando recibas notificaciones, aparecerán aquí</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notificaciones as $notif): 
                        // Calcular tiempo
                        $fecha = new DateTime($notif['fecha_creacion']);
                        $ahora = new DateTime();
                        $diff = $ahora->diff($fecha);
                        
                        if ($diff->days == 0) {
                            if ($diff->h == 0) {
                                $tiempo = "hace {$diff->i} minutos";
                            } else {
                                $tiempo = "hace {$diff->h} horas";
                            }
                        } elseif ($diff->days == 1) {
                            $tiempo = "ayer a las " . $fecha->format('H:i');
                        } elseif ($diff->days <= 7) {
                            $tiempo = "hace {$diff->days} días";
                        } else {
                            $tiempo = $fecha->format('d/m/Y H:i');
                        }
                        
                        $icono = 'fa-bell';
                        $color = '#3b82f6';
                        switch ($notif['tipo']) {
                            case 'vacaciones':
                                $icono = 'fa-umbrella-beach';
                                $color = '#f97316';
                                break;
                            case 'turno':
                                $icono = 'fa-calendar';
                                $color = '#3b82f6';
                                break;
                            case 'ausencia':
                                $icono = 'fa-clock';
                                $color = '#ef4444';
                                break;
                            case 'confirmacion':
                                $icono = 'fa-circle-check';
                                $color = '#22c55e';
                                break;
                            case 'sistema':
                                $icono = 'fa-gear';
                                $color = '#8b5cf6';
                                break;
                        }
                    ?>
                    <div class="notificacion-item <?= !$notif['leida'] ? 'unread' : '' ?>" onclick="window.location.href='<?= htmlspecialchars($notif['enlace'] ?? '#') ?>'">
                        <div class="notificacion-icon" style="color: <?= $color ?>">
                            <i class="fa-regular <?= $icono ?>"></i>
                        </div>
                        
                        <div class="notificacion-content">
                            <div class="notificacion-header">
                                <div class="notificacion-titulo">
                                    <?= htmlspecialchars($notif['titulo']) ?>
                                </div>
                                <div class="notificacion-tiempo">
                                    <span><?= $tiempo ?></span>
                                    <span class="read-status">
                                        <?php if ($notif['leida']): ?>
                                            <i class="fa-regular fa-check read"></i>
                                            <i class="fa-regular fa-check read"></i>
                                        <?php else: ?>
                                            <i class="fa-regular fa-circle"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="notificacion-mensaje">
                                <?= htmlspecialchars($notif['mensaje']) ?>
                            </div>
                            
                            <div class="notificacion-acciones" onclick="event.stopPropagation();">
                                <?php if ($notif['enlace']): ?>
                                <a href="<?= htmlspecialchars($notif['enlace']) ?>" class="btn-ver">
                                    <i class="fa-regular fa-eye"></i> Ver detalles
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!$notif['leida']): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="marcar_id" value="<?= $notif['id'] ?>">
                                    <button type="submit" class="btn-marcar" onclick="event.stopPropagation();">
                                        <i class="fa-regular fa-check"></i> Marcar como leída
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="script.js"></script>
</body>
</html>