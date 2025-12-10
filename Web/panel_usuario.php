<?php
session_start();
include 'seguridad.php';
include 'header.php';
include 'conexion.php';


if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

$mensaje = '';
$color = '';

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos actuales
$stmt = $conexion->prepare("SELECT nombre_usuario, email FROM USUARIOS WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($nombre_actual, $email_actual);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_nombre = trim($_POST['nombre_usuario']);
    $nuevo_email = trim($_POST['email']);
    $nueva_clave = trim($_POST['nueva_clave']);
    $confirmar_clave = trim($_POST['confirmar_clave']);

    if (!$nuevo_nombre || !filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "❌ Nombre o email inválidos.";
        $color = "red";
    } elseif (!empty($nueva_clave) && $nueva_clave !== $confirmar_clave) {
        $mensaje = "❌ Las contraseñas no coinciden.";
        $color = "red";
    } else {
        // Actualizar nombre y email
        $stmt = $conexion->prepare("UPDATE USUARIOS SET nombre_usuario = ?, email = ? WHERE id_usuario = ?");
        $stmt->bind_param("ssi", $nuevo_nombre, $nuevo_email, $id_usuario);
        $stmt->execute();
        $stmt->close();

        // Si hay nueva contraseña válida, actualizar
        if (!empty($nueva_clave)) {
            $clave_hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
            $clave_dovecot = "{PLAIN}" . $nueva_clave;  // (de momento seguimos así)

            $stmt = $conexion->prepare("UPDATE USUARIOS SET clave_hash = ?, clave_dovecot = ? WHERE id_usuario = ?");
            $stmt->bind_param("ssi", $clave_hash, $clave_dovecot, $id_usuario);
            $stmt->execute();
            $stmt->close();
        }

        $mensaje = "✅ Datos actualizados correctamente.";
        $color = "green";
        $_SESSION['usuario'] = $nuevo_nombre;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi cuenta</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>⚙️ Mi cuenta</h2>

    <?php if (!empty($mensaje)): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nombre de usuario:</label>
        <input type="text" name="nombre_usuario" value="<?= htmlspecialchars($nombre_actual) ?>" required>

        <label>Correo electrónico:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($email_actual) ?>" required>

        <label>Nueva contraseña (opcional):</label>
        <input type="password" name="nueva_clave" placeholder="Nueva contraseña">

        <label>Confirmar contraseña:</label>
        <input type="password" name="confirmar_clave" placeholder="Repite la contraseña">

        <input type="submit" value="Guardar cambios">
    </form>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
