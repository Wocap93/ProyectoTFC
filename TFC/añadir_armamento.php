<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'armero'])) {
    header("Location: index.php");
    exit;
}


$mensaje = '';
$color = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_armamento = $_POST['tipo_armamento'];
    $nombre = trim($_POST['nombre']);
    $numero_serie = trim($_POST['numero_serie']);
    $estado = $_POST['estado'];
    $asignado_a = isset($_POST['asignado_a']) ? trim($_POST['asignado_a']) : null;

    try {
        if ($tipo_armamento === 'individual') {
            $tipo = $_POST['tipo_individual'];
            $stmt = $conexion->prepare("INSERT INTO ARMAMENTO_INDIVIDUAL (nombre, numero_serie, estado, tipo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $numero_serie, $estado, $tipo);
        } else {
            $stmt = $conexion->prepare("INSERT INTO ARMAMENTO_COLECTIVO (nombre, numero_serie, estado, asignado_a) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $numero_serie, $estado, $asignado_a);
        }

        $stmt->execute();
        $mensaje = "✅ Armamento añadido correctamente.";
        $color = "green";
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $mensaje = "⚠️ Ya existe un arma con ese número de serie.";
        } else {
            $mensaje = "❌ Error al añadir el armamento: " . $e->getMessage();
        }
        $color = "red";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir armamento</title>
    <link rel="stylesheet" href="estilo.css">
    <script>
        function toggleCampos() {
            const tipo = document.getElementById('tipo_armamento').value;
            document.getElementById('campo_tipo_individual').style.display = (tipo === 'individual') ? 'block' : 'none';
            document.getElementById('campo_asignado_a').style.display = (tipo === 'colectivo') ? 'block' : 'none';
        }
    </script>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>➕ Añadir nuevo armamento</h2>

    <?php if ($mensaje): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST" oninput="toggleCampos()">
        <label>Tipo de armamento:</label>
        <select name="tipo_armamento" id="tipo_armamento" required>
            <option value="individual">Individual</option>
            <option value="colectivo">Colectivo</option>
        </select>

        <label>Nombre del arma:</label>
        <input type="text" name="nombre" required>

        <label>Número de serie:</label>
        <input type="text" name="numero_serie" required>

        <label>Estado:</label>
        <select name="estado" required>
            <option value="operativo">Operativo</option>
            <option value="inoperativo">Inoperativo</option>
            <option value="escalón">Escalón</option>
        </select>

        <div id="campo_tipo_individual">
            <label>Tipo de arma individual:</label>
            <select name="tipo_individual">
                <option value="fusil">Fusil</option>
                <option value="pistola">Pistola</option>
                <option value="otro">Otro</option>
            </select>
        </div>

        <div id="campo_asignado_a" style="display: none;">
            <label>Asignado a (sección, unidad, etc.):</label>
            <input type="text" name="asignado_a" placeholder="Ej. 1ª Sección">
        </div>

        <input type="submit" value="Guardar armamento">
    </form>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
