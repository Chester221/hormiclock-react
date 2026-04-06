<?php
// ============================================
// PERFIL DE USUARIO - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['email'];
$userId = $_SESSION['user_id'];

// ============================================
// DESTACAR DEPARTAMENTO SI VIENE DE NOTIFICACIÓN
// ============================================
$destacar_departamento = isset($_GET['destacar']) && $_GET['destacar'] == 'departamento';

// ============================================
// OBTENER TASA BCV (API en tiempo real)
// ============================================
function obtenerTasaBCV() {
    $url = "https://api.exchangerate.host/convert?from=USD&to=VES";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['result'])) {
            return round($data['result'], 2);
        }
    }
    
    return 414.04; // Tasa de respaldo
}

$tasa_usd = obtenerTasaBCV();

// Procesar actualizaciones de perfil
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        switch ($accion) {
            case 'editar_email':
                $nuevo_email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                if ($nuevo_email) {
                    // Verificar si el email ya existe
                    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $check->execute([$nuevo_email, $userId]);
                    if (!$check->fetch()) {
                        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                        $stmt->execute([$nuevo_email, $userId]);
                        $_SESSION['email'] = $nuevo_email;
                        $mensaje = "Email actualizado correctamente";
                    } else {
                        $error = "El email ya está en uso";
                    }
                } else {
                    $error = "Email inválido";
                }
                break;
                
            case 'editar_telefono':
                $nuevo_telefono = trim($_POST['telefono']);
                $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$nuevo_telefono, $userId]);
                $mensaje = "Teléfono actualizado correctamente";
                break;
                
            case 'editar_direccion':
                $nueva_direccion = trim($_POST['direccion']);
                $stmt = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
                $stmt->execute([$nueva_direccion, $userId]);
                $mensaje = "Dirección actualizada correctamente";
                break;
                
            case 'editar_cumpleanos':
                $dia = (int)$_POST['dia'];
                $mes = (int)$_POST['mes'];
                $ano = (int)$_POST['ano'];
                
                if (checkdate($mes, $dia, $ano)) {
                    $fecha = sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
                    $stmt = $conn->prepare("UPDATE users SET birth_date = ? WHERE id = ?");
                    $stmt->execute([$fecha, $userId]);
                    $mensaje = "Fecha de nacimiento actualizada correctamente";
                } else {
                    $error = "Fecha inválida";
                }
                break;
        }
        
        header("Location: profile.php?mensaje=" . urlencode($mensaje) . "&error=" . urlencode($error));
        exit();
        
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
        header("Location: profile.php?error=" . urlencode($error));
        exit();
    }
}

