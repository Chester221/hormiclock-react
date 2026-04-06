<?php
// ============================================
// GESTIÓN DE PUESTOS - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de validación. Intenta de nuevo.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name']);
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $base_salary = !empty($_POST['base_salary']) ? (float)$_POST['base_salary'] : null;
            $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
            $daily_hours = (int)$_POST['daily_hours'] ?: 8;
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $error = 'El nombre del puesto es obligatorio.';
            } else {
                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO positions (name, description, department_id, base_salary, hourly_rate, daily_hours) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $department_id, $base_salary, $hourly_rate, $daily_hours]);
                    $message = 'Puesto agregado correctamente.';
                    
                    // Notificar si hay usuarios en el departamento
                    if ($department_id) {
                        $stmt = $conn->prepare("
                            SELECT u.id, u.name 
                            FROM users u 
                            WHERE u.department_id = ? AND u.role = 'user'
                        ");
                        $stmt->execute([$department_id]);
                        $usuarios = $stmt->fetchAll();
                        
                        foreach ($usuarios as $usuario) {
                            Notificaciones::sistema(
                                $usuario['id'],
                                '📋 Nuevo puesto disponible',
                                "Se ha creado el puesto de {$name} en tu departamento",
                                'positions.php'
                            );
                        }
                    }
                    
                } else {
                    $id = (int)$_POST['id'];
                    
                    // Obtener datos anteriores para notificar cambios
                    $stmt = $conn->prepare("SELECT name, base_salary FROM positions WHERE id = ?");
                    $stmt->execute([$id]);
                    $old_data = $stmt->fetch();
                    
                    $stmt = $conn->prepare("
                        UPDATE positions 
                        SET name = ?, description = ?, department_id = ?, base_salary = ?, hourly_rate = ?, daily_hours = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $department_id, $base_salary, $hourly_rate, $daily_hours, $id]);
                    $message = 'Puesto actualizado correctamente.';
                    
                    // Notificar cambio de salario si aplica
                    if ($old_data && $old_data['base_salary'] != $base_salary) {
                        $stmt = $conn->prepare("
                            SELECT u.id, u.name 
                            FROM users u 
                            WHERE u.position_id = ?
                        ");
                        $stmt->execute([$id]);
                        $usuarios = $stmt->fetchAll();
                        
                        foreach ($usuarios as $usuario) {
                            Notificaciones::aumentoSalario(
                                $usuario['id'],
                                $old_data['base_salary'],
                                $base_salary
                            );
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            // Verificar si tiene usuarios asignados
            $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE position_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                $error = 'No se puede eliminar un puesto con usuarios asignados.';
            } else {
                $stmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Puesto eliminado correctamente.';
            }
        }
    }
}

