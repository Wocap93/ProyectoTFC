<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


// Consultas previas
$personas = $conexion->query("
    SELECT p.id_personal, CONCAT(p.nombre, ' ', p.apellidos) AS nombre_completo, e.nombre_empleo
    FROM PERSONAS p
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    WHERE p.activo = 1
    ORDER BY p.apellidos ASC, p.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

$materiales = $conexion->query("
    SELECT id_material, nombre_material
    FROM MATERIALES
    ORDER BY nombre_material ASC
")->fetch_all(MYSQLI_ASSOC);

// Mapas
$mapa_personas = [];
foreach ($personas as $p) {
    $clave = $p['nombre_completo'] . ' (' . $p['nombre_empleo'] . ')';
    $mapa_personas[$clave] = $p['id_personal'];
}

$mapa_materiales = [];
foreach ($materiales as $m) {
    $mapa_materiales[$m['nombre_material']] = $m['id_material'];
}

// Devolución
if (isset($_GET['devolver']) && is_numeric($_GET['devolver'])) {
    $id_entrega = intval($_GET['devolver']);
    $conexion->query("
        UPDATE ENTREGAS_INDIVIDUALES
        SET fecha_devolucion = CURRENT_DATE
        WHERE id_entrega = $id_entrega
    ");
}

// Asignación múltiple
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_persona = $_POST['id_personal'] ?? '';
    $materiales_seleccionados = $_POST['id_material'] ?? [];

    $id_personal = $mapa_personas[$nombre_persona] ?? null;

    if ($id_personal && count($materiales_seleccionados) > 0) {
        $errores = [];
        $asignados = 0;
        $materiales_unicos = array_unique($materiales_seleccionados);

        if (count($materiales_unicos) !== count($materiales_seleccionados)) {
            $mensaje = "⚠️ No se puede seleccionar el mismo material más de una vez.";
            $color = "orange";
        } else {
            foreach ($materiales_unicos as $nombre_material) {
                $id_material = $mapa_materiales[$nombre_material] ?? null;

                if ($id_material) {
                    // Verificar si ya tiene ese material sin devolver
                    $check = $conexion->prepare("
                        SELECT COUNT(*) FROM ENTREGAS_INDIVIDUALES
                        WHERE id_personal = ? AND id_material = ? AND fecha_devolucion IS NULL
                    ");
                    $check->bind_param("ii", $id_personal, $id_material);
                    $check->execute();
                    $check->bind_result($existe);
                    $check->fetch();
                    $check->close();

                    if ($existe > 0) {
                        $errores[] = "El material \"$nombre_material\" ya está asignado.";
                    } else {
                        $stmt = $conexion->prepare("CALL asignar_material_con_stock(?, ?)");
                        $stmt->bind_param("ii", $id_personal, $id_material);
                        if ($stmt->execute()) {
                            $asignados++;
                        }
                        $stmt->close();
                    }
                }
            }

            if (empty($errores)) {
                $mensaje = "✅ $asignados material(es) asignado(s) correctamente.";
                $color = "green";
            } else {
                $mensaje = "⚠️ " . implode("<br>", $errores);
                $color = "orange";
            }
        }
    } else {
        $mensaje = "⚠️ Selecciona militar y al menos un material.";
        $color = "orange";
    }
}

// Paginación de entregas
$por_pagina = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina - 1) * $por_pagina;

$consulta_entregas = $conexion->prepare("
    SELECT ei.id_entrega, p.nombre, p.apellidos, m.nombre_material, ei.fecha_entrega, e.nombre_empleo
    FROM ENTREGAS_INDIVIDUALES ei
    JOIN PERSONAS p ON ei.id_personal = p.id_personal
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    JOIN MATERIALES m ON ei.id_material = m.id_material
    WHERE ei.fecha_devolucion IS NULL
    ORDER BY apellidos ASC, nombre ASC
    LIMIT ?, ?
");
$consulta_entregas->bind_param("ii", $inicio, $por_pagina);
$consulta_entregas->execute();
$entregas = $consulta_entregas->get_result()->fetch_all(MYSQLI_ASSOC);
$consulta_entregas->close();

$total_resultado = $conexion->query("
    SELECT COUNT(*) AS total
    FROM ENTREGAS_INDIVIDUALES
    WHERE fecha_devolucion IS NULL
")->fetch_assoc();
$total_paginas = ceil($total_resultado['total'] / $por_pagina);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación de material de campo</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>🎒 Asignar material de campo</h2>

    <?php if (isset($mensaje)): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
    <label>Militar:</label>
    <input list="militares" name="id_personal" class="combo-estilo" required placeholder="Escribe o selecciona un militar...">
    <datalist id="militares">
        <?php foreach ($personas as $p): ?>
            <option value="<?= htmlspecialchars($p['nombre_completo']) ?> (<?= htmlspecialchars($p['nombre_empleo']) ?>)">
        <?php endforeach; ?>
    </datalist>

    <label>Selecciona materiales (Ctrl o Shift para varios):</label>
    <select name="id_material[]" multiple size="5" required>
        <?php foreach ($materiales as $m): ?>
            <option value="<?= htmlspecialchars($m['nombre_material']) ?>">
                <?= htmlspecialchars($m['nombre_material']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <p style="font-size: 0.9rem; color: #aaa; text-align: left;">(Ctrl o Shift para seleccionar varios)</p>
    <input type="submit" value="Asignar material">
</form>


    <hr>
    <h3>📋 Entregas activas</h3>
    <?php if (count($entregas) > 0): ?>
        <table>
            <tr>
                <th>Militar</th>
                <th>Empleo</th>
                <th>Material</th>
                <th>Fecha entrega</th>
                <th>Acción</th>
            </tr>
            <?php foreach ($entregas as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellidos']) ?></td>
                    <td><?= htmlspecialchars($e['nombre_empleo']) ?></td>
                    <td><?= htmlspecialchars($e['nombre_material']) ?></td>
                    <td><?= $e['fecha_entrega'] ?></td>
                    <td>
                        <a href="?devolver=<?= $e['id_entrega'] ?>" onclick="return confirm('¿Confirmar devolución?')">Devolver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacion">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?pagina=<?= $i ?>" class="<?= $i == $pagina ? 'activa' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p>No hay entregas activas.</p>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
