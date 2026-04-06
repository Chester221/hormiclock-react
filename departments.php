<?php
// ============================================
// GESTIÓN DE DEPARTAMENTOS - VERSIÓN COMPLETA
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
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $error = 'El nombre del departamento es obligatorio.';
            } else {
                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO departments (name, description, parent_id, manager_id) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $parent_id, $manager_id]);
                    $message = 'Departamento agregado correctamente.';
                    
                    // Notificar al manager si se asignó
                    if ($manager_id) {
                        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt->execute([$manager_id]);
                        $usuario = $stmt->fetch();
                        
                        Notificaciones::nuevoDepartamento(
                            $manager_id,
                            $name,
                            $usuario['name']
                        );
                    }
                    
                } else {
                    $id = (int)$_POST['id'];
                    
                    // Obtener el manager anterior para comparar
                    $stmt = $conn->prepare("SELECT manager_id FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    $old_manager = $stmt->fetchColumn();
                    
                    $stmt = $conn->prepare("
                        UPDATE departments 
                        SET name = ?, description = ?, parent_id = ?, manager_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $parent_id, $manager_id, $id]);
                    $message = 'Departamento actualizado correctamente.';
                    
                    // Notificar al nuevo manager
                    if ($manager_id && $manager_id != $old_manager) {
                        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt->execute([$manager_id]);
                        $usuario = $stmt->fetch();
                        
                        Notificaciones::nuevoDepartamento(
                            $manager_id,
                            $name,
                            $usuario['name']
                        );
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            // Verificar si tiene subdepartamentos
            $check = $conn->prepare("SELECT COUNT(*) FROM departments WHERE parent_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                $error = 'No se puede eliminar un departamento con subdepartamentos.';
            } else {
                // Verificar si tiene usuarios asignados
                $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    $error = 'No se puede eliminar un departamento con usuarios asignados.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Departamento eliminado correctamente.';
                }
            }
        }
    }
}

