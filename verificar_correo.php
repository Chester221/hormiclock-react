<?php
// verificar_correo.php - Página que pide el correo al escanear el QR
session_start();
require_once 'config.php';

$mensaje = '';
$tipo_mensaje = '';
$email = $_POST['email'] ?? '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($email)) {
    
    // Buscar el usuario por su correo
    $sql = "SELECT id, name, email, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verificar si ya tiene registro hoy
        $hoy = date('Y-m-d');
        $sql_check = "SELECT * FROM registros_tiempo WHERE user_id = ? AND fecha = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("is", $user['id'], $hoy);
        $stmt_check->execute();
        $check = $stmt_check->get_result();
        
        if ($check->num_rows == 0) {
            // NO tiene registro hoy - CREAR ENTRADA AUTOMÁTICA
            $hora_entrada = date('Y-m-d H:i:s');
            $estado = 'trabajando';
            $origen = 'qr_general';
            
            // Obtener ubicación si el navegador la envía
            $lat = $_POST['lat'] ?? null;
            $lng = $_POST['lng'] ?? null;
            
            $sql_insert = "INSERT INTO registros_tiempo 
                          (user_id, fecha, hora_entrada, estado, ubicacion_lat, ubicacion_lng, origen_marcacion) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("issssss", $user['id'], $hoy, $hora_entrada, $estado, $lat, $lng, $origen);
            
            if ($stmt_insert->execute()) {
                // Iniciar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                $mensaje = "✅ ¡ENTRADA REGISTRADA CON ÉXITO!";
                $tipo_mensaje = "exito";
                
                // Redirigir después de 3 segundos
                $dashboard = ($user['role'] == 'admin') ? 'admin_page.php' : 'user_page.php';
                header("refresh:3;url=$dashboard");
            } else {
                $mensaje = "❌ Error al registrar entrada";
                $tipo_mensaje = "error";
            }
        } else {
            $registro = $check->fetch_assoc();
            if ($registro['estado'] == 'trabajando') {
                $mensaje = "⏳ Ya tienes una jornada EN CURSO";
                $tipo_mensaje = "info";
                
                // Iniciar sesión igualmente
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                $dashboard = ($user['role'] == 'admin') ? 'admin_page.php' : 'user_page.php';
                header("refresh:3;url=$dashboard");
            } else {
                $mensaje = "✅ Jornada completada hoy";
                $tipo_mensaje = "info";
            }
        }
    } else {
        $mensaje = "❌ No existe usuario con ese correo electrónico";
        $tipo_mensaje = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verificar Correo - Entrada</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 90%;
        }
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            box-sizing: border-box;
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
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background: #5a67d8;
        }
        .mensaje {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .ubicacion-btn {
            background: #4CAF50;
            margin-top: 10px;
        }
        .small {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📝 VERIFICAR IDENTIDAD</h2>
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="formCorreo">
            <div class="form-group">
                <label>📧 Ingresa tu correo electrónico:</label>
                <input type="email" name="email" required placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($email); ?>">
            </div>
            
            <!-- Campos ocultos para ubicación -->
            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">
            
            <button type="submit" id="btnSubmit">🔍 VERIFICAR Y MARCAR ENTRADA</button>
        </form>
        
        <div class="small">
            <p>Al hacer clic en verificar, se registrará tu entrada automáticamente</p>
            <p id="ubicacionStatus">📍 Obteniendo ubicación...</p>
        </div>
    </div>

    <script>
    // Obtener ubicación automáticamente
    document.addEventListener('DOMContentLoaded', function() {
        const ubicacionStatus = document.getElementById('ubicacionStatus');
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    document.getElementById('lat').value = position.coords.latitude;
                    document.getElementById('lng').value = position.coords.longitude;
                    ubicacionStatus.innerHTML = '📍 Ubicación obtenida ✓';
                    ubicacionStatus.style.color = '#4CA50';
                },
                function(error) {
                    ubicacionStatus.innerHTML = '📍 Ubicación no disponible (opcional)';
                    ubicacionStatus.style.color = '#999';
                }
            );
        } else {
            ubicacionStatus.innerHTML = '📍 Tu navegador no soporta geolocalización';
        }
    });
    </script>
</body>
</html>