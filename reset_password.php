<?php
// ============================================
// RESTABLECER CONTRASEÑA - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';

// Si ya hay sesión, redirigir
if (isset($_SESSION['name'])) {
    $redirect = ($_SESSION['user_role'] === 'admin') ? "admin_page.php" : "user_page.php";
    header("Location: $redirect");
    exit();
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$email = '';

// Verificar token
if (!empty($token)) {
    try {
        // Verificar si la tabla existe
        $conn->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (token),
                INDEX (email)
            )
        ");
        
        // Buscar token válido
        $stmt = $conn->prepare("
            SELECT email, expires_at 
            FROM password_resets 
            WHERE token = ? AND used = FALSE AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'El enlace no es válido o ha expirado. Solicita uno nuevo.';
        } else {
            $email = $reset['email'];
        }
    } catch (Exception $e) {
        error_log("Error verificando token: " . $e->getMessage());
        $error = 'Error al verificar el token. Intenta de nuevo.';
    }
} else {
    $error = 'Token no proporcionado.';
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && !empty($email)) {
    // Generar token CSRF
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de validación. Intenta de nuevo.';
    } else {
        unset($_SESSION['csrf_token']);
        
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Validar contraseña
        $password_errors = [];
        
        if (strlen($password) < 8) {
            $password_errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $password_errors[] = 'Debe contener al menos una mayúscula.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $password_errors[] = 'Debe contener al menos una minúscula.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $password_errors[] = 'Debe contener al menos un número.';
        }
        
        if (!empty($password_errors)) {
            $error = implode(' ', $password_errors);
        } elseif ($password !== $confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            try {
                // Actualizar contraseña
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed, $email]);

                // Marcar token como usado
                $stmt = $conn->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
                $stmt->execute([$token]);

                // Eliminar tokens expirados
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
                $stmt->execute();

                // Registrar actividad
                logActivity($conn, $email, 'password_reset', 'Contraseña restablecida');

                $success = '¡Contraseña actualizada! Ya puedes iniciar sesión.';
                
            } catch (Exception $e) {
                error_log("Error actualizando contraseña: " . $e->getMessage());
                $error = 'Error al actualizar la contraseña. Intenta de nuevo.';
            }
        }
    }
}

