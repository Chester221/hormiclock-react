<?php
// ============================================
// PÁGINA DE LOGIN - CON ICONOS VISIBLES
// ============================================

require_once 'config.php';

// Si ya hay sesión, redirigir
if (isset($_SESSION['name'], $_SESSION['user_role'])) {
    $redirect = ($_SESSION['user_role'] === 'admin') ? "admin_page.php" : "user_page.php";
    header("Location: $redirect");
    exit();
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Recuperar errores y mensajes
$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HormiClock | Iniciar Sesión</title>
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

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            animation: slideUp 0.5s ease;
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

        .logo span {
            color: #3b82f6;
        }

        .login-container h2 {
            color: #0f172a;
            margin-bottom: 25px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

        /* Grupos de formulario */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Input groups con iconos SVG - CORREGIDO */
        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
            background: white;
            height: 50px;
            overflow: hidden;
            position: relative;
        }

        .input-group:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .icon-wrapper {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            flex-shrink: 0;
            z-index: 2;
        }

        .icon-wrapper img {
            width: 22px;
            height: 22px;
            opacity: 0.8;
            transition: opacity 0.2s;
            display: block;
        }

        .input-group:focus-within .icon-wrapper img {
            opacity: 1;
        }

        .input-group input {
            flex: 1;
            height: 100%;
            padding: 0 15px;
            border: none;
            outline: none;
            font-size: 14px;
            background: transparent;
            color: #0f172a;
            min-width: 0;
        }

        .input-group input::placeholder {
            color: #cbd5e1;
        }

        /* GRUPO DE CONTRASEÑA - CORREGIDO */
        .password-group {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
            height: 50px;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .password-group:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .password-group .icon-wrapper {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            flex-shrink: 0;
            z-index: 2;
        }

        .password-group input {
            flex: 1;
            height: 100%;
            padding: 0 45px 0 15px;
            border: none;
            outline: none;
            font-size: 14px;
            background: transparent;
            color: #0f172a;
            min-width: 0;
        }

        .password-group input::placeholder {
            color: #cbd5e1;
        }

        .eye-button {
            position: absolute;
            right: 8px;
            width: 35px;
            height: 35px;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
        }

        .eye-button img {
            width: 20px;
            height: 20px;
            opacity: 0.5;
            transition: opacity 0.2s;
            display: block;
        }

        .eye-button:hover img {
            opacity: 1;
        }

        /* Enlace olvidé contraseña */
        .forgot-link {
            text-align: right;
            margin: 10px 0 25px;
        }

        .forgot-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link a:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        /* Botón principal */
        .btn-submit {
            width: 100%;
            height: 50px;
            background: #3b82f6;
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 25px;
        }

        .btn-submit:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }

        /* Footer */
        .register-link {
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        .register-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Alertas */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 13px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left-color: #22c55e;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Hormi<span>Clock</span></h1>
        </div>
        
        <h2>Iniciar Sesión</h2>
        
        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario de login -->
        <form action="login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label>CORREO ELECTRÓNICO</label>
                <div class="input-group">
                    <span class="icon-wrapper">
                        <img src="icons/envelope.svg" alt="email">
                    </span>
                    <input type="email" name="login" placeholder="correo@ejemplo.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>CONTRASEÑA</label>
                <div class="password-group">
                    <span class="icon-wrapper">
                        <img src="icons/lock.svg" alt="lock">
                    </span>
                    <input type="password" name="password" id="login-password" placeholder="••••••••" required>
                    <button type="button" class="eye-button" onclick="togglePassword('login-password', this)">
                        <img src="icons/eye.svg" alt="show" class="eye-icon">
                    </button>
                </div>
            </div>

            <div class="forgot-link">
                <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" name="login_submit" class="btn-submit">Iniciar Sesión</button>

            <div class="register-link">
                ¿No tienes cuenta? <a href="register.php">Crear cuenta</a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const eyeIcon = button.querySelector('img');
            
            if (!input || !eyeIcon) return;
            
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            if (type === 'text') {
                eyeIcon.src = 'icons/eye-slash.svg';
                eyeIcon.alt = 'hide';
            } else {
                eyeIcon.src = 'icons/eye.svg';
                eyeIcon.alt = 'show';
            }
        }
    </script>
</body>
</html>