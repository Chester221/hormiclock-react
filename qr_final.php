<?php
// qr_final.php - VERSIÓN CORREGIDA CON UBICACIÓN
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensaje = '';
$tipo_mensaje = '';
$procesamiento = false;
$accion = '';
$email_actual = $_POST['email'] ?? $_GET['email'] ?? $_SESSION['email'] ?? '';

// ============================================
// FUNCIONES
// ============================================
function getTipoJornada() {
    $hora = (int)date('H');
    $minuto = (int)date('i');
    $hora_decimal = $hora + ($minuto / 60);
    
    if ($hora_decimal >= 8 && $hora_decimal < 16.5) {
        return [
            'tipo' => 'diurna',
            'frase' => 'LABORANDO',
            'horario' => '8:00 AM - 5:00 PM',
            'color' => '#3b82f6',        // AZUL principal
            'color_hover' => '#2563eb',  // AZUL más oscuro
            'gradiente' => 'linear-gradient(145deg, #3b82f6, #2563eb)',
            'sombra' => 'rgba(59, 130, 246, 0.2)',
            'tiene_descanso' => true
        ];
    } elseif ($hora_decimal >= 16.5 && $hora_decimal < 18) {
        return [
            'tipo' => 'extra',
            'frase' => 'HORAS EXTRAS',
            'horario' => 'Después de 5:00 PM',
            'color' => '#f39c12',
            'color_hover' => '#e67e22',
            'gradiente' => 'linear-gradient(145deg, #f39c12, #e67e22)',
            'sombra' => 'rgba(243, 156, 18, 0.2)',
            'tiene_descanso' => false
        ];
    } else {
        return [
            'tipo' => 'nocturna',
            'frase' => 'NOCTURNO',
            'horario' => 'Horario nocturno',
            'color' => '#3498db',
            'color_hover' => '#2980b9',
            'gradiente' => 'linear-gradient(145deg, #3498db, #2980b9)',
            'sombra' => 'rgba(52, 152, 219, 0.2)',
            'tiene_descanso' => false
        ];
    }
}

// Procesar email (AHORA CON UBICACIÓN)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    
    // Guardar en sesión para debug
    $_SESSION['debug_lat'] = $lat;
    $_SESSION['debug_lng'] = $lng;
    
    try {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['email'] = $email;
            
            // Guardar entrada con ubicación
            $user_id = $user['id'];
            $hoy = date('Y-m-d');
            $ahora = date('Y-m-d H:i:s');
            
            // Verificar si ya tiene entrada hoy
            $check = $conn->prepare("SELECT id FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
            $check->execute([$user_id, $hoy]);
            
            if (!$check->fetch()) {
                // Insertar entrada con ubicación
                $insert = $conn->prepare("INSERT INTO registros_tiempo (user_id, fecha, hora_entrada, estado, origen_marcacion, ubicacion_lat, ubicacion_lng) VALUES (?, ?, ?, 'trabajando', 'qr', ?, ?)");
                $insert->execute([$user_id, $hoy, $ahora, $lat, $lng]);
            }
            
            header("Location: qr_final.php?email=" . urlencode($email));
            exit();
        } else {
            $mensaje = "❌ No existe ese correo en nuestro sistema. Intenta nuevamente.";
            $tipo_mensaje = "error";
        }
    } catch (PDOException $e) {
        $mensaje = "❌ Error en el sistema";
        $tipo_mensaje = "error";
    }
}