// Generar token CSRF para el formulario
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
    <title>Restablecer Contraseña | BytesClock</title>
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
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .reset-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.5s ease;
        }

        body.dark-mode .reset-card {
            background: #1e293b;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
        }

        body.dark-mode .logo h1 {
            color: #f1f5f9;
        }

        .logo span {
            color: #3b82f6;
        }

        .reset-card h2 {
            color: #0f172a;
            margin-bottom: 10px;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
        }

        body.dark-mode .reset-card h2 {
            color: #f1f5f9;
        }

        .email-info {
            background: #eef2ff;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
            color: #3b82f6;
            font-weight: 500;
            font-size: 14px;
            border: 1px solid #bfdbfe;
        }

        body.dark-mode .email-info {
            background: #1e3a5f;
            border-color: #3b82f6;
            color: #93c5fd;
        }

        .email-info i {
            margin-right: 8px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
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

        .alert-warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
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

        body.dark-mode .alert-warning {
            background: #9a3412;
            border-color: #f97316;
            color: #fff7ed;
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

        .success-box {
            background: #f0fdf4;
            border-radius: 16px;
            padding: 30px 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        body.dark-mode .success-box {
            background: #166534;
        }

        .success-box i {
            font-size: 60px;
            color: #22c55e;
            margin-bottom: 15px;
        }

        .success-box h3 {
            color: #166534;
            font-size: 20px;
            margin-bottom: 10px;
        }

        body.dark-mode .success-box h3 {
            color: #f0fdf4;
        }

        .success-box p {
            color: #166534;
            margin-bottom: 20px;
        }

        body.dark-mode .success-box p {
            color: #dcfce7;
        }

        .btn-login {
            display: inline-block;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-login:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .password-requirements {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        body.dark-mode .password-requirements {
            background: #0f172a;
        }

        .password-requirements p {
            color: #64748b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
        }

        .requirements-list li {
            color: #64748b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }

        .requirements-list li i {
            width: 18px;
            font-size: 14px;
        }

        .requirements-list li.valid i {
            color: #22c55e;
        }

        .requirements-list li.invalid i {
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
            font-size: 14px;
        }

        body.dark-mode .form-group label {
            color: #94a3b8;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s;
            background: white;
            height: 50px;
            overflow: hidden;
        }

        body.dark-mode .input-group {
            background: #0f172a;
            border-color: #334155;
        }

        .input-group:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group i {
            width: 50px;
            text-align: center;
            color: #94a3b8;
            font-size: 16px;
            transition: color 0.2s;
        }

        .input-group:focus-within i {
            color: #3b82f6;
        }

        .input-group input {
            flex: 1;
            height: 100%;
            padding: 0 15px 0 0;
            border: none;
            outline: none;
            font-size: 15px;
            background: transparent;
            color: #0f172a;
        }

        body.dark-mode .input-group input {
            color: #f1f5f9;
        }

        .input-group input::placeholder {
            color: #cbd5e1;
        }

        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }

        .strength-weak {
            background: #ef4444;
        }

        .strength-medium {
            background: #f97316;
        }

        .strength-strong {
            background: #22c55e;
        }

        .strength-very-strong {
            background: #3b82f6;
        }

        .btn-submit {
            width: 100%;
            height: 50px;
            background: #3b82f6;
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin: 20px 0 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }

        .back-link a:hover {
            color: #3b82f6;
        }

        @media (max-width: 480px) {
            .reset-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="<?= $tema === 'dark' ? 'dark-mode' : '' ?>">
    <div class="reset-container">
        <div class="reset-card">
            <div class="logo">
                <h1>Bytes<span>Clock</span></h1>
            </div>
            
            <?php if ($success): ?>
                <!-- Éxito -->
                <div class="success-box">
                    <i class="fa-regular fa-circle-check"></i>
                    <h3>¡Contraseña actualizada!</h3>
                    <p>Tu contraseña ha sido cambiada exitosamente.</p>
                    <a href="index.php" class="btn-login">
                        <i class="fa-regular fa-arrow-right-to-bracket"></i>
                        Iniciar sesión
                    </a>
                </div>
                
            <?php elseif ($error): ?>
                <!-- Error -->
                <div class="alert alert-error">
                    <i class="fa-regular fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="forgot_password.php" class="btn-submit" style="display: inline-block; width: auto; padding: 12px 30px;">
                        <i class="fa-regular fa-arrow-left"></i>
                        Solicitar nuevo enlace
                    </a>
                </div>
                
                <div class="back-link">
                    <a href="index.php">
                        <i class="fa-regular fa-arrow-left"></i>
                        Volver al inicio
                    </a>
                </div>
                
            <?php elseif (empty($token)): ?>
                <!-- Sin token -->
                <div class="alert alert-warning">
                    <i class="fa-regular fa-triangle-exclamation"></i>
                    No se proporcionó un token válido.
                </div>
                
                <div class="back-link">
                    <a href="forgot_password.php">
                        <i class="fa-regular fa-arrow-left"></i>
                        Solicitar restablecimiento
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Formulario de nueva contraseña -->
                <h2>Nueva contraseña</h2>
                
                <div class="email-info">
                    <i class="fa-regular fa-envelope"></i>
                    Restableciendo para: <strong><?= htmlspecialchars($email) ?></strong>
                </div>
                
                <div class="password-requirements">
                    <p><i class="fa-regular fa-circle-info"></i> La contraseña debe cumplir:</p>
                    <ul class="requirements-list" id="requirementsList">
                        <li id="req-length" class="invalid">
                            <i class="fa-regular fa-circle"></i> Mínimo 8 caracteres
                        </li>
                        <li id="req-uppercase" class="invalid">
                            <i class="fa-regular fa-circle"></i> Al menos una mayúscula
                        </li>
                        <li id="req-lowercase" class="invalid">
                            <i class="fa-regular fa-circle"></i> Al menos una minúscula
                        </li>
                        <li id="req-number" class="invalid">
                            <i class="fa-regular fa-circle"></i> Al menos un número
                        </li>
                    </ul>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <form method="post" action="reset_password.php?token=<?= urlencode($token) ?>" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="form-group">
                        <label>Nueva contraseña</label>
                        <div class="input-group">
                            <i class="fa-regular fa-lock"></i>
                            <input type="password" name="password" id="password" 
                                   placeholder="••••••••" required autofocus>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar contraseña</label>
                        <div class="input-group">
                            <i class="fa-regular fa-lock"></i>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   placeholder="••••••••" required>
                        </div>
                        <div id="matchMessage" style="font-size: 12px; margin-top: 5px; min-height: 20px;"></div>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn" disabled>
                        <i class="fa-regular fa-key"></i>
                        Cambiar contraseña
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="index.php">
                        <i class="fa-regular fa-arrow-left"></i>
                        Cancelar
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const matchMessage = document.getElementById('matchMessage');
            
            // Elementos de requisitos
            const reqLength = document.getElementById('req-length');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqNumber = document.getElementById('req-number');
            const strengthBar = document.getElementById('strengthBar');
            
            function checkPasswordStrength(pwd) {
                let strength = 0;
                
                // Longitud
                if (pwd.length >= 8) {
                    strength++;
                    reqLength.className = 'valid';
                    reqLength.innerHTML = '<i class="fa-regular fa-circle-check"></i> Mínimo 8 caracteres';
                } else {
                    reqLength.className = 'invalid';
                    reqLength.innerHTML = '<i class="fa-regular fa-circle"></i> Mínimo 8 caracteres';
                }
                
                // Mayúscula
                if (/[A-Z]/.test(pwd)) {
                    strength++;
                    reqUppercase.className = 'valid';
                    reqUppercase.innerHTML = '<i class="fa-regular fa-circle-check"></i> Al menos una mayúscula';
                } else {
                    reqUppercase.className = 'invalid';
                    reqUppercase.innerHTML = '<i class="fa-regular fa-circle"></i> Al menos una mayúscula';
                }
                
                // Minúscula
                if (/[a-z]/.test(pwd)) {
                    strength++;
                    reqLowercase.className = 'valid';
                    reqLowercase.innerHTML = '<i class="fa-regular fa-circle-check"></i> Al menos una minúscula';
                } else {
                    reqLowercase.className = 'invalid';
                    reqLowercase.innerHTML = '<i class="fa-regular fa-circle"></i> Al menos una minúscula';
                }
                
                // Número
                if (/[0-9]/.test(pwd)) {
                    strength++;
                    reqNumber.className = 'valid';
                    reqNumber.innerHTML = '<i class="fa-regular fa-circle-check"></i> Al menos un número';
                } else {
                    reqNumber.className = 'invalid';
                    reqNumber.innerHTML = '<i class="fa-regular fa-circle"></i> Al menos un número';
                }
                
                // Barra de fortaleza
                const percentage = (strength / 4) * 100;
                strengthBar.style.width = percentage + '%';
                
                if (strength <= 1) {
                    strengthBar.className = 'strength-bar strength-weak';
                } else if (strength == 2) {
                    strengthBar.className = 'strength-bar strength-medium';
                } else if (strength == 3) {
                    strengthBar.className = 'strength-bar strength-strong';
                } else {
                    strengthBar.className = 'strength-bar strength-very-strong';
                }
                
                return strength;
            }
            
            function validateForm() {
                const pwd = password.value;
                const conf = confirm.value;
                
                const strength = checkPasswordStrength(pwd);
                
                // Verificar coincidencia
                if (conf.length > 0) {
                    if (pwd === conf) {
                        matchMessage.innerHTML = '<span style="color: #22c55e;"><i class="fa-regular fa-circle-check"></i> Las contraseñas coinciden</span>';
                    } else {
                        matchMessage.innerHTML = '<span style="color: #ef4444;"><i class="fa-regular fa-circle-exclamation"></i> Las contraseñas no coinciden</span>';
                    }
                } else {
                    matchMessage.innerHTML = '';
                }
                
                // Habilitar botón si todo está bien
                if (strength === 4 && pwd === conf && pwd.length > 0) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            password.addEventListener('input', validateForm);
            confirm.addEventListener('input', validateForm);
        });
    </script>
</body>
</html>