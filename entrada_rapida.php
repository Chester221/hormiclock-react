<?php
// entrada_rapida.php - CORREGIDO Y LISTO PARA SUBIR
require_once 'config.php';

$mensaje = '';
$tipo_mensaje = '';
$email = '';

// Procesar cuando envía el correo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['email'])) {
    $email = trim($_POST['email']);
    
    try {
        // BUSCAR SI EL CORREO EXISTE EN LA BASE DE DATOS
        $sql = "SELECT id, name, email, role FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // ✅ CORREO EXISTE - ACTIVAR ENTRADA
            
            // Verificar si ya tiene registro hoy
            $hoy = date('Y-m-d');
            $sql_check = "SELECT * FROM registros_tiempo WHERE user_id = ? AND fecha = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute([$user['id'], $hoy]);
            $registro_hoy = $stmt_check->fetch();
            
            if (!$registro_hoy) {
                // NO tiene registro hoy - CREAR REGISTRO DE ENTRADA
                $hora_entrada = date('Y-m-d H:i:s');
                
                $sql_insert = "INSERT INTO registros_tiempo (user_id, fecha, hora_entrada, estado, origen_marcacion) 
                              VALUES (?, ?, ?, 'trabajando', 'qr')";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->execute([$user['id'], $hoy, $hora_entrada]);
                
                // Registrar actividad
                if (function_exists('logActivity')) {
                    logActivity($conn, $user['email'], 'entrada_qr', 'Entrada registrada vía QR');
                }
                
                // INICIAR SESIÓN
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // REDIRIGIR AL DASHBOARD
                header("Location: dashboard.php?qr=exito");
                exit();
                
            } else {
                // Ya tiene registro hoy - solo iniciar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                if (function_exists('logActivity')) {
                    logActivity($conn, $user['email'], 'acceso_qr', 'Acceso vía QR - Jornada ya iniciada');
                }
                
                header("Location: dashboard.php?qr=ya_activa");
                exit();
            }
        } else {
            // ❌ CORREO NO EXISTE
            $mensaje = "⚠️ El correo no está registrado en el sistema";
            $tipo_mensaje = "error";
        }
        
    } catch (PDOException $e) {
        error_log("Error en entrada_rapida: " . $e->getMessage());
        $mensaje = "Error en el sistema. Intenta más tarde.";
        $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Entrada Rápida</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="email"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
            border-color: #667eea;
            outline: none;
        }
        button {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover { background: #5a67d8; }
        .mensaje {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .mensaje.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .mensaje.exito {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .info {
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 13px;
        }
        .ejemplo {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            text-align: left;
        }
        .debug {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 12px;
            color: #333;
            border-left: 4px solid #667eea;
        }
        .debug a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 ENTRADA RÁPIDA</h1>
        <div class="subtitle">Escanea el QR y verifica tu identidad</div>
        
        <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>📧 Tu correo electrónico</label>
                <input type="email" name="email" placeholder="nombre@empresa.com" required value="<?php echo htmlspecialchars($email); ?>" autocomplete="off">
            </div>
            
            <button type="submit">✅ VERIFICAR Y ACTIVAR ENTRADA</button>
        </form>
        
        <div class="ejemplo">
            <strong>📌 Cómo funciona:</strong><br>
            • Ingresa el correo con el que te registraste<br>
            • Si existe → Entrada activada automáticamente<br>
            • Irás al dashboard con tu sesión iniciada<br>
            • El botón de entrada ya estará activado
        </div>
        
        <!-- INFO DE DEBUG -->
        <div class="debug">
            <strong>🔧 DEPURACIÓN:</strong><br>
            • Archivo actual: entrada_rapida.php<br>
            • Método: <?php echo $_SERVER['REQUEST_METHOD']; ?><br>
            • Sesión: <?php echo session_status() == PHP_SESSION_ACTIVE ? 'ACTIVA' : 'INACTIVA'; ?><br>
            • <a href="dashboard.php">➡️ Ir al dashboard directamente</a>
        </div>
        
        <div class="info">
            <i>⚡</i> Tu sesión se inicia automáticamente al verificar
        </div>
    </div>
</body>
</html>