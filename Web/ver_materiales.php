<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


// Consulta extendida con asignado y total
$materiales = $conexion->query("
    SELECT 
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
    <title>Inventario de materiales</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>📦 Inventario de materiales</h2>

    <table>
        <tr>
            <th>Material</th>
            <th>Descripción</th>
            <th>Asignado</th>
            <th>Disponible</th>
            <th>Total</th>
        </tr>
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
