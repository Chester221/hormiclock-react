<?php
// importar.php - Script para importar SQL en Pxxl App
$host = 'db.pxxl.pro';
$port = '29926';
$user = 'pxxluser_mnnecesn87e348e';
$pass = '5bb1f99454d27ed029ab11ea9937804d5773da8e4cb00fdb4a30f6ab5f2686f9';
$dbname = 'pxxldb_mnnecesnc47fbee';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        
        // Dividir el SQL en comandos individuales
        $pdo->exec($sql);
        
        $mensaje = '<div style="color: green; background: #d4edda; padding: 15px; border-radius: 5px;">✅ Importación exitosa! Las tablas se han creado correctamente.</div>';
    } catch (Exception $e) {
        $mensaje = '<div style="color: red; background: #f8d7da; padding: 15px; border-radius: 5px;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Base de Datos - HormiClock</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; padding: 40px; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 600px; width: 100%; }
        h1 { color: #0f172a; margin-bottom: 10px; }
        .info { background: #eef2ff; padding: 15px; border-radius: 10px; margin: 20px 0; font-size: 14px; color: #1e40af; }
        input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #cbd5e1; border-radius: 10px; margin: 15px 0; }
        button { background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 30px; cursor: pointer; font-weight: 600; width: 100%; }
        button:hover { background: #2563eb; }
        .credential { font-family: monospace; font-size: 12px; background: #f1f5f9; padding: 10px; border-radius: 8px; margin: 10px 0; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Importar Base de Datos</h1>
        <p>Selecciona el archivo <strong>hormiclock.sql</strong> para importarlo a Pxxl App.</p>
        
        <?php echo $mensaje; ?>
        
        <div class="info">
            <strong>ℹ️ Información de conexión:</strong><br>
            Base de datos: <strong><?php echo $dbname; ?></strong><br>
            Usuario: <strong><?php echo $user; ?></strong><br>
            Host: <strong><?php echo $host . ':' . $port; ?></strong>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="sql_file" accept=".sql" required>
            <button type="submit">🚀 Importar ahora</button>
        </form>
        
        <div class="credential">
            ⚠️ Este script solo debe usarse una vez. Después de importar, elimínalo por seguridad.
        </div>
    </div>
</body>
</html>