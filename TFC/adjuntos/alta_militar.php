<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}
include 'conexion.php';

// Obtener lista de empleos
$empleos = $conexion->query("SELECT id_empleo, nombre_empleo FROM EMPLEOS ORDER BY nombre_empleo ASC")->fetch_all(MYSQLI_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $dni = strtoupper(trim($_POST['dni'] ?? ''));
    $empleo_id = intval($_POST['empleo_id'] ?? 0);

    if ($nombre && $apellidos && $dni && $empleo_id) {
        if (!preg_match("/^[0-9]{8}[A-Z]$/", $dni)) {
            $mensaje = "❌ El DNI debe tener el formato correcto (8 números + 1 letra en mayúscula).";
            $color = "red";
        } else {
            $verificar = $conexion->prepare("SELECT COUNT(*) FROM PERSONAS WHERE dni = ?");
            $verificar->bind_param("s", $dni);
            $verificar->execute();
            $verificar->bind_result($existe);
            $verificar->fetch();
            $verificar->close();

            if ($existe > 0) {
                $mensaje = "⚠️ Ya existe un militar con ese DNI.";
                $color = "orange";
            } else {
                $stmt = $conexion->prepare("
                    INSERT INTO PERSONAS (nombre, apellidos, dni, empleo_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("sssi", $nombre, $apellidos, $dni, $empleo_id);
                if ($stmt->execute()) {
                    $mensaje = "✅ Militar dado de alta correctamente.";
                    $color = "green";
                } else {
                    $mensaje = "❌ Error al guardar el militar.";
                    $color = "red";
                }
                $stmt->close();
            }
        }
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
        $color = "orange";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alta de militar</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'nav.php'; ?>

    <main class="contenedor">
        <h2>➕ Alta de nuevo militar</h2>

        <?php if (isset($mensaje)): ?>
            <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" required maxlength="50">

            <label for="apellidos">Apellidos:</label>
            <input type="text" name="apellidos" required maxlength="150">

            <label for="dni">DNI:</label>
            <input type="text" name="dni" required maxlength="9" pattern="[0-9]{8}[A-Z]">

            <label for="empleo_id">Empleo:</label>
            <select name="empleo_id" required>
                <option value="">-- Selecciona un empleo --</option>
                <?php foreach ($empleos as $e): ?>
                    <option value="<?= $e['id_empleo'] ?>"><?= htmlspecialchars($e['nombre_empleo']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="submit" value="Dar de alta">
        </form>
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
