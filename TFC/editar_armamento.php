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

// Actualizar si se ha enviado el formulario de modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_armamento'])) {
    $tipo = $_POST['tipo'];
    $id = intval($_POST['id_armamento']);
    $nombre = trim($_POST['nombre']);
    $numero_serie = trim($_POST['numero_serie']);
    $estado = $_POST['estado'];
    $asignado_a = $_POST['asignado_a'] ?? null;
    $tipo_individual = $_POST['tipo_individual'] ?? null;

    try {
        if ($tipo === 'individual') {
            $stmt = $conexion->prepare("UPDATE ARMAMENTO_INDIVIDUAL SET nombre=?, numero_serie=?, estado=?, tipo=? WHERE id_arma=?");
            $stmt->bind_param("ssssi", $nombre, $numero_serie, $estado, $tipo_individual, $id);
        } else {
            $stmt = $conexion->prepare("UPDATE ARMAMENTO_COLECTIVO SET nombre=?, numero_serie=?, estado=?, asignado_a=? WHERE id_armamento=?");
            $stmt->bind_param("ssssi", $nombre, $numero_serie, $estado, $asignado_a, $id);
        }
        $stmt->execute();
        $mensaje = "✅ Armamento modificado correctamente.";
        $color = "green";
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $mensaje = "⚠️ Ya existe un arma con ese número de serie.";
        } else {
            $mensaje = "❌ Error al modificar el armamento: " . $e->getMessage();
        }
        $color = "red";
    }
}

$armas_ind = $conexion->query("SELECT * FROM ARMAMENTO_INDIVIDUAL ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
$armas_col = $conexion->query("SELECT * FROM ARMAMENTO_COLECTIVO ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Modificar armamento</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>
<main class="contenedor">
    <h2>✏️ Modificar armamento</h2>

    <?php if ($mensaje): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <h3>🔫 Armamento individual</h3>
    <table>
        <tr>
            <th>Nombre</th><th>Nº Serie</th><th>Tipo</th><th>Estado</th><th>Acción</th>
        </tr>
        <?php foreach ($armas_ind as $a): ?>
        <tr>
            <form method="POST">
                <input type="hidden" name="tipo" value="individual">
                <input type="hidden" name="id_armamento" value="<?= $a['id_arma'] ?>">
                <td><input type="text" name="nombre" value="<?= htmlspecialchars($a['nombre']) ?>"></td>
                <td><input type="text" name="numero_serie" value="<?= htmlspecialchars($a['numero_serie']) ?>"></td>
                <td>
                    <select name="tipo_individual">
                        <option value="fusil" <?= $a['tipo'] === 'fusil' ? 'selected' : '' ?>>Fusil</option>
                        <option value="pistola" <?= $a['tipo'] === 'pistola' ? 'selected' : '' ?>>Pistola</option>
                        <option value="otro" <?= $a['tipo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </td>
                <td>
                    <select name="estado">
                        <option value="operativo" <?= $a['estado'] === 'operativo' ? 'selected' : '' ?>>Operativo</option>
                        <option value="inoperativo" <?= $a['estado'] === 'inoperativo' ? 'selected' : '' ?>>Inoperativo</option>
                        <option value="escalón" <?= $a['estado'] === 'escalón' ? 'selected' : '' ?>>Escalón</option>
                    </select>
                </td>
                <td><input type="submit" value="Guardar"></td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>

    <h3>🧨 Armamento colectivo</h3>
    <table>
        <tr>
            <th>Nombre</th><th>Nº Serie</th><th>Asignado a</th><th>Estado</th><th>Acción</th>
        </tr>
        <?php foreach ($armas_col as $a): ?>
        <tr>
            <form method="POST">
                <input type="hidden" name="tipo" value="colectivo">
                <input type="hidden" name="id_armamento" value="<?= $a['id_armamento'] ?>">
                <td><input type="text" name="nombre" value="<?= htmlspecialchars($a['nombre']) ?>"></td>
                <td><input type="text" name="numero_serie" value="<?= htmlspecialchars($a['numero_serie']) ?>"></td>
                <td><input type="text" name="asignado_a" value="<?= htmlspecialchars($a['asignado_a']) ?>"></td>
                <td>
                    <select name="estado">
                        <option value="operativo" <?= $a['estado'] === 'operativo' ? 'selected' : '' ?>>Operativo</option>
                        <option value="inoperativo" <?= $a['estado'] === 'inoperativo' ? 'selected' : '' ?>>Inoperativo</option>
                        <option value="escalón" <?= $a['estado'] === 'escalón' ? 'selected' : '' ?>>Escalón</option>
                    </select>
                </td>
                <td><input type="submit" value="Guardar"></td>
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
