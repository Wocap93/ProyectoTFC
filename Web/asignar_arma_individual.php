<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


// Procesar devolución
if (isset($_GET['devolver']) && is_numeric($_GET['devolver'])) {
    $id_asignacion = intval($_GET['devolver']);
    $conexion->query("
        UPDATE ASIGNACION_INDIVIDUAL
        SET fecha_devolucion = CURRENT_DATE, estado = 'devuelto'
        WHERE id_asignacion = $id_asignacion
    ");
}

// Procesar asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_militar = $_POST['id_personal'] ?? null;
    $arma_texto = $_POST['id_arma'] ?? null;

    // Buscar ID real del militar
    $buscar_militar = $conexion->prepare("
        SELECT p.id_personal
        FROM PERSONAS p
        JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
        WHERE CONCAT(p.nombre, ' ', p.apellidos, ' (', e.nombre_empleo, ')') = ?
    ");
    $buscar_militar->bind_param("s", $nombre_militar);
    $buscar_militar->execute();
    $buscar_militar->bind_result($id_personal);
    $buscar_militar->fetch();
    $buscar_militar->close();

    // Buscar ID real del arma
$buscar_arma = $conexion->prepare("
    SELECT ai.id_arma
    FROM ARMAMENTO_INDIVIDUAL ai
    WHERE CONCAT(ai.nombre, ' - ', ai.numero_serie) = ?
      AND ai.id_arma NOT IN (
          SELECT id_arma FROM ASIGNACION_INDIVIDUAL WHERE fecha_devolucion IS NULL
      )
");
    $buscar_arma->bind_param("s", $arma_texto);
    $buscar_arma->execute();
    $buscar_arma->bind_result($id_arma);
    $buscar_arma->fetch();
    $buscar_arma->close();

    if ($id_personal && $id_arma) {
        $check = $conexion->prepare("
            SELECT COUNT(*) FROM ASIGNACION_INDIVIDUAL
            WHERE id_arma = ? AND fecha_devolucion IS NULL
        ");
        $check->bind_param("i", $id_arma);
        $check->execute();
        $check->bind_result($asignada);
        $check->fetch();
        $check->close();

        if ($asignada == 0) {
            $tipo = $conexion->query("
                SELECT tipo FROM ARMAMENTO_INDIVIDUAL WHERE id_arma = $id_arma
            ")->fetch_assoc()['tipo'];

            $existe = $conexion->prepare("
                SELECT COUNT(*) FROM ASIGNACION_INDIVIDUAL ai
                JOIN ARMAMENTO_INDIVIDUAL ar ON ai.id_arma = ar.id_arma
                WHERE ai.id_personal = ? AND ai.fecha_devolucion IS NULL AND ar.tipo = ?
            ");
            $existe->bind_param("is", $id_personal, $tipo);
            $existe->execute();
            $existe->bind_result($ya_asignado);
            $existe->fetch();
            $existe->close();

            if ($ya_asignado > 0) {
                $mensaje = "⚠️ Ya tiene un arma tipo $tipo asignada.";
                $color = "orange";
            } else {
                try {
                    $stmt = $conexion->prepare("
                        INSERT INTO ASIGNACION_INDIVIDUAL (id_arma, id_personal)
                        VALUES (?, ?)
                    ");
                    $stmt->bind_param("ii", $id_arma, $id_personal);
                    $stmt->execute();
                    $stmt->close();
                    $mensaje = "✅ Arma asignada correctamente.";
                    $color = "green";
                } catch (mysqli_sql_exception $e) {
                    $mensaje = "❌ Error: " . htmlspecialchars($e->getMessage());
                    $color = "red";
                }
            }
        } else {
            $mensaje = "⚠️ Esta arma ya está asignada actualmente.";
            $color = "orange";
        }
    } else {
        $mensaje = "⚠️ Debes seleccionar un militar y un arma válidos.";
        $color = "orange";
    }
}

// Consultas
$personas = $conexion->query("
    SELECT p.id_personal, CONCAT(p.nombre, ' ', p.apellidos) AS nombre_completo, e.nombre_empleo
    FROM PERSONAS p
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    WHERE p.activo = 1
    ORDER BY p.apellidos ASC, p.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

$armas_disponibles = $conexion->query("
    SELECT ai.id_arma, CONCAT(ai.nombre, ' - ', ai.numero_serie) AS arma
    FROM ARMAMENTO_INDIVIDUAL ai
    WHERE ai.id_arma NOT IN (
        SELECT id_arma FROM ASIGNACION_INDIVIDUAL WHERE fecha_devolucion IS NULL
    )
")->fetch_all(MYSQLI_ASSOC);

// Paginación
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Total de asignaciones activas
$total_query = $conexion->query("
    SELECT COUNT(*) as total
    FROM ASIGNACION_INDIVIDUAL
    WHERE fecha_devolucion IS NULL
");
$total_asignaciones = $total_query->fetch_assoc()['total'];
$total_paginas = ceil($total_asignaciones / $por_pagina);

// Asignaciones activas paginadas
$stmt = $conexion->prepare("
    SELECT asi.id_asignacion, p.nombre, p.apellidos, e.nombre_empleo,
           a.nombre AS arma, a.numero_serie, asi.fecha_asignacion
    FROM ASIGNACION_INDIVIDUAL asi
    JOIN PERSONAS p ON asi.id_personal = p.id_personal
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    JOIN ARMAMENTO_INDIVIDUAL a ON asi.id_arma = a.id_arma
    WHERE asi.fecha_devolucion IS NULL
    ORDER BY p.apellidos ASC, p.nombre ASC
    LIMIT ?, ?
");

$stmt->bind_param("ii", $inicio, $por_pagina);
$stmt->execute();
$asignaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar arma individual</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

    <?php include 'nav.php'; ?>

    <main class="contenedor">
        <h2>🔫 Asignar arma individual</h2>

        <?php if (isset($mensaje)): ?>
            <p class="mensaje-<?= $color === 'green' ? 'exito' : 'error' ?>"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>

<form method="POST">
    <label>Militar:</label>
    <input list="lista-militares" name="id_personal" class="combo-estilo" required placeholder="Escribe o selecciona un militar...">
    <datalist id="lista-militares">
        <?php foreach ($personas as $p): ?>
            <option value="<?= htmlspecialchars($p['nombre_completo']) ?> (<?= htmlspecialchars($p['nombre_empleo']) ?>)">
        <?php endforeach; ?>
    </datalist>

    <label>Arma disponible:</label>
    <input list="lista-armas" name="id_arma" class="combo-estilo" required placeholder="Escribe o selecciona un arma...">
    <datalist id="lista-armas">
        <?php foreach ($armas_disponibles as $a): ?>
            <option value="<?= htmlspecialchars($a['arma']) ?>">
        <?php endforeach; ?>
    </datalist>

    <input type="submit" value="Asignar arma">
</form>


        <hr>
        <h3>📋 Asignaciones activas</h3>
        <?php if (count($asignaciones) > 0): ?>
            <table>

    <tr>
        <th>Militar</th>
        <th>Empleo</th>
        <th>Arma</th>
        <th>Nº Serie</th>
        <th>Fecha asignación</th>
        <th>Acción</th>
    </tr>
<?php foreach ($asignaciones as $as): ?>
    <tr>
        <td><?= htmlspecialchars($as['nombre'] . ' ' . $as['apellidos']) ?></td>
        <td><?= htmlspecialchars($as['nombre_empleo']) ?></td>
        <td><?= htmlspecialchars($as['arma']) ?></td>
        <td><?= htmlspecialchars($as['numero_serie']) ?></td>
        <td><?= $as['fecha_asignacion'] ?></td>
        <td><a href="?devolver=<?= $as['id_asignacion'] ?>" onclick="return confirm('¿Confirmar devolución?')">Devolver</a></td>
    </tr>
<?php endforeach; ?>
            </table>
        <?php if ($total_paginas > 1): ?>
<div class="paginacion">
    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="?pagina=<?= $i ?>" class=" <?= $i == $pagina ? 'activa' : '' ?>">
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
