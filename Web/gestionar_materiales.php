<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'furriel'])) {
    header("Location: index.php");
    exit;
}


$mensaje = "";

// Añadir nuevo material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_material'])) {
    $nombre = trim($_POST['nombre_material']);
    $descripcion = trim($_POST['descripcion']);
    $stock = intval($_POST['stock']);

    if ($nombre !== "" && $stock >= 0) {
        $stmt = $conexion->prepare("INSERT INTO MATERIALES (nombre_material, descripcion, stock_total) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $descripcion, $stock);
        $stmt->execute();
        $stmt->close();
        $mensaje = "✅ Material añadido correctamente.";
    } else {
        $mensaje = "⚠️ Nombre y stock válidos son obligatorios.";
    }
}

// Actualizar stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_stock'])) {
    $id_material = intval($_POST['id_material']);
    $cambio = intval($_POST['cambio_stock']);

    $conexion->query("UPDATE MATERIALES SET stock_total = stock_total + ($cambio) WHERE id_material = $id_material");
    $mensaje = "✅ Stock actualizado.";
}

// Eliminar material
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_material = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM MATERIALES WHERE id_material = $id_material");
    $mensaje = "✅ Material eliminado correctamente.";
}

// Listar materiales con asignados y total
$materiales = $conexion->query("
    SELECT 
        m.id_material,
        m.nombre_material,
        m.descripcion,
        m.stock_total,
        (
            SELECT COUNT(*) 
            FROM ENTREGAS_INDIVIDUALES ei 
            WHERE ei.id_material = m.id_material AND ei.fecha_devolucion IS NULL
        ) AS asignado
    FROM MATERIALES m
    ORDER BY m.nombre_material ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de materiales</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>📦 Gestión de materiales</h2>

    <?php if ($mensaje): ?>
        <p class="<?= strpos($mensaje, '✅') !== false ? 'mensaje-exito' : 'mensaje-error' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </p>
    <?php endif; ?>

    <section class="seccion-formulario">
        <h3>➕ Añadir nuevo material</h3>
        <form method="POST">
            <input type="hidden" name="nuevo_material" value="1">

            <label>Nombre del material:</label>
            <input class="input-text" type="text" name="nombre_material" required>

            <label>Descripción:</label>
            <textarea class="input-text" name="descripcion" rows="3"></textarea>

            <label>Stock inicial:</label>
            <input class="input-text" type="number" name="stock" min="0" required>

            <input class="boton-verde" type="submit" value="Añadir material">
        </form>
    </section>

    <hr>

    <section class="seccion-tabla">
        <h3>🛠️ Actualizar stock existente</h3>
        <?php if (count($materiales) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Descripción</th>
                        <th>Asignado</th>
                        <th>Stock actual</th>
                        <th>Total</th>
                        <th>Cambiar stock</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiales as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['nombre_material']) ?></td>
                            <td><?= htmlspecialchars($m['descripcion']) ?></td>
                            <td><?= $m['asignado'] ?></td>
                            <td style="color: <?= $m['stock_total'] < 5 ? '#ff6b6b' : '#a9e5a1' ?>;">
                                <?= $m['stock_total'] ?>
                                <?= $m['stock_total'] < 5 ? '⚠️' : '' ?>
                            </td>
                            <td><?= $m['stock_total'] + $m['asignado'] ?></td>
                            <td>
                                <form method="POST" style="display: flex; flex-direction: column; gap: 5px; width: 80%; max-width: 150px; margin: 0 10px;">
                                    <input type="hidden" name="actualizar_stock" value="1">
                                    <input type="hidden" name="id_material" value="<?= $m['id_material'] ?>">
                                    <input class="input-text" type="number" name="cambio_stock" required placeholder="Ej: 5 o -3">
                                    <input class="boton-verde boton-small" type="submit" value="Actualizar">

                                </form>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="boton-accion boton-rojo"
                                    onclick="if(confirm('¿Seguro que deseas eliminar este material?')) window.location.href='?eliminar=<?= $m['id_material'] ?>';">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay materiales registrados.</p>
        <?php endif; ?>
    </section>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
