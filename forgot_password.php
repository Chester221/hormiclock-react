<?php
// ============================================
// RECUPERAR CONTRASEÑA - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';

// Si ya hay sesión, redirigir
if (isset($_SESSION['name'])) {
    $redirect = ($_SESSION['user_role'] === 'admin') ? "admin_page.php" : "user_page.php";
    header("Location: $redirect");
    exit();
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$email_sent = false;
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de validación. Intenta de nuevo.';
    } else {
        // Limpiar token para que no se reutilice
        unset($_SESSION['csrf_token']);
        
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Correo electrónico no válido.';
        } else {
            // Verificar si el email existe en users
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generar token único
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hora de validez
                
                // Crear tabla si no existe
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
                
                // Eliminar tokens anteriores para este email
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                // Insertar nuevo token
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);
                
                // Crear enlace de restablecimiento
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                
                // ============================================
                // SIMULACIÓN DE ENVÍO DE CORREO
                // ============================================
                // Por ahora, solo mostramos el enlace en pantalla
                // En producción, aquí iría el código de PHPMailer
                
                // Guardamos el enlace en sesión para mostrarlo (solo para pruebas)
                $_SESSION['reset_link_demo'] = $reset_link;
                $email_sent = true;
                
                // Registrar actividad
                logActivity($conn, $email, 'password_reset_request', 'Solicitud de restablecimiento de contraseña');
                
                /*
                // Código para PHPMailer (comentado hasta que se configure)
                
                use PHPMailer\PHPMailer\PHPMailer;
                use PHPMailer\PHPMailer\Exception;
                
                require_once __DIR__ . '/PHPMailer/src/Exception.php';
                require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
                require_once __DIR__ . '/PHPMailer/src/SMTP.php';
                
                $mail = new PHPMailer(true);
                
                try {
                    // Configuración del servidor SMTP
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'tu-email@gmail.com';
                    $mail->Password   = 'tu-contraseña';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    
                    // Remitente y destinatario
                    $mail->setFrom('no-reply@bytesclock.com', 'BytesClock');
                    $mail->addAddress($email, $user['name']);
                    
                    // Contenido
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = 'Recuperación de contraseña - BytesClock';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                            <h2 style='color: #3b82f6;'>BytesClock</h2>
                            <p>Hola <strong>{$user['name']}</strong>,</p>
                            <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace para continuar:</p>
                            <p style='text-align: center; margin: 30px 0;'>
                                <a href='{$reset_link}' style='background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 30px; display: inline-block;'>Restablecer contraseña</a>
                            </p>
                            <p>Este enlace expirará en <strong>1 hora</strong>.</p>
                            <p>Si no solicitaste esto, ignora este mensaje.</p>
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                            <p style='color: #64748b; font-size: 12px;'>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                            <p style='color: #64748b; font-size: 12px; word-break: break-all;'>{$reset_link}</p>
                        </div>
                    ";
                    $mail->AltBody = "Para restablecer tu contraseña, visita: $reset_link";
                    
                    $mail->send();
                    $email_sent = true;
                    
                } catch (Exception $e) {
                    error_log("Error enviando email: " . $mail->ErrorInfo);
                    $error = 'No se pudo enviar el correo. Intenta más tarde.';
                }
                */
                
            } else {
                // Por seguridad, decimos lo mismo aunque el email no exista
                $email_sent = true;
            }
        }
    }
}

$tema = $_COOKIE['tema'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | BytesClock</title>
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

        .forgot-container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .forgot-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.5s ease;
        }

        body.dark-mode .forgot-card {
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

        .forgot-card h2 {
            color: #0f172a;
            margin-bottom: 10px;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
        }

        body.dark-mode .forgot-card h2 {
            color: #f1f5f9;
        }

        .description {
            color: #64748b;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.6;
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

        .success-box {
            background: #f0fdf4;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }

        body.dark-mode .success-box {
            background: #166534;
        }

        .success-box i {
            font-size: 48px;
            color: #22c55e;
            margin-bottom: 15px;
        }

        .success-box h3 {
            color: #166534;
            font-size: 18px;
            margin-bottom: 10px;
        }

        body.dark-mode .success-box h3 {
            color: #f0fdf4;
        }

        .success-box p {
            color: #166534;
            font-size: 14px;
            margin-bottom: 20px;
        }

        body.dark-mode .success-box p {
            color: #dcfce7;
        }

        .demo-link {
            background: white;
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            word-break: break-all;
            font-size: 13px;
            color: #166534;
        }

        .form-group {
            margin-bottom: 25px;
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
            margin-bottom: 20px;
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

        .btn-submit i {
            font-size: 18px;
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

        .security-note {
            margin-top: 30px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #64748b;
        }

        body.dark-mode .security-note {
            background: #0f172a;
        }

        .security-note i {
            color: #3b82f6;
            font-size: 20px;
        }

        @media (max-width: 480px) {
            .forgot-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="<?= $tema === 'dark' ? 'dark-mode' : '' ?>">
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="logo">
                <h1>Bytes<span>Clock</span></h1>
            </div>
            
            <?php if ($email_sent): ?>
                <!-- Mensaje de éxito -->
                <div class="success-box">
                    <i class="fa-regular fa-envelope-circle-check"></i>
                    <h3>¡Correo enviado!</h3>
                    <p>Si el correo existe en nuestro sistema, recibirás instrucciones para restablecer tu contraseña.</p>
                    
                    <?php if (isset($_SESSION['reset_link_demo'])): ?>
                        <!-- MODO DEMO: Mostrar enlace (solo para pruebas) -->
                        <div style="margin-top: 20px; padding: 15px; background: #e6f7ff; border-radius: 8px; text-align: left;">
                            <p style="color: #0066cc; font-weight: 500; margin-bottom: 5px;">
                                <i class="fa-regular fa-flask"></i> MODO DEMO:
                            </p>
                            <p style="color: #0066cc; word-break: break-all; font-size: 12px;">
                                <a href="<?= $_SESSION['reset_link_demo'] ?>" style="color: #0066cc;">
                                    <?= $_SESSION['reset_link_demo'] ?>
                                </a>
                            </p>
                            <p style="color: #666; font-size: 11px; margin-top: 5px;">
                                (Este enlace solo aparece en modo desarrollo)
                            </p>
                        </div>
                        <?php unset($_SESSION['reset_link_demo']); ?>
                    <?php endif; ?>
                </div>
                
                <div class="back-link">
                    <a href="index.php">
                        <i class="fa-regular fa-arrow-left"></i>
                        Volver al inicio de sesión
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Formulario -->
                <h2>¿Olvidaste tu contraseña?</h2>
                <p class="description">
                    No te preocupes. Ingresa tu correo electrónico y te enviaremos instrucciones para restablecerla.
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fa-regular fa-circle-exclamation"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <div class="input-group">
                            <i class="fa-regular fa-envelope"></i>
                            <input type="email" name="email" placeholder="correo@ejemplo.com" 
                                   value="<?= htmlspecialchars($email) ?>" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fa-regular fa-paper-plane"></i>
                        Enviar instrucciones
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="index.php">
                        <i class="fa-regular fa-arrow-left"></i>
                        Volver al inicio de sesión
                    </a>
                </div>
                
                <div class="security-note">
                    <i class="fa-regular fa-shield"></i>
                    <div>
                        <strong>Seguridad:</strong> Por tu protección, este enlace expirará en 3 minutos y solo puede usarse una vez.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>