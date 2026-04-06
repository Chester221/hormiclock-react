<?php
// test_qr.php - DEPURACIÓN COMPLETA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h2>🔍 DEPURACIÓN DEL SISTEMA QR</h2>";

// 1. Verificar conexión
try {
    echo "✅ Conexión a BD: OK<br>";
} catch (Exception $e) {
    die("❌ Error de conexión: " . $e->getMessage());
}

// 2. Verificar que la tabla users existe
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'users' existe<br>";
    } else {
        die("❌ Tabla 'users' NO existe");
    }
} catch (PDOException $e) {
    die("❌ Error verificando tabla: " . $e->getMessage());
}

// 3. Verificar estructura de users
try {
    $stmt = $conn->query("DESCRIBE users");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Columnas en users:<br>";
    echo "<ul>";
    foreach ($columnas as $col) {
        echo "<li>" . $col['Field'] . " - " . $col['Type'] . "</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "❌ Error en DESCRIBE: " . $e->getMessage() . "<br>";
}

// 4. Contar usuarios
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Total de usuarios: " . $total['total'] . "<br>";
} catch (PDOException $e) {
    echo "❌ Error contando usuarios: " . $e->getMessage() . "<br>";
}

// 5. Probar búsqueda por email (CAMBIAR POR TU CORREO)
$email_prueba = "galletasdechocolates666@gmail.com"; // ¡CAMBIA ESTO POR TU CORREO REAL!
try {
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
    $stmt->execute([$email_prueba]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ CORREO ENCONTRADO: ID=" . $user['id'] . ", Nombre=" . $user['name'] . "<br>";
    } else {
        echo "❌ CORREO NO ENCONTRADO: " . $email_prueba . "<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error buscando email: " . $e->getMessage() . "<br>";
}

// 6. Probar inserción en dispositivos
try {
    $test_fingerprint = "test_" . time();
    $test_user_id = 1; // CAMBIA ESTO POR UN ID VÁLIDO DE TU TABLA
    
    $insert = $conn->prepare("INSERT INTO dispositivos (fingerprint, user_id, user_agent, fecha_vinculacion, ultimo_acceso) VALUES (?, ?, ?, NOW(), NOW())");
    echo "✅ Query preparada correctamente<br>";
} catch (PDOException $e) {
    echo "❌ Error preparando insert: " . $e->getMessage() . "<br>";
}

// 7. Verificar tabla dispositivos
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'dispositivos'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'dispositivos' existe<br>";
    } else {
        echo "ℹ️ Tabla 'dispositivos' no existe (se creará automáticamente)<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>✅ DEPURACIÓN COMPLETADA</h3>";
echo "<p><a href='qr.php'>Volver al QR</a></p>";
?>