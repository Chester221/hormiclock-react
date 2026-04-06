<?php
// ============================================
// PÁGINA DE REGISTRO - VERSIÓN FINAL FUNCIONAL
// ============================================

require_once 'config.php';

// Si ya hay sesión, redirigir
if (isset($_SESSION['name'], $_SESSION['user_role'])) {
    $redirect = ($_SESSION['user_role'] === 'admin') ? "admin_page.php" : "user_page.php";
    header("Location: $redirect");
    exit();
}

// Verificar administradores
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admin_count = $stmt->fetchColumn();
} catch (Exception $e) {
    $admin_count = 0;
}

$mostrar_admin = ($admin_count < 2);

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Procesar registro
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de seguridad. Intenta nuevamente.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        $doc_letter = $_POST['doc_letter'] ?? 'V';
        $doc_number = trim($_POST['doc_number'] ?? '');
        $id_number = $doc_letter . '-' . $doc_number;
        
        $day = (int)($_POST['day'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        
        if (empty($username) || empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($doc_number)) {
            $error = 'Todos los campos son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } elseif (!$day || !$month || !$year || !checkdate($month, $day, $year)) {
            $error = 'La fecha de nacimiento no es válida.';
        } elseif ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $uppercase = preg_match('@[A-Z]@', $password);
            $lowercase = preg_match('@[a-z]@', $password);
            $number    = preg_match('@[0-9]@', $password);
            $special   = preg_match('@[^\w]@', $password);
            
            if (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif (!$uppercase) {
                $error = 'La contraseña debe contener al menos una mayúscula.';
            } elseif (!$lowercase) {
                $error = 'La contraseña debe contener al menos una minúscula.';
            } elseif (!$number) {
                $error = 'La contraseña debe contener al menos un número.';
            } elseif (!$special) {
                $error = 'La contraseña debe contener al menos un carácter especial.';
            } else {
                try {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR id_number = ?");
                    $stmt->execute([$email, $username, $id_number]);
                    
                    if ($stmt->fetch()) {
                        $error = 'El correo, usuario o documento ya está registrado.';
                    } else {
                        $birth_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // CONSULTA CORREGIDA - AHORA USA hire_date
                        $stmt = $conn->prepare("
                            INSERT INTO users (
                                username, name, email, phone, id_number, birth_date, 
                                password, role, hire_date
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        if ($stmt->execute([$username, $full_name, $email, $phone, $id_number, $birth_date, $hashed_password, $role])) {
                            $success = true;
                        } else {
                            $error = 'Error al registrar. Intenta nuevamente.';
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error en registro: " . $e->getMessage());
                    $error = 'Error en el servidor. Intenta más tarde.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HormiClock | Crear Cuenta</title>
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

        .register-container {
            background: white;
            border-radius: 28px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 700px;
            padding: 30px 35px;
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
            margin-bottom: 20px;
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

        .logo span {
            color: #3b82f6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .full-width {
            grid-column: span 2;
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            color: #475569;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            transition: all 0.2s ease;
            background: white;
            height: 48px;
            overflow: hidden;
        }

        .input-group:hover {
            border-color: #94a3b8;
        }

        .input-group:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .icon-wrapper {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-right: 2px solid #e2e8f0;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .input-group:hover .icon-wrapper {
            background: #f1f5f9;
            border-right-color: #94a3b8;
        }

        .input-group:focus-within .icon-wrapper {
            background: #eef2ff;
            border-right-color: #3b82f6;
        }

        .icon-wrapper img {
            width: 18px;
            height: 18px;
            opacity: 0.5;
            transition: all 0.2s ease;
        }

        .input-group:hover .icon-wrapper img {
            opacity: 0.8;
        }

        .input-group:focus-within .icon-wrapper img {
            opacity: 1;
            filter: brightness(0) saturate(100%) invert(32%) sepia(98%) saturate(1234%) hue-rotate(196deg) brightness(97%) contrast(101%);
        }

        .input-group input,
        .input-group select {
            flex: 1;
            height: 100%;
            padding: 0 12px;
            border: none;
            outline: none;
            font-size: 14px;
            background: transparent;
            color: #0f172a;
        }

        .phone-group {
            display: flex;
            gap: 8px;
            height: 48px;
        }

        .phone-prefix {
            height: 100%;
            padding: 0 16px;
            background: #3b82f6;
            border: none;
            border-radius: 14px;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            font-size: 14px;
        }

        .phone-group input {
            flex: 1;
            padding: 0 12px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            outline: none;
            font-size: 14px;
        }

        .phone-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .doc-group {
            display: flex;
            gap: 8px;
            height: 48px;
        }

        .doc-letter-select {
            display: flex;
            width: 90px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }

        .doc-letter-btn {
            flex: 1;
            height: 100%;
            border: none;
            background: #f8fafc;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            color: #64748b;
        }

        .doc-letter-btn.active {
            background: #3b82f6;
            color: white;
        }

        .doc-number-input {
            flex: 1;
            padding: 0 12px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            outline: none;
            font-size: 14px;
        }

        .doc-number-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .date-group {
            display: flex;
            gap: 8px;
        }

        .date-select {
            flex: 1;
            height: 48px;
            padding: 0 8px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            outline: none;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .date-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .password-group {
            display: flex;
            align-items: center;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            transition: all 0.2s ease;
            height: 48px;
            background: white;
            overflow: hidden;
        }

        .password-group:hover {
            border-color: #94a3b8;
        }

        .password-group:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .password-group .icon-wrapper {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-right: 2px solid #e2e8f0;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .password-group:hover .icon-wrapper {
            background: #f1f5f9;
            border-right-color: #94a3b8;
        }

        .password-group:focus-within .icon-wrapper {
            background: #eef2ff;
            border-right-color: #3b82f6;
        }

        .password-group .icon-wrapper img {
            width: 18px;
            height: 18px;
            opacity: 0.5;
            transition: all 0.2s ease;
        }

        .password-group:hover .icon-wrapper img {
            opacity: 0.8;
        }

        .password-group:focus-within .icon-wrapper img {
            opacity: 1;
            filter: brightness(0) saturate(100%) invert(32%) sepia(98%) saturate(1234%) hue-rotate(196deg) brightness(97%) contrast(101%);
        }

        .password-group input {
            flex: 1;
            height: 100%;
            padding: 0 8px;
            border: none;
            outline: none;
            font-size: 14px;
            background: transparent;
        }

        .eye-button {
            width: 42px;
            height: 48px;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .eye-button:hover {
            background: #f1f5f9;
        }

        .eye-button img {
            width: 18px;
            height: 18px;
            opacity: 0.5;
        }

        .password-requirements {
            margin-top: 6px;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: none;
            grid-column: span 2;
        }

        .password-requirements.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .requirements-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 11px;
        }

        .requirement i {
            width: 14px;
            font-size: 11px;
            color: #94a3b8;
        }

        .requirement.met {
            color: #10b981;
        }

        .requirement.met i {
            color: #10b981;
        }

        .requirement.met span {
            text-decoration: line-through 2px #10b981;
            opacity: 0.7;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #password-match-message {
            font-size: 11px;
            margin-top: 5px;
            padding: 8px 12px;
            border-radius: 10px;
            display: none;
            grid-column: span 2;
        }

        #password-match-message.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .match-success {
            background: #d1fae5;
            color: #065f46;
        }

        .match-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-select {
            width: 100%;
            height: 48px;
            padding: 0 12px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            outline: none;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .role-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: #3b82f6;
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin: 15px 0 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-submit img {
            width: 16px;
            height: 16px;
            filter: brightness(0) invert(1);
        }

        .login-link {
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        .login-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 13px;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .success-message {
            text-align: center;
            padding: 40px 25px;
            background: #f0fdf4;
            border-radius: 18px;
            border: 2px solid #22c55e;
        }

        .success-message i {
            font-size: 70px;
            color: #22c55e;
            margin-bottom: 15px;
        }

        .success-message h2 {
            color: #166534;
            margin-bottom: 8px;
            font-size: 24px;
        }

        .success-message p {
            color: #166534;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .btn-login {
            display: inline-block;
            padding: 12px 35px;
            background: #22c55e;
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(22, 163, 74, 0.3);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>Bytes<span>Clock</span></h1>
        </div>
        
        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <h2>¡Cuenta creada!</h2>
                <p>Registro exitoso</p>
                <a href="index.php" class="btn-login">Iniciar sesión</a>
            </div>
        <?php else: ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form action="register.php" method="post" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-grid">
                    <!-- USUARIO -->
                    <div class="form-group">
                        <label>USUARIO</label>
                        <div class="input-group">
                            <span class="icon-wrapper">
                                <img src="icons/at.svg" alt="@">
                            </span>
                            <input type="text" name="username" placeholder="usuario123" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- NOMBRE -->
                    <div class="form-group">
                        <label>NOMBRE</label>
                        <div class="input-group">
                            <span class="icon-wrapper">
                                <img src="icons/user.svg" alt="user">
                            </span>
                            <input type="text" name="full_name" placeholder="Juan Pérez" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- CORREO -->
                    <div class="form-group">
                        <label>CORREO</label>
                        <div class="input-group">
                            <span class="icon-wrapper">
                                <img src="icons/envelope.svg" alt="email">
                            </span>
                            <input type="email" name="email" placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- TELÉFONO -->
                    <div class="form-group">
                        <label>TELÉFONO</label>
                        <div class="phone-group">
                            <span class="phone-prefix">+58</span>
                            <input type="tel" name="phone" placeholder="4121234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- DOCUMENTO -->
                    <div class="form-group">
                        <label>DOCUMENTO</label>
                        <div class="doc-group">
                            <div class="doc-letter-select">
                                <button type="button" class="doc-letter-btn active" data-letter="V" onclick="setDocLetter('V', this)">V</button>
                                <button type="button" class="doc-letter-btn" data-letter="E" onclick="setDocLetter('E', this)">E</button>
                            </div>
                            <input type="text" name="doc_number" class="doc-number-input" placeholder="12345678" value="<?= htmlspecialchars($_POST['doc_number'] ?? '') ?>" required>
                        </div>
                        <input type="hidden" name="doc_letter" id="doc_letter" value="V">
                    </div>

                    <!-- FECHA -->
                    <div class="form-group">
                        <label>FECHA DE NACIMIENTO</label>
                        <div class="date-group">
                            <select name="day" id="day" class="date-select" required>
                                <option value="">Día</option>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?= $d ?>" <?= (isset($_POST['day']) && $_POST['day'] == $d) ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="month" id="month" class="date-select" required>
                                <option value="">Mes</option>
                                <?php
                                $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                                foreach ($meses as $i => $mes):
                                ?>
                                    <option value="<?= $i + 1 ?>" <?= (isset($_POST['month']) && $_POST['month'] == $i + 1) ? 'selected' : '' ?>><?= $mes ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="year" id="year" class="date-select" required>
                                <option value="">Año</option>
                                <?php for ($y = 2008; $y >= 1950; $y--): ?>
                                    <option value="<?= $y ?>" <?= (isset($_POST['year']) && $_POST['year'] == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- CONTRASEÑA -->
                    <div class="form-group">
                        <label>CONTRASEÑA</label>
                        <div class="password-group">
                            <span class="icon-wrapper">
                                <img src="icons/lock.svg" alt="lock">
                            </span>
                            <input type="password" name="password" id="register-password" placeholder="••••••••" required>
                            <button type="button" class="eye-button" onclick="togglePassword('register-password', this)">
                                <img src="icons/eye.svg" alt="show">
                            </button>
                        </div>
                    </div>

                    <!-- REPETIR -->
                    <div class="form-group">
                        <label>REPETIR</label>
                        <div class="password-group">
                            <span class="icon-wrapper">
                                <img src="icons/lock.svg" alt="lock">
                            </span>
                            <input type="password" name="confirm_password" id="confirm-password" placeholder="••••••••" required>
                            <button type="button" class="eye-button" onclick="togglePassword('confirm-password', this)">
                                <img src="icons/eye.svg" alt="show">
                            </button>
                        </div>
                    </div>

                    <!-- REQUISITOS -->
                    <div class="password-requirements full-width" id="passwordRequirements">
                        <div class="requirements-grid">
                            <div class="requirement" id="req-length"><i class="fas fa-circle"></i> <span>8+ caracteres</span></div>
                            <div class="requirement" id="req-uppercase"><i class="fas fa-circle"></i> <span>1 mayúscula</span></div>
                            <div class="requirement" id="req-lowercase"><i class="fas fa-circle"></i> <span>1 minúscula</span></div>
                            <div class="requirement" id="req-number"><i class="fas fa-circle"></i> <span>1 número</span></div>
                            <div class="requirement" id="req-special"><i class="fas fa-circle"></i> <span>1 especial</span></div>
                        </div>
                    </div>

                    <!-- MENSAJE -->
                    <div id="password-match-message" class="full-width"></div>

                    <!-- ROL -->
                    <div class="form-group full-width">
                        <label>ROL</label>
                        <select name="role" class="role-select" required>
                            <option value="user">Empleado</option>
                            <?php if ($mostrar_admin): ?>
                                <option value="admin">Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" name="register" class="btn-submit">
                    <img src="icons/user-plus.svg" alt="register">
                    Crear Cuenta
                </button>

                <div class="login-link">
                    ¿Ya tienes cuenta? <a href="index.php">Iniciar sesión</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://kit.fontawesome.com/your-kit-id.js" crossorigin="anonymous"></script>
    
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('img');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.src = 'icons/eye-slash.svg';
            } else {
                input.type = 'password';
                icon.src = 'icons/eye.svg';
            }
        }

        function setDocLetter(letter, btn) {
            document.querySelectorAll('.doc-letter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('doc_letter').value = letter;
        }

        function initPasswordValidation() {
            const pwd = document.getElementById('register-password');
            const conf = document.getElementById('confirm-password');
            const msg = document.getElementById('password-match-message');
            const reqs = document.getElementById('passwordRequirements');
            
            const lengthReq = document.getElementById('req-length');
            const upperReq = document.getElementById('req-uppercase');
            const lowerReq = document.getElementById('req-lowercase');
            const numReq = document.getElementById('req-number');
            const specialReq = document.getElementById('req-special');
            
            function check() {
                const val = pwd.value;
                
                const hasLength = val.length >= 8;
                const hasUpper = /[A-Z]/.test(val);
                const hasLower = /[a-z]/.test(val);
                const hasNumber = /[0-9]/.test(val);
                const hasSpecial = /[^a-zA-Z0-9]/.test(val);
                
                updateReq(lengthReq, hasLength);
                updateReq(upperReq, hasUpper);
                updateReq(lowerReq, hasLower);
                updateReq(numReq, hasNumber);
                updateReq(specialReq, hasSpecial);
                
                const allMet = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
                
                if (val.length > 0 && !allMet) {
                    reqs.classList.add('show');
                } else {
                    reqs.classList.remove('show');
                }
                
                if (conf.value.length > 0) {
                    if (pwd.value !== conf.value) {
                        msg.textContent = '❌ Las contraseñas no coinciden';
                        msg.className = 'match-error show full-width';
                    } else {
                        msg.textContent = '✅ Las contraseñas coinciden';
                        msg.className = 'match-success show full-width';
                    }
                } else {
                    msg.classList.remove('show');
                }
            }
            
            function updateReq(el, met) {
                if (met) {
                    el.classList.add('met');
                    el.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    el.classList.remove('met');
                    el.querySelector('i').className = 'fas fa-circle';
                }
            }
            
            pwd.addEventListener('input', check);
            conf.addEventListener('input', check);
        }

        document.addEventListener('DOMContentLoaded', initPasswordValidation);
    </script>
</body>
</html>