<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


$es_admin = $_SESSION['rol'] === 'admin';
$puede_editar_personal = in_array($_SESSION['rol'], ['admin', 'oficina']);
$puede_ver_dni = in_array($_SESSION['rol'], ['admin', 'oficina']);
$mensaje = '';
$color = '';

$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Eliminar personal
if (isset($_GET['eliminar']) && $puede_editar_personal) {
    $id = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM PERSONAS WHERE id_personal = $id");
    header("Location: ver_personal.php?pagina=$pagina");
    exit;
}

// Modificar datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_personal']) && $puede_editar_personal) {
    $id = intval($_POST['id_personal']);
    $empleo_id = intval($_POST['empleo_id']);
    $activo = isset($_POST['activo']) && $_POST['activo'] === '1' ? 1 : 0;
    $stmt = $conexion->prepare("UPDATE PERSONAS SET empleo_id=?, activo=? WHERE id_personal=?");
    $stmt->bind_param("iii", $empleo_id, $activo, $id);
    $stmt->execute();
    $stmt->close();
    $mensaje = "✅ Datos actualizados correctamente.";
    $color = "green";
}

$total = $conexion->query("SELECT COUNT(*) FROM PERSONAS")->fetch_row()[0];
$total_paginas = ceil($total / $por_pagina);

$personas = $conexion->query("SELECT p.*, e.nombre_empleo FROM PERSONAS p LEFT JOIN EMPLEOS e ON p.empleo_id = e.id_empleo ORDER BY apellidos, nombre LIMIT $por_pagina OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
$empleos = $conexion->query("SELECT id_empleo, nombre_empleo FROM EMPLEOS")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver personal</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>
<main class="contenedor">
    <h2>🧍 Personal del cuartel</h2>

    <?php if ($mensaje): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <table>
        <tr>
            <th>Nombre</th><th>Apellidos</th><?= $puede_ver_dni ? '<th>DNI</th>' : '' ?><th>Empleo</th><th>Activo</th><?= $puede_editar_personal ? '<th>Acción</th>' : '' ?>
        </tr>
        <?php foreach ($personas as $p): ?>
        <tr>
        <?php if ($puede_editar_personal): ?>
            <form method="POST">
                <input type="hidden" name="id_personal" value="<?= $p['id_personal'] ?>">
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td><?= htmlspecialchars($p['apellidos']) ?></td>
                <?php if ($puede_ver_dni): ?><td><?= htmlspecialchars($p['dni']) ?></td><?php endif; ?>
                <td>
                    <select name="empleo_id">
                        <?php foreach ($empleos as $e): ?>
                            <option value="<?= $e['id_empleo'] ?>" <?= $e['id_empleo'] == $p['empleo_id'] ? 'selected' : '' ?>><?= $e['nombre_empleo'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="activo">
                        <option value="1" <?= $p['activo'] ? 'selected' : '' ?>>✅</option>
                        <option value="0" <?= !$p['activo'] ? 'selected' : '' ?>>—</option>
                    </select>
                </td>
                <td>
                    <button type="submit" class="boton-accion">💾</button>
                    <button type="button" class="boton-accion boton-rojo" onclick="if(confirm('¿Seguro que deseas eliminar a este militar?')) window.location.href='?eliminar=<?= $p['id_personal'] ?>&pagina=<?= $pagina ?>';">🗑️</button>
                </td>
            </form>
        <?php else: ?>
            <td><?= htmlspecialchars($p['nombre']) ?></td>
            <td><?= htmlspecialchars($p['apellidos']) ?></td>
            <?php if ($puede_ver_dni): ?><td><?= htmlspecialchars($p['dni']) ?></td><?php endif; ?>
            <td><?= htmlspecialchars($p['nombre_empleo']) ?></td>
            <td><?= $p['activo'] ? '✅' : '—' ?></td>
        <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?pagina=<?= $i ?>" class="<?= $i == $pagina ? 'activa' : '' ?>"> <?= $i ?> </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>