// Obtener puestos con información del departamento y conteo de empleados
$positions = $conn->query("
    SELECT 
        p.*, 
        d.name as dept_name,
        d.id as dept_id,
        COUNT(u.id) as employee_count,
        AVG(u.salario_usd) as avg_salary
    FROM positions p
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN users u ON u.position_id = p.id
    GROUP BY p.id
    ORDER BY p.name
")->fetchAll();

// Obtener todos los departamentos para el selector
$departments = $conn->query("
    SELECT id, name, 
           (SELECT COUNT(*) FROM users WHERE department_id = departments.id) as employee_count
    FROM departments 
    ORDER BY name
")->fetchAll();

// Estadísticas
$total_positions = count($positions);
$positions_with_employees = 0;
$total_employees_in_positions = 0;
$total_salary = 0;

foreach ($positions as $pos) {
    if ($pos['employee_count'] > 0) {
        $positions_with_employees++;
        $total_employees_in_positions += $pos['employee_count'];
    }
    if ($pos['base_salary']) {
        $total_salary += $pos['base_salary'] * ($pos['employee_count'] ?: 1);
    }
}

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
    <title>Gestión de Puestos | BytesClock</title>
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
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59,130,246,0.3);
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

        /* Grid de puestos */
        .positions-container {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .positions-container {
            background: #1e293b;
            border-color: #334155;
        }

        .positions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .position-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .position-card {
            background: #0f172a;
            border-color: #334155;
        }

        .position-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: #3b82f6;
        }

        .position-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .position-card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0f172a;
        }

        body.dark-mode .card-header h3 {
            color: #f1f5f9;
        }

        .employee-badge {
            background: #eef2ff;
            color: #3b82f6;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        body.dark-mode .employee-badge {
            background: #1e293b;
            color: #94a3b8;
        }

        .card-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 14px;
        }

        .info-row i {
            width: 18px;
            color: #3b82f6;
            font-size: 14px;
        }

        .info-row strong {
            color: #0f172a;
            font-weight: 600;
            margin-right: 5px;
        }

        body.dark-mode .info-row strong {
            color: #f1f5f9;
        }

        .salary-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        body.dark-mode .salary-info {
            background: #0f172a;
        }

        .salary-item {
            flex: 1;
            min-width: 120px;
        }

        .salary-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }

        .salary-item .value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
        }

        body.dark-mode .salary-item .value {
            color: #f1f5f9;
        }

        .salary-item .value small {
            font-size: 11px;
            color: #64748b;
            font-weight: 400;
        }

        .description-text {
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            color: #475569;
            margin: 15px 0;
            border-left: 3px solid #3b82f6;
        }

        body.dark-mode .description-text {
            background: #0f172a;
            color: #94a3b8;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        body.dark-mode .card-actions {
            border-top-color: #334155;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-edit {
            background: #eef2ff;
            color: #3b82f6;
        }

        .btn-edit:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 30px;
            max-width: 550px;
            width: 90%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            animation: modalSlide 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        body.dark-mode .modal-content {
            background: #1e293b;
            border: 1px solid #334155;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content h2 {
            font-size: 1.4rem;
            color: #0f172a;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .modal-content h2 {
            color: #f1f5f9;
        }

        .modal-content h2 i {
            color: #3b82f6;
        }

        .form-group {
            margin-bottom: 20px;
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
            min-height: 80px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-save {
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

        .btn-save:hover {
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
        }

        body.dark-mode .btn-cancel {
            background: #0f172a;
            color: #94a3b8;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
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
            
            .positions-grid {
                grid-template-columns: 1fr;
            }
            
            .card-actions {
                flex-direction: column;
            }
            
            .row {
                grid-template-columns: 1fr;
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
                <i class="fa-regular fa-briefcase" style="color: #3b82f6;"></i>
                Gestión de Puestos
            </h1>
            <button class="btn-primary" id="btnAddPos">
                <i class="fa-regular fa-plus"></i>
                Nuevo Puesto
            </button>
        </div>

        <!-- Mensajes -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fa-regular fa-circle-check"></i>
                <?= htmlspecialchars($message) ?>
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
                    <i class="fa-regular fa-briefcase"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Puestos</h3>
                    <p><?= $total_positions ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-regular fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Con empleados</h3>
                    <p><?= $positions_with_employees ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fa-regular fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Empleados</h3>
                    <p><?= $total_employees_in_positions ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fa-regular fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Salario prom.</h3>
                    <p>$<?= number_format($total_positions > 0 ? $total_salary / $total_positions : 0, 0) ?></p>
                </div>
            </div>
        </div>

        <!-- Grid de puestos -->
        <div class="positions-container">
            <?php if (empty($positions)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-briefcase"></i>
                    <h3>No hay puestos creados</h3>
                    <p>Crea tu primer puesto para comenzar</p>
                    <button class="btn-primary" id="btnAddPosEmpty" style="margin-top: 20px;">
                        <i class="fa-regular fa-plus"></i> Crear Puesto
                    </button>
                </div>
            <?php else: ?>
                <div class="positions-grid">
                    <?php foreach ($positions as $pos): ?>
                        <div class="position-card" data-id="<?= $pos['id'] ?>">
                            <div class="card-header">
                                <h3><?= htmlspecialchars($pos['name']) ?></h3>
                                <span class="employee-badge">
                                    <i class="fa-regular fa-user"></i>
                                    <?= $pos['employee_count'] ?> empleados
                                </span>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <i class="fa-regular fa-building"></i>
                                    <span><strong>Departamento:</strong> 
                                        <?= htmlspecialchars($pos['dept_name'] ?? 'No asignado') ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($pos['description'])): ?>
                                    <div class="description-text">
                                        <?= htmlspecialchars($pos['description']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($pos['base_salary'] || $pos['hourly_rate']): ?>
                                    <div class="salary-info">
                                        <?php if ($pos['base_salary']): ?>
                                            <div class="salary-item">
                                                <div class="label">Salario base</div>
                                                <div class="value">
                                                    $<?= number_format($pos['base_salary'], 2) ?>
                                                    <small>/mes</small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($pos['hourly_rate']): ?>
                                            <div class="salary-item">
                                                <div class="label">Tarifa hora</div>
                                                <div class="value">
                                                    $<?= number_format($pos['hourly_rate'], 2) ?>
                                                    <small>/hora</small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <i class="fa-regular fa-clock"></i>
                                    <span><strong>Jornada:</strong> <?= $pos['daily_hours'] ?> horas/día</span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="fa-regular fa-hashtag"></i>
                                    <span><strong>ID:</strong> <?= $pos['id'] ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <button class="btn-action btn-edit" onclick="editPos(<?= $pos['id'] ?>, '<?= htmlspecialchars(addslashes($pos['name'])) ?>', '<?= htmlspecialchars(addslashes($pos['description'] ?? '')) ?>', <?= $pos['dept_id'] ?: 'null' ?>, <?= $pos['base_salary'] ?: 'null' ?>, <?= $pos['hourly_rate'] ?: 'null' ?>, <?= $pos['daily_hours'] ?>)">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                    Editar
                                </button>
                                <form method="post" style="flex:1;" onsubmit="return confirm('¿Eliminar este puesto? Los usuarios asignados perderán su puesto.');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $pos['id'] ?>">
                                    <button type="submit" class="btn-action btn-delete" style="width:100%;">
                                        <i class="fa-regular fa-trash-can"></i>
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal -->
    <div id="posModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">
                <i class="fa-regular fa-briefcase"></i>
                Nuevo Puesto
            </h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="posId" value="">
                
                <div class="form-group">
                    <label>Nombre del puesto *</label>
                    <input type="text" name="name" id="posName" class="form-control" required placeholder="Ej: Desarrollador Senior">
                </div>

                <div class="form-group">
                    <label>Descripción (opcional)</label>
                    <textarea name="description" id="posDescription" class="form-control" placeholder="Funciones y responsabilidades..."></textarea>
                </div>

                <div class="form-group">
                    <label>Departamento</label>
                    <select name="department_id" id="departmentId" class="form-control">
                        <option value="">-- Sin departamento --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>">
                                <?= htmlspecialchars($dept['name']) ?> (<?= $dept['employee_count'] ?> emp.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="form-group">
                        <label>Salario base (mensual)</label>
                        <input type="number" step="0.01" name="base_salary" id="baseSalary" class="form-control" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Tarifa por hora</label>
                        <input type="number" step="0.01" name="hourly_rate" id="hourlyRate" class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Horas diarias</label>
                    <input type="number" name="daily_hours" id="dailyHours" class="form-control" value="8" min="1" max="24">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('posModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const posId = document.getElementById('posId');
        const posName = document.getElementById('posName');
        const posDescription = document.getElementById('posDescription');
        const departmentId = document.getElementById('departmentId');
        const baseSalary = document.getElementById('baseSalary');
        const hourlyRate = document.getElementById('hourlyRate');
        const dailyHours = document.getElementById('dailyHours');

        // Abrir modal para nuevo puesto
        document.getElementById('btnAddPos')?.addEventListener('click', () => {
            modalTitle.innerHTML = '<i class="fa-regular fa-briefcase"></i> Nuevo Puesto';
            formAction.value = 'add';
            posId.value = '';
            posName.value = '';
            posDescription.value = '';
            departmentId.value = '';
            baseSalary.value = '';
            hourlyRate.value = '';
            dailyHours.value = '8';
            modal.classList.add('show');
        });

        document.getElementById('btnAddPosEmpty')?.addEventListener('click', () => {
            modalTitle.innerHTML = '<i class="fa-regular fa-briefcase"></i> Nuevo Puesto';
            formAction.value = 'add';
            posId.value = '';
            posName.value = '';
            posDescription.value = '';
            departmentId.value = '';
            baseSalary.value = '';
            hourlyRate.value = '';
            dailyHours.value = '8';
            modal.classList.add('show');
        });

        // Función para editar puesto
        window.editPos = (id, name, description, dept, salary, rate, hours) => {
            modalTitle.innerHTML = '<i class="fa-regular fa-pen-to-square"></i> Editar Puesto';
            formAction.value = 'edit';
            posId.value = id;
            posName.value = name;
            posDescription.value = description;
            departmentId.value = dept !== null ? dept : '';
            baseSalary.value = salary !== null ? salary : '';
            hourlyRate.value = rate !== null ? rate : '';
            dailyHours.value = hours;
            modal.classList.add('show');
        };

        // Cerrar modal
        window.closeModal = () => {
            modal.classList.remove('show');
        };

        // Cerrar al hacer clic fuera
        window.onclick = (e) => {
            if (e.target === modal) closeModal();
        };

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>

    <script src="script.js"></script>
</body>
</html>