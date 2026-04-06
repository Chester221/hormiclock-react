<?php
// ver_dispositivos.php - Listado de todos los dispositivos vinculados
require_once 'config.php';

// Verificar si la tabla existe
try {
    $conn->query("SELECT 1 FROM dispositivos LIMIT 1");
} catch (PDOException $e) {
    die("❌ La tabla 'dispositivos' no existe. Escanea el QR primero para crearla.");
}

// Obtener todos los dispositivos con información del usuario
$stmt = $conn->query("
    SELECT 
        d.*,
        u.name as user_name,
        u.email as user_email,
        u.id_number as user_cedula
    FROM dispositivos d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.ultimo_acceso DESC
");
$dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_dispositivos = count($dispositivos);
$usuarios_unicos = $conn->query("SELECT COUNT(DISTINCT user_id) FROM dispositivos")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dispositivos Vinculados</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 15px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        table {
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .fingerprint {
            font-family: monospace;
            font-size: 12px;
            color: #666;
            word-break: break-all;
        }
        .info-json {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            font-family: monospace;
            max-height: 100px;
            overflow-y: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4338ca;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 DISPOSITIVOS VINCULADOS</h1>
            <p>Listado completo de todos los celulares registrados en el sistema QR</p>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_dispositivos; ?></div>
                    <div class="stat-label">Total de dispositivos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $usuarios_unicos; ?></div>
                    <div class="stat-label">Usuarios con dispositivo</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo round($total_dispositivos / max($usuarios_unicos, 1), 1); ?></div>
                    <div class="stat-label">Dispositivos por usuario</div>
                </div>
            </div>
        </div>

        <?php if (empty($dispositivos)): ?>
            <div style="background: white; padding: 50px; text-align: center; border-radius: 15px;">
                <div style="font-size: 50px; margin-bottom: 20px;">📱</div>
                <h2>No hay dispositivos vinculados</h2>
                <p style="color: #666; margin: 20px;">Escanea el QR por primera vez para comenzar</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email/Cédula</th>
                        <th>Fingerprint (Huella digital)</th>
                        <th>Información del dispositivo</th>
                        <th>Vinculación</th>
                        <th>Último acceso</th>
                        <th>Usos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dispositivos as $d): ?>
                    <tr>
                        <td>#<?php echo $d['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($d['user_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($d['user_email']); ?><br>
                            <small>C.I: <?php echo $d['user_cedula'] ?? 'N/A'; ?></small>
                        </td>
                        <td>
                            <div class="fingerprint">
                                <?php echo substr($d['fingerprint'], 0, 20); ?>...
                            </div>
                            <span class="badge">ID único</span>
                        </td>
                        <td>
                            <?php 
                            $info = json_decode($d['info_adicional'], true);
                            if ($info): 
                            ?>
                            <div class="info-json">
                                <?php foreach ($info as $key => $value): ?>
                                    <strong><?php echo $key; ?>:</strong> <?php echo $value; ?><br>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <span class="badge">Sin datos</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($d['fecha_vinculacion'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($d['ultimo_acceso'])); ?></td>
                        <td>
                            <span class="badge"><?php echo $d['veces_usado']; ?> veces</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="text-align: center; margin: 30px;">
            <a href="qr.php" class="btn">⬅️ Volver al QR</a>
            <a href="javascript:location.reload()" class="btn" style="background: #6c757d;">🔄 Actualizar</a>
        </div>
    </div>
</body>
</html>