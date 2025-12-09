<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


$pagina_ind = isset($_GET['pagina_ind']) ? max(1, intval($_GET['pagina_ind'])) : 1;
$pagina_col = isset($_GET['pagina_col']) ? max(1, intval($_GET['pagina_col'])) : 1;
$mensaje = '';
$color = '';
$es_admin = $_SESSION['rol'] === 'admin';
$puede_editar_armamento = in_array($_SESSION['rol'], ['admin', 'armero']);

// Eliminar armamento
if (isset($_GET['eliminar']) && isset($_GET['tipo']) && in_array($_SESSION['rol'], ['admin', 'armero'])) {

    $id = intval($_GET['eliminar']);
    $tipo = $_GET['tipo'];

    if ($tipo === 'individual') {
        $asignado = $conexion->query("SELECT COUNT(*) FROM ASIGNACION_INDIVIDUAL WHERE id_arma = $id AND fecha_devolucion IS NULL")->fetch_row()[0];
        if ($asignado == 0) {
            $conexion->query("DELETE FROM ARMAMENTO_INDIVIDUAL WHERE id_arma = $id");
        } else {
            $mensaje = "⚠️ No se puede eliminar: el arma individual está asignada.";
            $color = "red";
        }
    } elseif ($tipo === 'colectivo') {
        $asignado = $conexion->query("SELECT COUNT(*) FROM ASIGNACION_COLECTIVO WHERE id_armamento = $id")->fetch_row()[0];
        if ($asignado == 0) {
            $conexion->query("DELETE FROM ARMAMENTO_COLECTIVO WHERE id_armamento = $id");
        } else {
            $mensaje = "⚠️ No se puede eliminar: el arma colectiva está asignada.";
            $color = "red";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?pagina_ind=$pagina_ind&pagina_col=$pagina_col");
    exit;
}

// Actualizar si se ha enviado el formulario de modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_armamento']) && in_array($_SESSION['rol'], ['admin', 'armero'])) {

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


$por_pagina = 10;
$offset_ind = ($pagina_ind - 1) * $por_pagina;
$offset_col = ($pagina_col - 1) * $por_pagina;

$total_ind = $conexion->query("SELECT COUNT(*) FROM ARMAMENTO_INDIVIDUAL")->fetch_row()[0];
$total_col = $conexion->query("SELECT COUNT(*) FROM ARMAMENTO_COLECTIVO")->fetch_row()[0];
$total_paginas_ind = ceil($total_ind / $por_pagina);
$total_paginas_col = ceil($total_col / $por_pagina);

$armas_ind = $conexion->query("SELECT * FROM ARMAMENTO_INDIVIDUAL ORDER BY nombre ASC LIMIT $por_pagina OFFSET $offset_ind")->fetch_all(MYSQLI_ASSOC);
$armas_col = $conexion->query("SELECT * FROM ARMAMENTO_COLECTIVO ORDER BY nombre ASC LIMIT $por_pagina OFFSET $offset_col")->fetch_all(MYSQLI_ASSOC);
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
    <h2><?= $puede_editar_armamento ? '✏️ Modificar armamento' : '🔍 Ver armamento' ?></h2>


    <?php if ($mensaje): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>


<div class="contenedor-secundario">
    <h3>🔫 Armamento individual</h3>
    <table>
        <tr>
            <th>Nombre</th><th>Nº Serie</th><th>Tipo</th><th>Estado</th><th>Asignado</th><?= $es_admin ? '<th>Acción</th>' : '' ?>
        </tr>
        <?php foreach ($armas_ind as $a): ?>
        <?php $asignado_ind = $conexion->query("SELECT COUNT(*) FROM ASIGNACION_INDIVIDUAL WHERE id_arma = {$a['id_arma']} AND fecha_devolucion IS NULL")->fetch_row()[0] > 0; ?>
        <tr>
        <?php if ($puede_editar_armamento): ?>
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
                <td><?= $asignado_ind ? '✅' : '—' ?></td>
                <td>
                    <button type="submit" class="boton-accion">💾</button>
                    <?php if (!$asignado_ind): ?>
                        <button type="button" class="boton-accion boton-rojo"
                        onclick="if(confirm('¿Seguro que deseas eliminar este arma?')) window.location.href='?eliminar=<?= $a['id_arma'] ?>&tipo=individual&pagina_ind=<?= $pagina_ind ?>&pagina_col=<?= $pagina_col ?>';">🗑️
                        </button>
                    <?php endif; ?>
                </td>
            </form>
        <?php else: ?>
            <td><?= htmlspecialchars($a['nombre']) ?></td>
            <td><?= htmlspecialchars($a['numero_serie']) ?></td>
            <td><?= htmlspecialchars($a['tipo']) ?></td>
            <td><?= htmlspecialchars($a['estado']) ?></td>
            <td><?= $asignado_ind ? '✅' : '—' ?></td>
        <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php if ($total_paginas_ind > 1): ?>
    <div class="paginacion">
        <?php for ($i = 1; $i <= $total_paginas_ind; $i++): ?>
            <a href="?pagina_ind=<?= $i ?>&pagina_col=<?= $pagina_col ?>" class="<?= $i == $pagina_ind ? 'activa' : '' ?>"> <?= $i ?> </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div class="contenedor-secundario">
<h3>🧨 Armamento colectivo</h3>
<table>
        <tr>
            <th>Nombre</th><th>Nº Serie</th><th>Asignado a</th><th>Estado</th><th>Asignado</th><?= $es_admin ? '<th>Acción</th>' : '' ?>
        </tr>
        <?php foreach ($armas_col as $a): ?>
        <?php $asignado_col = $conexion->query("SELECT COUNT(*) FROM ASIGNACION_COLECTIVO WHERE id_armamento = {$a['id_armamento']}")->fetch_row()[0] > 0; ?>
        <tr>
        <?php if ($puede_editar_armamento): ?>
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
                <td><?= $asignado_col ? '✅' : '—' ?></td>
                <td>
                    <button type="submit" class="boton-accion">💾</button>
                    <?php if (!$asignado_col): ?>
                        <button type="button" class="boton-accion boton-rojo"
                        onclick="if(confirm('¿Seguro que deseas eliminar este arma?')) window.location.href='?eliminar=<?= $a['id_armamento'] ?>&tipo=colectivo&pagina_ind=<?= $pagina_ind ?>&pagina_col=<?= $pagina_col ?>';">🗑️
                        </button>
                    <?php endif; ?>
                </td>
            </form>
        <?php else: ?>
            <td><?= htmlspecialchars($a['nombre']) ?></td>
            <td><?= htmlspecialchars($a['numero_serie']) ?></td>
            <td><?= htmlspecialchars($a['asignado_a']) ?></td>
            <td><?= htmlspecialchars($a['estado']) ?></td>
            <td><?= $asignado_col ? '✅' : '—' ?></td>
        <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php if ($total_paginas_col > 1): ?>
    <div class="paginacion">
        <?php for ($i = 1; $i <= $total_paginas_col; $i++): ?>
            <a href="?pagina_ind=<?= $pagina_ind ?>&pagina_col=<?= $i ?>" class="<?= $i == $pagina_col ? 'activa' : '' ?>"> <?= $i ?> </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>
