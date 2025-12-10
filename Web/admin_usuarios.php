<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$mensaje = '';
$color = '';

// Crear nuevo usuario
if (isset($_POST['crear'])) {
    $usuario = trim($_POST['nuevo_usuario']);
    $clave = trim($_POST['nueva_clave']);
    $rol = $_POST['nuevo_rol'];
    $email = trim($_POST['email']);

    if ($usuario && $clave && filter_var($email, FILTER_VALIDATE_EMAIL) && in_array($rol, ['admin', 'usuario', 'armero', 'furriel', 'oficina'])) {
        $hash_web = password_hash($clave, PASSWORD_DEFAULT);
        $hash_dovecot = "{PLAIN}" . $clave;

        try {
            $stmt = $conexion->prepare("INSERT INTO USUARIOS (nombre_usuario, clave_hash, rol, email, clave_dovecot) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $usuario, $hash_web, $rol, $email, $hash_dovecot);
            $stmt->execute();
            $stmt->close();

            // ✅ Crear también el usuario en el sistema Ubuntu (grupo: nologin, shell bloqueado)
            $usuario_sanitizado = escapeshellarg($usuario);
            $clave_sanitizada = escapeshellarg($clave);

            exec("getent group nologin || sudo groupadd nologin");

            $cmd_adduser = "sudo useradd -m -s /usr/sbin/nologin -g nologin $usuario_sanitizado";
            
            $cmd_passwd = "echo $usuario_sanitizado:$clave_sanitizada | sudo chpasswd";

            exec($cmd_adduser, $out1, $ret1);
            exec($cmd_passwd, $out2, $ret2);
            

            if ($ret1 !== 0 || $ret2 !== 0) {
                $mensaje = "⚠️ Usuario web creado, pero falló la creación en Ubuntu.";
                $color = "red";
            } else {
                $mensaje = "✅ Usuario creado correctamente.<br>👤 Usuario también creado en el sistema Ubuntu.";
                $color = "green";
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje = strpos($e->getMessage(), 'Duplicate entry') !== false ? "⚠️ Usuario o email ya registrado." : "❌ Error: " . $e->getMessage();
            $color = "red";
        }
    } else {
        $mensaje = "❌ Datos inválidos.";
        $color = "red";
    }
}

// Modificar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_usuario']);
    $nuevo_usuario = trim($_POST['nombre_usuario']);
    $nuevo_email = trim($_POST['email']);
    $nuevo_rol = $_POST['rol'];

    if ($nuevo_usuario && filter_var($nuevo_email, FILTER_VALIDATE_EMAIL) && in_array($nuevo_rol, ['admin', 'usuario', 'armero', 'furriel', 'oficina'])) {
        $stmt = $conexion->prepare("UPDATE USUARIOS SET nombre_usuario=?, email=?, rol=? WHERE id_usuario=?");
        $stmt->bind_param("sssi", $nuevo_usuario, $nuevo_email, $nuevo_rol, $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "✅ Datos actualizados.";
        $color = "green";
    } else {
        $mensaje = "❌ Datos inválidos al editar.";
        $color = "red";
    }
}

// Borrar usuario

// Borrar usuario
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
if ($id !== $_SESSION['id_usuario']) {
    // Obtener nombre de usuario antes de borrar
    $stmt = $conexion->prepare("SELECT nombre_usuario FROM USUARIOS WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($nombre_usuario);
    $stmt->fetch();
    $stmt->close();

    // Eliminar de la base de datos
    $conexion->query("DELETE FROM USUARIOS WHERE id_usuario = $id");

    // También eliminar del sistema Ubuntu
    $usuario_sanitizado = escapeshellarg($nombre_usuario);
    exec("sudo userdel -r $usuario_sanitizado");

    header("Location: admin_usuarios.php");
    exit;
}
}

$usuarios = $conexion->query("SELECT * FROM USUARIOS")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de usuarios</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>👤 Gestión de usuarios</h2>

    <?php if ($mensaje): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <h3>➕ Crear nuevo usuario</h3>
    <form method="POST">
        <input type="text" name="nuevo_usuario" placeholder="Nombre de usuario" required>
        <input type="password" name="nueva_clave" placeholder="Contraseña" required>
        <input type="email" name="email" placeholder="Correo electrónico" required>
        <select name="nuevo_rol" required>
            <option value="usuario">usuario</option>
            <option value="admin">admin</option>
            <option value="armero">armero</option>
            <option value="furriel">furriel</option>
            <option value="oficina">oficina</option>
        </select>
        <input type="submit" name="crear" value="Crear">
    </form>

    <h3>👥 Usuarios registrados</h3>
    <table>
        <tr><th>Usuario</th><th>Email</th><th>Rol</th><th>Acción</th></tr>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                    <td><input type="text" name="nombre_usuario" value="<?= htmlspecialchars($u['nombre_usuario']) ?>" required></td>
                    <td><input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required></td>
                    <td>
                        <select name="rol">
                            <option value="usuario" <?= $u['rol'] === 'usuario' ? 'selected' : '' ?>>usuario</option>
                            <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>admin</option>
                            <option value="armero" <?= $u['rol'] === 'armero' ? 'selected' : '' ?>>armero</option>
                            <option value="furriel" <?= $u['rol'] === 'furriel' ? 'selected' : '' ?>>furriel</option>
                            <option value="oficina" <?= $u['rol'] === 'oficina' ? 'selected' : '' ?>>oficina</option>
                        </select>
                    </td>
                    <td>
                        <button type="submit" name="editar" class="boton-accion">💾</button>
                        <?php if ($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                            <button type="button" class="boton-accion boton-rojo" onclick="if(confirm('¿Seguro que deseas eliminar este usuario?')) window.location.href='?eliminar=<?= $u['id_usuario'] ?>';">🗑️</button>
                        <?php else: ?>
                            <span style="color: gray;">(Tú)</span>
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
