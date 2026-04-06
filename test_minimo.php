<?php
// test_minimo.php - VERSIÓN MÍNIMA PARA PROBAR
require_once 'config.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    
    try {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $mensaje = "✅ CORREO VÁLIDO: " . $user['name'];
        } else {
            $mensaje = "❌ CORREO NO REGISTRADO";
        }
    } catch (PDOException $e) {
        $mensaje = "❌ ERROR BD: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>TEST MÍNIMO</title>
</head>
<body>
    <h1>🔍 TEST MÍNIMO</h1>
    
    <?php if ($mensaje): ?>
        <div style="padding:20px; background:#f0f0f0; margin:20px 0;">
            <?= $mensaje ?>
        </div>
        <a href="test_minimo.php">← Volver</a>
    <?php else: ?>
        <form method="POST">
            <input type="email" name="email" placeholder="tu@email.com" required>
            <button type="submit">PROBAR</button>
        </form>
    <?php endif; ?>
</body>
</html>