// Obtener departamentos con conteo de empleados y subdepartamentos
$departments = $conn->query("
    SELECT 
        d.*, 
        COUNT(DISTINCT u.id) as employee_count,
        COUNT(DISTINCT sub.id) as sub_count,
        (SELECT name FROM departments WHERE id = d.parent_id) as parent_name,
        (SELECT name FROM users WHERE id = d.manager_id) as manager_name,
        (SELECT email FROM users WHERE id = d.manager_id) as manager_email
    FROM departments d
    LEFT JOIN users u ON u.department_id = d.id
    LEFT JOIN departments sub ON sub.parent_id = d.id
    GROUP BY d.id
    ORDER BY d.name
")->fetchAll();

// Obtener lista de usuarios para el selector de responsables
$users = $conn->query("
    SELECT id, name, email, role 
    FROM users 
    ORDER BY name
")->fetchAll();

// Obtener estadísticas
$total_deptos = count($departments);
$total_con_empleados = 0;
$total_empleados_asignados = 0;

foreach ($departments as $dept) {
    if ($dept['employee_count'] > 0) {
        $total_con_empleados++;
        $total_empleados_asignados += $dept['employee_count'];
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
    <title>Gestión de Departamentos | BytesClock</title>
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

        /* Grid de departamentos */
        .dept-container {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .dept-container {
            background: #1e293b;
            border-color: #334155;
        }

        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .dept-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .dept-card {
            background: #0f172a;
            border-color: #334155;
        }

        .dept-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: #3b82f6;
        }

        .dept-card::before {
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

        .dept-card:hover::before {
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

        .sub-badge {
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: 8px;
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

        .info-row span {
            color: #64748b;
        }

        .manager-info {
            display: flex;
            flex-direction: column;
        }

        .manager-name {
            color: #0f172a;
            font-weight: 500;
        }

        body.dark-mode .manager-name {
            color: #f1f5f9;
        }

        .manager-email {
            font-size: 11px;
            color: #64748b;
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
            max-width: 500px;
            width: 90%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            animation: modalSlide 0.3s ease;
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
            
            .dept-grid {
                grid-template-columns: 1fr;
            }
            
            .card-actions {
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
                <i class="fa-regular fa-building" style="color: #3b82f6;"></i>
                Gestión de Departamentos
            </h1>
            <button class="btn-primary" id="btnAddDept">
                <i class="fa-regular fa-plus"></i>
                Nuevo Departamento
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
                    <i class="fa-regular fa-building"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Departamentos</h3>
                    <p><?= $total_deptos ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-regular fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Con empleados</h3>
                    <p><?= $total_con_empleados ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fa-regular fa-user-tie"></i>
                </div>
                <div class="stat-info">
                    <h3>Empleados</h3>
                    <p><?= $total_empleados_asignados ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fa-regular fa-sitemap"></i>
                </div>
                <div class="stat-info">
                    <h3>Subdepartamentos</h3>
                    <p><?= array_sum(array_column($departments, 'sub_count')) ?></p>
                </div>
            </div>
        </div>

        <!-- Grid de departamentos -->
        <div class="dept-container">
            <?php if (empty($departments)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-building"></i>
                    <h3>No hay departamentos</h3>
                    <p>Crea tu primer departamento para comenzar</p>
                    <button class="btn-primary" id="btnAddDeptEmpty" style="margin-top: 20px;">
                        <i class="fa-regular fa-plus"></i> Crear Departamento
                    </button>
                </div>
            <?php else: ?>
                <div class="dept-grid">
                    <?php foreach ($departments as $dept): ?>
                        <div class="dept-card" data-id="<?= $dept['id'] ?>">
                            <div class="card-header">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <h3><?= htmlspecialchars($dept['name']) ?></h3>
                                    <?php if ($dept['sub_count'] > 0): ?>
                                        <span class="sub-badge">
                                            <i class="fa-regular fa-sitemap"></i>
                                            <?= $dept['sub_count'] ?> sub
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="employee-badge">
                                    <i class="fa-regular fa-user"></i>
                                    <?= $dept['employee_count'] ?> empleados
                                </span>
                            </div>
                            
                            <div class="card-info">
                                <?php if (!empty($dept['description'])): ?>
                                    <div class="description-text">
                                        <?= htmlspecialchars($dept['description']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($dept['parent_name']): ?>
                                    <div class="info-row">
                                        <i class="fa-regular fa-sitemap"></i>
                                        <span><strong>Depende de:</strong> <?= htmlspecialchars($dept['parent_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($dept['manager_name']): ?>
                                    <div class="info-row">
                                        <i class="fa-regular fa-user-tie"></i>
                                        <div class="manager-info">
                                            <span class="manager-name"><?= htmlspecialchars($dept['manager_name']) ?></span>
                                            <span class="manager-email"><?= htmlspecialchars($dept['manager_email'] ?? '') ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="info-row">
                                        <i class="fa-regular fa-user-tie"></i>
                                        <span><em>Sin responsable asignado</em></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <i class="fa-regular fa-hashtag"></i>
                                    <span><strong>ID:</strong> <?= $dept['id'] ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <button class="btn-action btn-edit" onclick="editDept(<?= $dept['id'] ?>, '<?= htmlspecialchars(addslashes($dept['name'])) ?>', '<?= htmlspecialchars(addslashes($dept['description'] ?? '')) ?>', <?= $dept['parent_id'] ?: 'null' ?>, <?= $dept['manager_id'] ?: 'null' ?>)">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                    Editar
                                </button>
                                <form method="post" style="flex:1;" onsubmit="return confirm('¿Eliminar este departamento? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $dept['id'] ?>">
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
    <div id="deptModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">
                <i class="fa-regular fa-building"></i>
                Nuevo Departamento
            </h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="deptId" value="">
                
                <div class="form-group">
                    <label>Nombre del departamento *</label>
                    <input type="text" name="name" id="deptName" class="form-control" required placeholder="Ej: Recursos Humanos">
                </div>

                <div class="form-group">
                    <label>Descripción (opcional)</label>
                    <textarea name="description" id="deptDescription" class="form-control" placeholder="Breve descripción del departamento..."></textarea>
                </div>

                <div class="form-group">
                    <label>Departamento padre</label>
                    <select name="parent_id" id="parentId" class="form-control">
                        <option value="">-- Ninguno (departamento principal) --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Responsable</label>
                    <select name="manager_id" id="managerId" class="form-control">
                        <option value="">-- Sin responsable --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('deptModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const deptId = document.getElementById('deptId');
        const deptName = document.getElementById('deptName');
        const deptDescription = document.getElementById('deptDescription');
        const parentId = document.getElementById('parentId');
        const managerId = document.getElementById('managerId');

        // Abrir modal para nuevo departamento
        document.getElementById('btnAddDept')?.addEventListener('click', () => {
            modalTitle.innerHTML = '<i class="fa-regular fa-building"></i> Nuevo Departamento';
            formAction.value = 'add';
            deptId.value = '';
            deptName.value = '';
            deptDescription.value = '';
            parentId.value = '';
            managerId.value = '';
            modal.classList.add('show');
        });

        document.getElementById('btnAddDeptEmpty')?.addEventListener('click', () => {
            modalTitle.innerHTML = '<i class="fa-regular fa-building"></i> Nuevo Departamento';
            formAction.value = 'add';
            deptId.value = '';
            deptName.value = '';
            deptDescription.value = '';
            parentId.value = '';
            managerId.value = '';
            modal.classList.add('show');
        });

        // Función para editar departamento
        window.editDept = (id, name, description, parent, manager) => {
            modalTitle.innerHTML = '<i class="fa-regular fa-pen-to-square"></i> Editar Departamento';
            formAction.value = 'edit';
            deptId.value = id;
            deptName.value = name;
            deptDescription.value = description;
            parentId.value = parent !== null ? parent : '';
            managerId.value = manager !== null ? manager : '';
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