// Obtener información del usuario
$userInfo = null;
try {
    $stmt = $conn->prepare("
        SELECT u.*, 
               d.name as department_name, 
               p.name as position_name,
               p.base_salary as salario_usd,
               p.hourly_rate,
               p.daily_hours
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN positions p ON u.position_id = p.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener info de usuario: " . $e->getMessage());
}

// Si no se pudo obtener, usar datos de sesión
if (!$userInfo) {
    $userInfo = [
        'username' => $_SESSION['username'] ?? 'usuario',
        'name' => $_SESSION['name'] ?? 'Usuario',
        'email' => $_SESSION['email'] ?? '',
        'id_number' => $_SESSION['id_number'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
        'address' => $_SESSION['address'] ?? '',
        'birth_date' => $_SESSION['birth_date'] ?? '',
        'hire_date' => null,
        'department_name' => null,
        'position_name' => null,
        'salario_usd' => 0,
        'hourly_rate' => null,
        'daily_hours' => 8
    ];
}

// Calcular salario en bolívares
$salario_usd = floatval($userInfo['salario_usd'] ?? 0);
$salario_ves = round($salario_usd * $tasa_usd, 2);

// ============================================
// ESTADÍSTICAS DE ACTIVIDAD
// ============================================
$total_events = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total_events = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

$upcoming_events = [];
try {
    $stmt = $conn->prepare("
        SELECT title, start_date as start 
        FROM events 
        WHERE user_id = ? AND start_date > NOW() 
        ORDER BY start_date 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $upcoming_events = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// DATOS PARA EL GRÁFICO
// ============================================
$chart_labels = [];
$chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($date));
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM user_activity 
            WHERE user_email = ? AND DATE(created_at) = ?
        ");
        $stmt->execute([$userInfo['email'], $date]);
        $chart_data[] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $chart_data[] = 0;
    }
}

$tema = $_COOKIE['tema'] ?? 'light';

// Obtener mensajes de la URL
$mensaje = $_GET['mensaje'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | BytesClock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            transition: all 0.3s ease;
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
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
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

        .profile-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Header del perfil */
        .profile-header {
            background: white;
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .profile-header {
            background: #1e293b;
            border-color: #334155;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3b82f6;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }

        .profile-avatar-large.no-image {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 600;
        }

        .profile-title {
            flex: 1;
        }

        .profile-title h1 {
            font-size: 2rem;
            color: #0f172a;
            margin-bottom: 10px;
        }

        body.dark-mode .profile-title h1 {
            color: #f1f5f9;
        }

        .profile-username {
            display: inline-block;
            background: #eef2ff;
            padding: 8px 20px;
            border-radius: 40px;
            color: #3b82f6;
            font-weight: 500;
            font-size: 1rem;
        }

        body.dark-mode .profile-username {
            background: #0f172a;
        }

        .btn-edit-photo {
            background: #eef2ff;
            color: #3b82f6;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        body.dark-mode .btn-edit-photo {
            background: #0f172a;
        }

        .btn-edit-photo:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }

        input[type="file"] {
            display: none;
        }

        /* Grid de información */
        .info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .personal-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .personal-card {
            background: #1e293b;
            border-color: #334155;
        }

        .personal-card h2 {
            font-size: 1.2rem;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        body.dark-mode .personal-card h2 {
            color: #f1f5f9;
            border-bottom-color: #334155;
        }

        .personal-card h2 i {
            color: #3b82f6;
        }

        .personal-fields {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .field-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 16px;
            transition: all 0.2s;
            position: relative;
        }

        body.dark-mode .field-item {
            background: #0f172a;
        }

        .field-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.1);
        }

        .field-item:hover .edit-icon {
            opacity: 1;
        }

        .field-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .field-label i {
            color: #3b82f6;
        }

        .field-value {
            font-size: 1rem;
            font-weight: 500;
            color: #0f172a;
            word-break: break-word;
            padding-right: 30px;
        }

        body.dark-mode .field-value {
            color: #f1f5f9;
        }

        .edit-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            color: #3b82f6;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 1rem;
            background: white;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        body.dark-mode .edit-icon {
            background: #1e293b;
        }

        .edit-icon:hover {
            background: #3b82f6;
            color: white;
        }

        .work-card {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 24px;
            padding: 25px;
            color: white;
            transition: all 0.3s ease;
        }

        .work-card.highlight {
            box-shadow: 0 0 0 4px #fbbf24, 0 20px 40px rgba(59, 130, 246, 0.4);
            transform: scale(1.02);
        }

        .work-section {
            margin-bottom: 25px;
        }

        .work-section h3 {
            font-size: 0.9rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .work-section .value {
            font-size: 1.3rem;
            font-weight: 600;
            margin-left: 26px;
        }

        .salary-display {
            cursor: pointer;
            padding: 10px;
            border-radius: 12px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .salary-display:hover {
            background: rgba(255,255,255,0.1);
        }

        .salary-main {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            transition: transform 0.3s ease;
        }

        .salary-detail {
            font-size: 0.9rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.3s ease;
        }

        .salary-display.converting .salary-main,
        .salary-display.converting .salary-detail {
            animation: slideOutIn 0.3s ease;
        }

        @keyframes slideOutIn {
            0% { transform: translateX(0); opacity: 1; }
            50% { transform: translateX(-20px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }

        .activity-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .stats-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .stats-card {
            background: #1e293b;
            border-color: #334155;
        }

        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stats-header h2 {
            font-size: 1.1rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .stats-header h2 {
            color: #f1f5f9;
        }

        .stats-header h2 i {
            color: #3b82f6;
        }

        .stats-numbers {
            display: flex;
            gap: 20px;
        }

        .stat-number-item {
            text-align: center;
        }

        .stat-number-item .big {
            font-size: 2rem;
            font-weight: 700;
            color: #3b82f6;
        }

        .stat-number-item .label {
            font-size: 0.85rem;
            color: #64748b;
        }

        .chart-container {
            height: 150px;
            margin-top: 20px;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .event-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 16px;
            transition: all 0.2s;
        }

        body.dark-mode .event-item {
            background: #0f172a;
        }

        .event-item:hover {
            transform: translateX(5px);
            background: #eef2ff;
        }

        .event-date {
            background: #3b82f6;
            color: white;
            padding: 8px 12px;
            border-radius: 12px;
            text-align: center;
            min-width: 60px;
        }

        .event-day {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1;
        }

        .event-month {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 3px;
        }

        body.dark-mode .event-title {
            color: #f1f5f9;
        }

        .event-time {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .view-all {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

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
            max-width: 450px;
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

        .modal-content h3 {
            font-size: 1.3rem;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .modal-content h3 {
            color: #f1f5f9;
        }

        .modal-content h3 i {
            color: #3b82f6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group select {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .date-selector {
            display: flex;
            gap: 10px;
        }

        .date-selector select {
            flex: 1;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-save {
            flex: 1;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
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
            border-radius: 12px;
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

        @media (max-width: 1024px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .activity-grid {
                grid-template-columns: 1fr;
            }
            
            .personal-fields {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .btn-edit-photo {
                margin-left: 0;
            }
            
            .stats-header {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body class="<?= $tema === 'dark' ? 'dark-mode' : '' ?>">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    
    <main class="main-content">
        <div class="profile-wrapper">
            
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
            
            <!-- HEADER DEL PERFIL -->
            <div class="profile-header">
                <?php 
                $profile_img = $_SESSION['profile_img'] ?? 'uploads/default.svg';
                $has_image = $profile_img && $profile_img !== 'uploads/default.svg' && file_exists($profile_img);
                ?>
                
                <?php if ($has_image): ?>
                    <img src="<?= htmlspecialchars($profile_img) ?>" 
                         alt="Foto de perfil" class="profile-avatar-large" id="preview-img">
                <?php else: ?>
                    <div class="profile-avatar-large no-image" id="preview-img">
                        <?php 
                        $iniciales = '';
                        $nombre = $userInfo['name'] ?? 'Usuario';
                        $partes = explode(' ', trim($nombre));
                        foreach ($partes as $parte) {
                            if (!empty($parte)) $iniciales .= strtoupper(substr($parte, 0, 1));
                        }
                        echo substr($iniciales, 0, 2);
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-title">
                    <h1><?= htmlspecialchars($userInfo['name'] ?? 'Usuario') ?></h1>
                    <span class="profile-username">
                        <i class="fa-regular fa-at"></i>
                        <?= htmlspecialchars($userInfo['username'] ?? 'usuario') ?>
                    </span>
                </div>
                
                <form action="upload_profile.php" method="POST" enctype="multipart/form-data">
                    <input type="file" name="profile_pic" id="file-upload" accept="image/*" onchange="previewFile()" style="display: none;">
                    <button type="button" class="btn-edit-photo" onclick="document.getElementById('file-upload').click();">
                        <i class="fa-regular fa-image"></i> Cambiar foto
                    </button>
                    <button type="submit" name="submit_image" style="display: none;" id="submit-photo">Subir</button>
                </form>
            </div>

            <!-- GRID DE INFORMACIÓN -->
            <div class="info-grid">
                <!-- INFORMACIÓN PERSONAL -->
                <div class="personal-card">
                    <h2>
                        <i class="fa-regular fa-id-card"></i>
                        Información Personal
                    </h2>
                    
                    <div class="personal-fields">
                        <!-- Email -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-regular fa-envelope"></i> Email
                            </div>
                            <div class="field-value"><?= htmlspecialchars($userInfo['email'] ?? '') ?></div>
                            <div class="edit-icon" onclick="abrirModal('email')">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </div>
                        </div>
                        
                        <!-- Cédula (no editable) -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-regular fa-id-card"></i> Cédula
                            </div>
                            <div class="field-value"><?= htmlspecialchars($userInfo['id_number'] ?? 'No registrada') ?></div>
                        </div>
                        
                        <!-- Nacimiento (no editable) -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-regular fa-calendar"></i> Nacimiento
                            </div>
                            <div class="field-value">
                                <?= isset($userInfo['birth_date']) ? date('d/m/Y', strtotime($userInfo['birth_date'])) : 'No registrada' ?>
                            </div>
                        </div>
                        
                        <!-- Cumpleaños (editable) -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-regular fa-cake-candles"></i> Cumpleaños
                            </div>
                            <div class="field-value">
                                <?= isset($userInfo['birth_date']) ? date('d/m', strtotime($userInfo['birth_date'])) : 'No registrada' ?>
                            </div>
                            <div class="edit-icon" onclick="abrirModal('cumpleanos')">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </div>
                        </div>
                        
                        <!-- Teléfono (editable) -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-solid fa-phone"></i> Teléfono
                            </div>
                            <div class="field-value"><?= htmlspecialchars($userInfo['phone'] ?? 'No registrado') ?></div>
                            <div class="edit-icon" onclick="abrirModal('telefono')">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </div>
                        </div>
                        
                        <!-- Dirección (editable) -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-solid fa-location-dot"></i> Dirección
                            </div>
                            <div class="field-value"><?= htmlspecialchars($userInfo['address'] ?? 'No registrada') ?></div>
                            <div class="edit-icon" onclick="abrirModal('direccion')">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </div>
                        </div>
                        
                        <!-- Ingreso (no editable) -->
                        <div class="field-item">
                            <div class="field-label">
                                <i class="fa-regular fa-building"></i> Ingreso
                            </div>
                            <div class="field-value">
                                <?= isset($userInfo['hire_date']) ? date('d/m/Y', strtotime($userInfo['hire_date'])) : 'No especificada' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- INFORMACIÓN LABORAL -->
                <div class="work-card <?= $destacar_departamento ? 'highlight' : '' ?>" id="departamento-section">
                    <div class="work-section">
                        <h3><i class="fa-regular fa-building"></i> Departamento</h3>
                        <div class="value"><?= htmlspecialchars($userInfo['department_name'] ?? 'No asignado') ?></div>
                    </div>
                    
                    <div class="work-section">
                        <h3><i class="fa-regular fa-briefcase"></i> Puesto</h3>
                        <div class="value"><?= htmlspecialchars($userInfo['position_name'] ?? 'No asignado') ?></div>
                    </div>
                    
                    <div class="work-section">
                        <h3><i class="fa-regular fa-dollar-sign"></i> Salario</h3>
                        <div class="salary-display" onclick="convertirMoneda(this)" id="salary-container">
                            <div class="salary-main" id="salary-amount">$<?= number_format($salario_usd, 2) ?></div>
                            <div class="salary-detail" id="salary-ves">
                                <?= number_format($salario_ves, 2) ?> Bs
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIVIDAD Y EVENTOS -->
            <div class="activity-grid">
                <!-- Estadísticas y gráfico -->
                <div class="stats-card">
                    <div class="stats-header">
                        <h2>
                            <i class="fa-regular fa-chart-line"></i>
                            Actividad
                        </h2>
                        <div class="stats-numbers">
                            <div class="stat-number-item">
                                <div class="big"><?= $total_events ?></div>
                                <div class="label">Eventos</div>
                            </div>
                            <div class="stat-number-item">
                                <div class="big"><?= count($upcoming_events) ?></div>
                                <div class="label">Próximos</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <!-- Próximos eventos -->
                <div class="stats-card">
                    <div class="stats-header">
                        <h2>
                            <i class="fa-regular fa-calendar"></i>
                            Próximos Eventos
                        </h2>
                    </div>
                    
                    <?php if (!empty($upcoming_events)): ?>
                        <div class="events-list">
                            <?php foreach ($upcoming_events as $event): 
                                $fecha = strtotime($event['start']);
                                $dia = date('d', $fecha);
                                $mes = date('M', $fecha);
                                $hora = date('H:i', $fecha);
                            ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <div class="event-day"><?= $dia ?></div>
                                    <div class="event-month"><?= $mes ?></div>
                                </div>
                                <div class="event-info">
                                    <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                                    <div class="event-time">
                                        <i class="fa-regular fa-clock"></i>
                                        <?= $hora ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="calendar.php" class="view-all">
                            Ver todos los eventos <i class="fa-regular fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: #64748b;">
                            <i class="fa-regular fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>No hay eventos próximos</p>
                            <a href="calendar.php" class="view-all" style="margin-top: 10px;">
                                Crear evento <i class="fa-regular fa-plus"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- MODALES -->
    <div id="modalEmail" class="modal">
        <div class="modal-content">
            <h3><i class="fa-regular fa-envelope"></i> Editar Email</h3>
            <form method="post">
                <input type="hidden" name="accion" value="editar_email">
                <div class="form-group">
                    <label>Nuevo email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('email')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalTelefono" class="modal">
        <div class="modal-content">
            <h3><i class="fa-solid fa-phone"></i> Editar Teléfono</h3>
            <form method="post">
                <input type="hidden" name="accion" value="editar_telefono">
                <div class="form-group">
                    <label>Nuevo teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($userInfo['phone'] ?? '') ?>" placeholder="0412-1234567" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('telefono')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalDireccion" class="modal">
        <div class="modal-content">
            <h3><i class="fa-solid fa-location-dot"></i> Editar Dirección</h3>
            <form method="post">
                <input type="hidden" name="accion" value="editar_direccion">
                <div class="form-group">
                    <label>Nueva dirección</label>
                    <input type="text" name="direccion" value="<?= htmlspecialchars($userInfo['address'] ?? '') ?>" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('direccion')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalCumpleanos" class="modal">
        <div class="modal-content">
            <h3><i class="fa-regular fa-cake-candles"></i> Editar Cumpleaños</h3>
            <form method="post">
                <input type="hidden" name="accion" value="editar_cumpleanos">
                <div class="form-group">
                    <label>Fecha de nacimiento</label>
                    <div class="date-selector">
                        <select name="dia" required>
                            <option value="">Día</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>" <?= (date('d', strtotime($userInfo['birth_date'] ?? '')) == $d) ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="mes" required>
                            <option value="">Mes</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (date('m', strtotime($userInfo['birth_date'] ?? '')) == $m) ? 'selected' : '' ?>><?= date('M', mktime(0,0,0,$m,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="ano" required>
                            <option value="">Año</option>
                            <?php for ($a = date('Y') - 18; $a >= 1900; $a--): ?>
                            <option value="<?= $a ?>" <?= (date('Y', strtotime($userInfo['birth_date'] ?? '')) == $a) ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('cumpleanos')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(tipo) {
            document.getElementById('modal' + tipo.charAt(0).toUpperCase() + tipo.slice(1)).classList.add('show');
        }

        function cerrarModal(tipo) {
            document.getElementById('modal' + tipo.charAt(0).toUpperCase() + tipo.slice(1)).classList.remove('show');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        function previewFile() {
            const preview = document.getElementById('preview-img');
            const file = document.querySelector('input[type=file]').files[0];
            const reader = new FileReader();
            
            reader.onloadend = function() {
                if (preview.tagName === 'IMG') {
                    preview.src = reader.result;
                } else {
                    const newImg = document.createElement('img');
                    newImg.src = reader.result;
                    newImg.className = 'profile-avatar-large';
                    newImg.id = 'preview-img';
                    preview.parentNode.replaceChild(newImg, preview);
                }
                document.getElementById('submit-photo').click();
            }
            
            if (file) {
                reader.readAsDataURL(file);
            }
        }

        let showingUSD = true;
        const salarioUSD = <?= $salario_usd ?>;
        const tasaUSD = <?= $tasa_usd ?>;
        
        function convertirMoneda(element) {
            const salaryMain = element.querySelector('.salary-main');
            const salaryDetail = element.querySelector('.salary-detail');
            
            element.classList.add('converting');
            
            setTimeout(() => {
                if (showingUSD) {
                    salaryMain.textContent = 'Bs. ' + new Intl.NumberFormat('es-VE', { 
                        minimumFractionDigits: 2, 
                        maximumFractionDigits: 2 
                    }).format(salarioUSD * tasaUSD);
                    salaryDetail.textContent = '$' + salarioUSD.toFixed(2) + ' USD';
                } else {
                    salaryMain.textContent = '$' + salarioUSD.toFixed(2);
                    salaryDetail.textContent = new Intl.NumberFormat('es-VE', { 
                        minimumFractionDigits: 2, 
                        maximumFractionDigits: 2 
                    }).format(salarioUSD * tasaUSD) + ' Bs';
                }
                
                showingUSD = !showingUSD;
                
                setTimeout(() => {
                    element.classList.remove('converting');
                }, 300);
            }, 150);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Actividad',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { display: false },
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        });

        <?php if ($destacar_departamento): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const deptoSection = document.getElementById('departamento-section');
            if (deptoSection) {
                deptoSection.classList.add('highlight');
                deptoSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => {
                    deptoSection.classList.remove('highlight');
                }, 3000);
            }
        });
        <?php endif; ?>
    </script>

    <script src="script.js"></script>
</body>
</html>