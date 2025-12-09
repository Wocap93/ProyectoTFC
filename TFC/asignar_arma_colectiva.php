<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


// Paginación
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Total de asignaciones
$total_resultado = $conexion->query("SELECT COUNT(*) FROM ASIGNACION_COLECTIVO")->fetch_row()[0];
$total_paginas = ceil($total_resultado / $por_pagina);

// Procesar devolución
if (isset($_GET['devolver']) && is_numeric($_GET['devolver']) && isset($_GET['arma'])) {
    $id_personal = intval($_GET['devolver']);
    $id_armamento = intval($_GET['arma']);
    $conexion->query("
        DELETE FROM ASIGNACION_COLECTIVO
        WHERE id_armamento = $id_armamento AND id_personal = $id_personal
    ");
}

// Procesar asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion_armamento = $_POST['id_armamento'];

    // Buscar ID real del armamento por descripción
    $buscar_armamento = $conexion->prepare("
        SELECT id_armamento FROM ARMAMENTO_COLECTIVO
        WHERE CONCAT(nombre, ' - ', numero_serie) = ?
    ");
    $buscar_armamento->bind_param("s", $descripcion_armamento);
    $buscar_armamento->execute();
    $buscar_armamento->bind_result($id_armamento);
    $buscar_armamento->fetch();
    $buscar_armamento->close();

    if (!$id_armamento) {
        $mensaje = "❌ Armamento no válido.";
        $color = "red";
    } else {
        $militares = $_POST['id_personal'] ?? [];
        $errores = [];

        $checkTotal = $conexion->prepare("
            SELECT COUNT(*) FROM ASIGNACION_COLECTIVO
            WHERE id_armamento = ?
        ");
        $checkTotal->bind_param("i", $id_armamento);
        $checkTotal->execute();
        $checkTotal->bind_result($total_asignados);
        $checkTotal->fetch();
        $checkTotal->close();

        if ($total_asignados + count($militares) > 2) {
            $mensaje = "❌ Este armamento ya tiene $total_asignados militar(es) asignado(s). Solo se permiten 2.";
            $color = "red";
        } else {
            foreach ($militares as $id_personal) {
                $id_personal = intval($id_personal); // Sigue viniendo del <select> con IDs

                // Verificar si ya está asignado
                $check = $conexion->prepare("
                    SELECT COUNT(*) FROM ASIGNACION_COLECTIVO
                    WHERE id_armamento = ? AND id_personal = ?
                ");
                $check->bind_param("ii", $id_armamento, $id_personal);
                $check->execute();
                $check->bind_result($ya_asignado);
                $check->fetch();
                $check->close();

                if ($ya_asignado == 0) {
                    try {
                        $stmt = $conexion->prepare("
                            INSERT INTO ASIGNACION_COLECTIVO (id_armamento, id_personal, fecha_asignacion)
                            VALUES (?, ?, CURRENT_DATE)
                        ");
                        $stmt->bind_param("ii", $id_armamento, $id_personal);
                        $stmt->execute();
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        $errores[] = "❌ MySQL dice: " . $e->getMessage();
                    }
                } else {
                    $errores[] = "ID $id_personal ya está asignado a ese arma.";
                }
            }

            if (empty($errores)) {
                $mensaje = "✅ Armamento asignado correctamente.";
                $color = "green";
            } else {
                $mensaje = "⚠️ " . implode("<br>", $errores);
                $color = "orange";
            }
        }
    }
}

// Consultas
$armamento = $conexion->query("
    SELECT id_armamento, CONCAT(nombre, ' - ', numero_serie) AS descripcion
    FROM ARMAMENTO_COLECTIVO
")->fetch_all(MYSQLI_ASSOC);

$militares = $conexion->query("
    SELECT p.id_personal, CONCAT(p.nombre, ' ', p.apellidos) AS nombre_completo, e.nombre_empleo
    FROM PERSONAS p
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    WHERE p.activo = 1 AND e.nombre_empleo IN ('Soldado', 'Cabo')
    ORDER BY p.apellidos ASC, p.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

$asignaciones = $conexion->query("
    SELECT ac.id_armamento, ac.id_personal, ac.fecha_asignacion,
           p.nombre, p.apellidos, e.nombre_empleo,
           a.nombre AS arma, a.numero_serie
    FROM ASIGNACION_COLECTIVO ac
    JOIN PERSONAS p ON ac.id_personal = p.id_personal
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    JOIN ARMAMENTO_COLECTIVO a ON ac.id_armamento = a.id_armamento
    ORDER BY p.apellidos ASC, p.nombre ASC
    LIMIT $por_pagina OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar armamento colectivo</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>🧱 Asignar armamento colectivo</h2>

    <?php if (isset($mensaje)): ?>
        <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Selecciona armamento colectivo:</label>
        <input list="lista-armamento" name="id_armamento" class="combo-estilo" required placeholder="Escribe o selecciona armamento...">
        <datalist id="lista-armamento">
            <?php foreach ($armamento as $a): ?>
                <option value="<?= htmlspecialchars($a['descripcion']) ?>">
            <?php endforeach; ?>
        </datalist>

        <label>Asignar hasta 2 militares:</label>
        <select name="id_personal[]" multiple size="5" required>
            <?php foreach ($militares as $m): ?>
                <option value="<?= $m['id_personal'] ?>">
                    <?= htmlspecialchars($m['nombre_completo']) ?> (<?= htmlspecialchars($m['nombre_empleo']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <p style="font-size: 0.9rem; color: #aaa; text-align: left;">(Ctrl + clic para seleccionar más de uno)</p>

        <input type="submit" value="Asignar armamento">
    </form>

    <hr>
    <h3>📋 Asignaciones actuales</h3>
    <?php if (count($asignaciones) > 0): ?>
        <table>
    <tr>
        <th>Militar</th>
        <th>Empleo</th>
        <th>Arma</th>
        <th>Nº Serie</th>
        <th>Fecha</th>
        <th>Acción</th>
    </tr>
<?php foreach ($asignaciones as $as): ?>
    <tr>
        <td><?= htmlspecialchars($as['nombre'] . ' ' . $as['apellidos']) ?></td>
        <td><?= htmlspecialchars($as['nombre_empleo']) ?></td>
        <td><?= htmlspecialchars($as['arma']) ?></td>
        <td><?= htmlspecialchars($as['numero_serie']) ?></td>
        <td><?= $as['fecha_asignacion'] ?></td>
        <td>
            <a href="?devolver=<?= $as['id_personal'] ?>&arma=<?= $as['id_armamento'] ?>" onclick="return confirm('¿Confirmar devolución?')">Devolver</a>
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
        <p>No hay asignaciones activas.</p>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