// Verificar estado de jornada
if (!empty($_SESSION['email']) && !$mensaje) {
    $procesamiento = true;
    $user_id = $_SESSION['user_id'];
    $hoy = date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT * FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
    $stmt->execute([$user_id, $hoy]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $jornada = getTipoJornada();
    
    if (!$registro) {
        $accion = 'seleccion';
    } elseif ($registro['estado'] == 'trabajando' && empty($registro['descanso_inicio'])) {
        $accion = 'trabajando';
    } elseif ($registro['estado'] == 'descanso' && !empty($registro['descanso_inicio']) && empty($registro['descanso_fin'])) {
        $accion = 'descanso';
    } elseif ($registro['estado'] == 'trabajando' && !empty($registro['descanso_fin'])) {
        $accion = 'trabajando';
    } elseif ($registro['estado'] == 'completado') {
        $accion = 'completado';
        $mensaje = "✅ Jornada completada. ¡Hasta mañana!";
        $tipo_mensaje = "success";
    }
}

$jornada = getTipoJornada();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>HormiClock · Gestión de Jornada</title>
    <!-- Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 24px;
            padding: 30px 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: <?= $jornada['color'] ?>;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px <?= $jornada['sombra'] ?>;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            letter-spacing: -0.3px;
        }

        .datetime {
            font-size: 14px;
            color: <?= $jornada['color'] ?>;
            background: #f0f0f0;
            padding: 6px 14px;
            border-radius: 40px;
            font-weight: 500;
        }

        /* Navegación */
        .nav-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .back-button {
            padding: 8px 16px;
            background: #f0f0f0;
            border: none;
            border-radius: 8px;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .back-button:hover {
            background: #e0e0e0;
            transform: scale(1.02);
        }

        /* Título de jornada */
        .jornada-header {
            text-align: center;
            margin: 15px 0 20px;
        }

        .jornada-frase {
            font-size: 26px;
            font-weight: 700;
            color: <?= $jornada['color'] ?>;
            margin-bottom: 5px;
        }

        .jornada-horario {
            font-size: 14px;
            color: #888;
        }

        /* Tarjetas */
        .card {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 16px;
            padding: 25px 20px;
            margin-bottom: 20px;
        }

        /* Timer */
        .timer-container {
            text-align: center;
            margin: 10px 0 25px;
        }

        .timer-numbers {
            font-size: 48px;
            font-weight: 600;
            color: #333;
            font-family: "Inter", monospace;
            letter-spacing: 2px;
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }

        .action-btn {
            width: 220px;
            padding: 14px 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #333;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }

        .action-btn i {
            font-size: 18px;
            color: #666;
        }

        .action-btn:hover {
            background: #f8f8f8;
            border-color: <?= $jornada['color'] ?>;
            transform: scale(1.02);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .action-btn.descanso:hover i {
            color: #f39c12;
        }

        .action-btn.salida:hover i {
            color: #e74c3c;
        }

        .action-btn.reanudar:hover i {
            color: #2ecc71;
        }

.btn-primary {
    width: 220px;
    padding: 14px 20px;
    border: none;
    border-radius: 8px;
    background: <?= $jornada['gradiente'] ?>;
    color: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin: 10px auto;
}

        .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px <?= $jornada['sombra'] ?>;
        }

        .btn-primary i {
            color: white;
            font-size: 18px;
        }

        /* Formulario */
        .input-group {
            margin-bottom: 20px;
        }

        .input-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .input-field {
            width: 100%;
            padding: 14px 16px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            color: #333;
            transition: all 0.2s;
        }

        .input-field:focus {
            outline: none;
            border-color: <?= $jornada['color'] ?>;
            box-shadow: 0 0 0 3px <?= $jornada['sombra'] ?>;
        }

        /* Email display */
        .email-display {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: <?= $jornada['color'] ?>;
            font-size: 14px;
            text-align: center;
            border: 1px solid #eee;
            font-weight: 500;
        }

        /* Mensajes */
        .message {
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.error {
            background: #fee;
            border: 1px solid #fcc;
            color: #e74c3c;
        }

        .message.success {
            background: #e8f8f5;
            border: 1px solid #d1f2eb;
            color: #2ecc71;
        }

        /* Footer */
        .footer {
            margin-top: 25px;
            text-align: center;
            color: #aaa;
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container { padding: 20px; }
            .timer-numbers { font-size: 42px; }
            .action-btn, .btn-primary { width: 200px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header con reloj de pared clásico -->
        <div class="app-header">
            <div class="logo-area">
                <div class="logo-icon">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.5" fill="none"/>
        <line x1="12" y1="12" x2="12" y2="6" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="12" y1="12" x2="16" y2="12" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="12" cy="12" r="1.5" fill="white"/>
    </svg>
</div>
                <span class="logo-text">HormiClock</span>
            </div>
            <div class="datetime">
                <?= date('h:i A') ?><br>
                <?= date('d/m/Y') ?>
            </div>
        </div>

        <!-- Barra de navegación -->
        <?php if ($procesamiento): ?>
        <div class="nav-bar">
            <a href="qr_final.php?logout=1" class="back-button">
                <i class="fas fa-arrow-left"></i> Cambiar correo
            </a>
        </div>
        <?php endif; ?>

        <!-- Título de jornada -->
        <div class="jornada-header">
            <div class="jornada-frase"><?= $jornada['frase'] ?></div>
            <div class="jornada-horario"><?= $jornada['horario'] ?></div>
        </div>

        <?php if ($mensaje): ?>
            <!-- Mensaje -->
            <div class="message <?= $tipo_mensaje ?>">
                <i class="fas <?= $tipo_mensaje == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= $mensaje ?>
            </div>
            
            <?php if (!$procesamiento): ?>
            <div style="display: flex; justify-content: center;">
                <a href="qr_final.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
            <?php endif; ?>

        <?php elseif ($procesamiento && $accion == 'seleccion'): ?>
            <!-- Iniciar jornada -->
            <div class="card">
                <div class="email-display">
                    <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                    <?= $_SESSION['email'] ?>
                </div>
                <button class="btn-primary" onclick="window.location.href='qr_procesar_final.php?accion=entrada'">
                    <i class="fas fa-play"></i> INICIAR JORNADA
                </button>
            </div>

        <?php elseif ($procesamiento && $accion == 'trabajando'): ?>
            <!-- Jornada activa -->
            <?php
            $stmt = $conn->prepare("SELECT hora_entrada FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
            $stmt->execute([$_SESSION['user_id'], date('Y-m-d')]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="card">
                <div class="timer-container">
                    <div class="timer-numbers" id="timer">00h 00m 00s</div>
                </div>

                <div class="action-buttons">
                    <?php if ($jornada['tiene_descanso']): ?>
                    <button class="action-btn descanso" onclick="window.location.href='qr_procesar_final.php?accion=descanso'">
                        <i class="fas fa-mug-hot"></i> INICIAR DESCANSO
                    </button>
                    <?php endif; ?>
                    <button class="action-btn salida" onclick="if(confirm('¿Finalizar jornada?')) window.location.href='qr_procesar_final.php?accion=salida'">
                        <i class="fas fa-sign-out-alt"></i> CULMINAR JORNADA
                    </button>
                </div>
            </div>

            <script>
            const inicio = <?= strtotime($reg['hora_entrada']) ?>;
            function actualizarTimer() {
                const ahora = Math.floor(Date.now() / 1000);
                const diff = ahora - inicio;
                const horas = Math.floor(diff / 3600).toString().padStart(2, '0');
                const minutos = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
                const segundos = (diff % 60).toString().padStart(2, '0');
                document.getElementById('timer').textContent = `${horas}h ${minutos}m ${segundos}s`;
            }
            actualizarTimer();
            setInterval(actualizarTimer, 1000);
            </script>

        <?php elseif ($procesamiento && $accion == 'descanso'): ?>
            <!-- En descanso -->
            <?php
            $stmt = $conn->prepare("SELECT descanso_inicio FROM registros_tiempo WHERE user_id = ? AND fecha = ?");
            $stmt->execute([$_SESSION['user_id'], date('Y-m-d')]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="card">
                <div class="timer-container">
                    <div class="timer-numbers" id="timerDescanso">01h 00m 00s</div>
                </div>

                <div class="action-buttons">
                    <button class="action-btn reanudar" onclick="window.location.href='qr_procesar_final.php?accion=reanudar'">
                        <i class="fas fa-play"></i> REANUDAR JORNADA
                    </button>
                </div>
            </div>

            <script>
            const inicioDesc = <?= strtotime($reg['descanso_inicio']) ?>;
            function actualizarDescanso() {
                const ahora = Math.floor(Date.now() / 1000);
                const restante = 3600 - (ahora - inicioDesc);
                if (restante < 0) {
                    document.getElementById('timerDescanso').textContent = '00h 00m 00s';
                    return;
                }
                const horas = Math.floor(restante / 3600).toString().padStart(2, '0');
                const minutos = Math.floor((restante % 3600) / 60).toString().padStart(2, '0');
                const segundos = (restante % 60).toString().padStart(2, '0');
                document.getElementById('timerDescanso').textContent = `${horas}h ${minutos}m ${segundos}s`;
            }
            actualizarDescanso();
            setInterval(actualizarDescanso, 1000);
            </script>

        <?php else: ?>
            <!-- Formulario de correo (AHORA CON CAMPOS DE UBICACIÓN) -->
            <div class="card">
                <form method="POST" id="emailForm">
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                    
                    <div class="input-group">
                        <label class="input-label">
                            <i class="fas fa-envelope" style="margin-right: 6px;"></i>
                            Correo electrónico
                        </label>
                        <input type="email" 
                               name="email" 
                               class="input-field" 
                               placeholder="nombre@empresa.com"
                               required>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-arrow-right"></i> CONTINUAR
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            HormiClock © 2026
        </div>
    </div>

    <!-- Script para obtener ubicación -->
    <script>
    // Función para obtener ubicación
    function obtenerUbicacion() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    document.getElementById('lat').value = position.coords.latitude;
                    document.getElementById('lng').value = position.coords.longitude;
                    console.log('Ubicación obtenida:', position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    console.log('Error obteniendo ubicación:', error);
                }
            );
        }
    }

    // Ejecutar al cargar la página
    document.addEventListener('DOMContentLoaded', obtenerUbicacion);
    </script>

    <?php if (isset($_GET['logout'])): 
        session_destroy();
        header("Location: qr_final.php");
        exit();
    endif; ?>
</body>
</html>