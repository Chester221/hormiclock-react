<?php
// generar_qr_prueba.php
require_once 'config.php';

// Verificar si se envió una cédula
$cedula = $_POST['cedula'] ?? $_GET['cedula'] ?? '';
$resultado = '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generar QR de Prueba</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
        }
        h1 {
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
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="text"]:focus {
            border-color: #667eea;
            outline: none;
        }
        button, .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin: 10px 0;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        button:hover {
            background: #5a67d8;
        }
        .qr-container {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .btn-secondary {
            background: #48bb78;
        }
        .btn-secondary:hover {
            background: #38a169;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 GENERAR QR DE PRUEBA</h1>
        
        <?php if (isset($_GET['generado'])): ?>
            <div class="info">
                ✅ QR generado exitosamente para cédula: <strong><?php echo htmlspecialchars($_GET['cedula']); ?></strong>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Ingresa tu número de cédula (con formato V/E):</label>
                <input type="text" name="cedula" placeholder="Ej: V12345678 o E87654321" required value="<?php echo htmlspecialchars($cedula); ?>">
            </div>
            <button type="submit">Generar QR</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['cedula'])) {
            $cedula = $_POST['cedula'];
            
            // Verificar que la cédula existe en la BD
            $sql = "SELECT name FROM users WHERE id_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $cedula);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $nombre = $user['name'];
                
                // URL con ubicación (JavaScript obtendrá la ubicación)
                $url = "https://" . $_SERVER['HTTP_HOST'] . "/procesar_qr.php?cedula=" . urlencode($cedula);
                ?>
                
                <div class="qr-container">
                    <h3>QR para: <?php echo htmlspecialchars($nombre); ?></h3>
                    <p>Cédula: <strong><?php echo htmlspecialchars($cedula); ?></strong></p>
                    
                    <!-- Usamos API gratuita de QR -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode($url); ?>" 
                         alt="QR Code" style="width: 250px; height: 250px;">
                    
                    <div style="margin: 20px 0;">
                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?php echo urlencode($url); ?>" 
                           download="qr_<?php echo $cedula; ?>.png" 
                           class="btn btn-secondary">📥 Descargar QR</a>
                    </div>
                    
                    <div class="warning">
                        <strong>⚠️ IMPORTANTE:</strong> Cuando escanees, el navegador pedirá permiso para acceder a tu ubicación.
                        ¡ACEPTALO! Así registraremos dónde marcas entrada.
                    </div>
                </div>
                
                <?php
            } else {
                echo '<div class="warning">❌ La cédula ' . htmlspecialchars($cedula) . ' no está registrada en el sistema.</div>';
            }
        }
        ?>

        <div class="info">
            <h3>📋 INSTRUCCIONES PARA LA PRUEBA:</h3>
            <ol>
                <li><strong>Paso 1:</strong> Ingresa tu cédula (ej: V12345678)</li>
                <li><strong>Paso 2:</strong> Genera el QR y DESCÁRGALO a tu celular</li>
                <li><strong>Paso 3:</strong> Abre la imagen QR en otro celular o imprime</li>
                <li><strong>Paso 4:</strong> Escanea con tu celular (usa datos móviles)</li>
                <li><strong>Paso 5:</strong> Cuando el navegador pida ubicación, ACEPTA</li>
                <li><strong>Paso 6:</strong> Verás que inicia tu jornada automáticamente</li>
            </ol>
            <p style="color: #666; font-size: 14px; margin-top: 15px;">
                🔍 Si escaneas y ya tienes jornada activa, te redirigirá a tu dashboard normal.
            </p>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="dashboard.php" class="btn">⬅️ Ir al Dashboard</a>
        </div>
    </div>
</body>
</html>