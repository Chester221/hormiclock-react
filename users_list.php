<?php
// ============================================
// LISTA DE USUARIOS - VERSIÓN COMPLETA (SOLO ADMIN)
// ============================================

require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin_page.php");
    exit();
}

// Procesar acciones de usuarios (activar/desactivar, etc)
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de validación. Intenta de nuevo.';
    } else {
        $user_id = (int)$_POST['user_id'];
        
        if ($_POST['accion'] === 'toggle_status') {
            try {
                $stmt = $conn->prepare("UPDATE users SET active = NOT active WHERE id = ?");
                $stmt->execute([$user_id]);
                $mensaje = 'Estado de usuario actualizado';
            } catch (Exception $e) {
                $error = 'Error al actualizar estado';
            }
        } elseif ($_POST['accion'] === 'reset_password') {
            // Generar contraseña temporal
            $temp_password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $mensaje = "Contraseña restablecida. Nueva contraseña: $temp_password";
            } catch (Exception $e) {
                $error = 'Error al restablecer contraseña';
            }
        }
    }
}

// Obtener lista de usuarios
try {
    $stmt = $conn->prepare("
        SELECT 
            u.id, 
            u.username,
            u.name, 
            u.email, 
            u.role,
            u.active,
            u.created_at,
            u.last_login,
            d.name as department_name, 
            p.name as position_name,
            (SELECT COUNT(*) FROM events WHERE user_id = u.id) as total_events,
            (SELECT COUNT(*) FROM ausencias WHERE user_id = u.id AND estado = 'pendiente') as ausencias_pendientes
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN positions p ON u.position_id = p.id
        ORDER BY u.id DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Contar estadísticas
    $total_usuarios = count($users);
    $total_activos = 0;
    $total_admins = 0;
    
    foreach ($users as $user) {
        if ($user['active']) $total_activos++;
        if ($user['role'] === 'admin') $total_admins++;
    }
    
} catch (Exception $e) {
    error_log("Error cargando usuarios: " . $e->getMessage());
    $users = [];
    $total_usuarios = 0;
    $total_activos = 0;
    $total_admins = 0;
}

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$tema = $_COOKIE['tema'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | BytesClock</title>
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

        /* Stats Cards */
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
            display: flex;
            align-items: center;
            gap: 20px;
        }

        body.dark-mode .stat-card {
            background: #1e293b;
            border-color: #334155;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.blue { background: #eef2ff; color: #3b82f6; }
        .stat-icon.green { background: #f0fdf4; color: #22c55e; }
        .stat-icon.orange { background: #fff7ed; color: #f97316; }
        .stat-icon.purple { background: #faf5ff; color: #8b5cf6; }

        .stat-info h3 {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #0f172a;
            font-size: 24px;
            font-weight: 700;
        }

        body.dark-mode .stat-info p {
            color: #f1f5f9;
        }

        /* Mensajes */
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

        /* Tabla */
        .users-container {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .users-container {
            background: #1e293b;
            border-color: #334155;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 600;
        }

        body.dark-mode .table-header h2 {
            color: #f1f5f9;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            background: white;
        }

        body.dark-mode .search-box {
            background: #0f172a;
            border-color: #334155;
        }

        .search-box i {
            color: #64748b;
        }

        .search-box input {
            border: none;
            outline: none;
            background: transparent;
            color: #0f172a;
            font-size: 14px;
        }

        body.dark-mode .search-box input {
            color: #f1f5f9;
        }

        .search-box input::placeholder {
            color: #94a3b8;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            text-align: left;
            padding: 15px 20px;
            color: #64748b;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        body.dark-mode .users-table th {
            border-bottom-color: #334155;
            color: #94a3b8;
        }

        .users-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #0f172a;
            font-size: 14px;
        }

        body.dark-mode .users-table td {
            border-bottom-color: #334155;
            color: #f1f5f9;
        }

        .users-table tr:hover td {
            background: #f8fafc;
        }

        body.dark-mode .users-table tr:hover td {
            background: #0f172a;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #eef2ff;
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #0f172a;
        }

        body.dark-mode .user-name {
            color: #f1f5f9;
        }

        .user-username {
            font-size: 12px;
            color: #64748b;
        }

        .badge-role {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-admin {
            background: #fff7ed;
            color: #f97316;
        }

        .badge-user {
            background: #eef2ff;
            color: #3b82f6;
        }

        .badge-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-active {
            background: #22c55e;
            box-shadow: 0 0 0 2px #f0fdf4;
        }

        .status-inactive {
            background: #ef4444;
            box-shadow: 0 0 0 2px #fee2e2;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
        }

        body.dark-mode .btn-icon {
            background: #0f172a;
            color: #94a3b8;
        }

        .btn-icon:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }

        .btn-icon.delete:hover {
            background: #ef4444;
        }

        .btn-text {
            padding: 8px 16px;
            border-radius: 30px;
            background: #f1f5f9;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        body.dark-mode .btn-text {
            background: #0f172a;
            color: #94a3b8;
        }

        .btn-text:hover {
            background: #3b82f6;
            color: white;
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
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .users-table td {
                white-space: nowrap;
            }
            
            .action-buttons {
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
                <i class="fa-regular fa-users" style="color: #3b82f6;"></i>
                Gestión de Usuarios
            </h1>
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

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fa-regular fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Usuarios</h3>
                    <p><?= $total_usuarios ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-regular fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Activos</h3>
                    <p><?= $total_activos ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fa-regular fa-crown"></i>
                </div>
                <div class="stat-info">
                    <h3>Administradores</h3>
                    <p><?= $total_admins ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fa-regular fa-building"></i>
                </div>
                <div class="stat-info">
                    <h3>Departamentos</h3>
                    <p><?= $conn->query("SELECT COUNT(*) FROM departments")->fetchColumn() ?: 0 ?></p>
                </div>
            </div>
        </div>

        <!-- Lista de usuarios -->
        <div class="users-container">
            <div class="table-header">
                <h2>Usuarios Registrados</h2>
                <div class="search-box">
                    <i class="fa-regular fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Buscar usuario..." onkeyup="buscarUsuarios()">
                </div>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-users-slash"></i>
                    <h3>No hay usuarios registrados</h3>
                    <p>Los usuarios aparecerán aquí cuando se registren</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="users-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Departamento</th>
                                <th>Puesto</th>
                                <th>Eventos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['username'] ?? $user['name'], 0, 2)) ?>
                                        </div>
                                        <div class="user-info">
                                            <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                                            <span class="user-username">@<?= htmlspecialchars($user['username'] ?? 'usuario') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge-role <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                        <?= $user['role'] === 'admin' ? 'Administrador' : 'Empleado' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $user['active'] ? 'status-active' : 'status-inactive' ?>"></span>
                                    <?= $user['active'] ? 'Activo' : 'Inactivo' ?>
                                </td>
                                <td><?= htmlspecialchars($user['department_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($user['position_name'] ?? '-') ?></td>
                                <td><?= $user['total_events'] ?> eventos</td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="activity.php?id=<?= $user['id'] ?>" class="btn-icon" title="Ver actividad">
                                            <i class="fa-regular fa-clock-rotate-left"></i>
                                        </a>
                                        <a href="calendar.php?id=<?= $user['id'] ?>" class="btn-icon" title="Ver calendario">
                                            <i class="fa-regular fa-calendar"></i>
                                        </a>
                                        <a href="profile.php?id=<?= $user['id'] ?>" class="btn-icon" title="Ver perfil">
                                            <i class="fa-regular fa-user"></i>
                                        </a>
                                        
                                        <?php if ($user['ausencias_pendientes'] > 0): ?>
                                        <a href="ausencias.php?user_id=<?= $user['id'] ?>" class="btn-text" style="background: #fef3c7; color: #f97316;">
                                            <i class="fa-regular fa-clock"></i> <?= $user['ausencias_pendientes'] ?>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- Formulario para cambiar estado -->
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="accion" value="toggle_status">
                                            <button type="submit" class="btn-icon <?= !$user['active'] ? 'delete' : '' ?>" 
                                                    title="<?= $user['active'] ? 'Desactivar' : 'Activar' ?>">
                                                <i class="fa-regular <?= $user['active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="post" style="display: inline;" onsubmit="return confirm('¿Restablecer contraseña? Se generará una nueva contraseña temporal.')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="accion" value="reset_password">
                                            <button type="submit" class="btn-icon" title="Restablecer contraseña">
                                                <i class="fa-regular fa-key"></i>
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
    </main>

    <script>
        function buscarUsuarios() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Mostrar contraseña temporal si viene en mensaje
        <?php if ($mensaje && strpos($mensaje, 'Nueva contraseña:') !== false): ?>
        setTimeout(() => {
            alert('<?= addslashes($mensaje) ?>');
        }, 500);
        <?php endif; ?>
    </script>

    <script src="script.js"></script>
</body>
</html>