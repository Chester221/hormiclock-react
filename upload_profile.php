<?php
// ============================================
// SUBIR FOTO DE PERFIL - VERSIÓN CORREGIDA
// ============================================

require_once 'config.php';

if (!isset($_SESSION['email'])) {
    header("Location: profile.php?status=error");
    exit();
}

// Crear directorio uploads si no existe
if (!file_exists('uploads')) {
    mkdir('uploads', 0755, true);
}

if (isset($_POST['submit_image']) && isset($_FILES['profile_pic'])) {
    $email = $_SESSION['email'];
    $file = $_FILES['profile_pic'];

    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileName = $file['name'];
    
    // Validar error de subida
    if ($fileError !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
        ];
        
        $error_msg = $error_messages[$fileError] ?? 'Error desconocido al subir el archivo';
        header("Location: profile.php?status=upload_error&msg=" . urlencode($error_msg));
        exit();
    }

    // Validar tamaño (2MB máximo)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($fileSize > $max_size) {
        header("Location: profile.php?status=too_big&max=2");
        exit();
    }

    // Validar extensión
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExt, $allowed_ext)) {
        header("Location: profile.php?status=invalid_type&allowed=" . implode(', ', $allowed_ext));
        exit();
    }

    // Validar MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $fileTmpName);
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        header("Location: profile.php?status=invalid_type");
        exit();
    }
    finfo_close($finfo);

    // Validar que sea una imagen real
    $image_info = getimagesize($fileTmpName);
    if (!$image_info) {
        header("Location: profile.php?status=invalid_image");
        exit();
    }

    // Generar nombre único
    $fileNameNew = "profile_" . $email . "_" . time() . "." . $fileExt;
    $fileDestination = 'uploads/' . $fileNameNew;

    // Mover archivo
    if (!move_uploaded_file($fileTmpName, $fileDestination)) {
        header("Location: profile.php?status=upload_fail");
        exit();
    }

    // Eliminar imagen anterior si existe
    try {
        $stmt = $conn->prepare("SELECT profile_image FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $old_image = $stmt->fetchColumn();
        
        if ($old_image && $old_image != 'default.svg' && file_exists('uploads/' . $old_image)) {
            unlink('uploads/' . $old_image);
        }
    } catch (Exception $e) {
        // Si hay error, continuamos de todos modos
        error_log("Error eliminando imagen anterior: " . $e->getMessage());
    }

    // Actualizar BD
    try {
        $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE email = ?");
        if (!$stmt->execute([$fileNameNew, $email])) {
            unlink($fileDestination); // Borrar si falla
            header("Location: profile.php?status=db_error");
            exit();
        }
    } catch (Exception $e) {
        unlink($fileDestination);
        error_log("Error actualizando BD: " . $e->getMessage());
        header("Location: profile.php?status=db_error");
        exit();
    }

    // Actualizar sesión con la nueva imagen
    $_SESSION['profile_img'] = $fileDestination;

    // Registrar actividad
    logActivity($conn, $email, 'update_profile', 'Foto de perfil actualizada');

    header("Location: profile.php?status=success");
    exit();
}

// Si no se envió el formulario, redirigir
header("Location: profile.php");
exit();
?>