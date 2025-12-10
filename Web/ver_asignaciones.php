<?php

include 'seguridad.php';
include 'header.php';
include 'conexion.php';


if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}


// Obtener todos los empleos para el selector
$empleos = $conexion->query("SELECT id_empleo, nombre_empleo FROM EMPLEOS")->fetch_all(MYSQLI_ASSOC);

// Consulta de militares con asignaciones
// Paginación
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$condiciones = [];
$params = [];
$param_types = "";

// Nombre
if (!empty($_GET['nombre'])) {
    $condiciones[] = "CONCAT(p.nombre, ' ', p.apellidos) LIKE ?";
    $params[] = "%" . $_GET['nombre'] . "%";
    $param_types .= "s";
}

// Empleo
if (!empty($_GET['empleo'])) {
    $condiciones[] = "e.nombre_empleo = ?";
    $params[] = $_GET['empleo'];
    $param_types .= "s";
}

$where = count($condiciones) ? "WHERE " . implode(" AND ", $condiciones) : "";

// Conteo total para paginación
$stmt = $conexion->prepare("
    SELECT COUNT(*) FROM PERSONAS p
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    $where
");
if ($params) $stmt->bind_param($param_types, ...$params);
$stmt->execute();
$stmt->bind_result($total_resultado);
$stmt->fetch();
$stmt->close();
$total_paginas = ceil($total_resultado / $por_pagina);

// Consulta principal con LIMIT
$sql = "
    SELECT
        p.id_personal,
        CONCAT(p.nombre, ' ', p.apellidos) AS nombre_completo,
        e.nombre_empleo,

        (SELECT GROUP_CONCAT(m.nombre_material SEPARATOR ', ')
         FROM ENTREGAS_INDIVIDUALES ei
         JOIN MATERIALES m ON ei.id_material = m.id_material
         WHERE ei.id_personal = p.id_personal AND ei.fecha_devolucion IS NULL
        ) AS materiales,

        (SELECT GROUP_CONCAT(a.nombre, ' (', a.numero_serie, ')')
         FROM ASIGNACION_INDIVIDUAL ai
         JOIN ARMAMENTO_INDIVIDUAL a ON ai.id_arma = a.id_arma
         WHERE ai.id_personal = p.id_personal AND ai.fecha_devolucion IS NULL
        ) AS armas_indiv,

        (SELECT GROUP_CONCAT(ar.nombre, ' (', ar.numero_serie, ') - ', ar.asignado_a)
         FROM ASIGNACION_COLECTIVO ac
         JOIN ARMAMENTO_COLECTIVO ar ON ac.id_armamento = ar.id_armamento
         WHERE ac.id_personal = p.id_personal
        ) AS armas_colect

    FROM PERSONAS p
    JOIN EMPLEOS e ON p.empleo_id = e.id_empleo
    $where
    ORDER BY e.id_empleo, p.apellidos
    LIMIT $por_pagina OFFSET $offset
";

$stmt = $conexion->prepare($sql);
if ($params) $stmt->bind_param($param_types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();
$asignaciones = $resultado->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de asignaciones</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<div class="flex-wrapper">
    <aside class="filtros">
        <h3>🎛️ Filtros</h3>
        <form method="GET">
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">

            <label>Empleo:</label>
            <select name="empleo">
                <option value="">-- Todos --</option>
                <?php foreach ($empleos as $emp): ?>
                    <?php $selected = (isset($_GET['empleo']) && $_GET['empleo'] == $emp['nombre_empleo']) ? 'selected' : ''; ?>
                    <option value="<?= $emp['nombre_empleo'] ?>" <?= $selected ?>><?= $emp['nombre_empleo'] ?></option>
                <?php endforeach; ?>
            </select>

            <label><input type="checkbox" name="solo_material" <?= isset($_GET['solo_material']) ? 'checked' : '' ?>> Solo con material</label>
            <label><input type="checkbox" name="solo_individual" <?= isset($_GET['solo_individual']) ? 'checked' : '' ?>> Solo con arma individual</label>
            <label><input type="checkbox" name="solo_colectivo" <?= isset($_GET['solo_colectivo']) ? 'checked' : '' ?>> Solo con arma colectiva</label>

            <input type="submit" value="Aplicar">
            <a href="ver_asignaciones.php" class="boton-volver" style="display:block; margin-top:10px;">Limpiar</a>
        </form>
    </aside>

    <main class="contenedor">
        <h2>📋 Asignaciones activas por militar</h2>

        <table>
            <tr>
                <th>Militar</th>
                <th>Empleo</th>
                <th>Material</th>
                <th>Arma individual</th>
                <th>Arma colectiva</th>
            </tr>

            <?php foreach ($asignaciones as $a): ?>
                <?php
                    $mostrar = true;

                    // Filtrado por nombre
                    if (!empty($_GET['nombre']) && stripos($a['nombre_completo'], $_GET['nombre']) === false) {
                        $mostrar = false;
                    }

                    // Filtrado por empleo
                    if (!empty($_GET['empleo']) && $_GET['empleo'] !== $a['nombre_empleo']) {
                        $mostrar = false;
                    }

                    // Filtrado por tipo de asignación
                    if (isset($_GET['solo_material']) && empty($a['materiales'])) $mostrar = false;
                    if (isset($_GET['solo_individual']) && empty($a['armas_indiv'])) $mostrar = false;
                    if (isset($_GET['solo_colectivo']) && empty($a['armas_colect'])) $mostrar = false;
                ?>

                <?php if ($mostrar): ?>
                <tr>
                    <td><?= htmlspecialchars($a['nombre_completo']) ?></td>
                    <td><?= htmlspecialchars($a['nombre_empleo']) ?></td>
                    <td>
                        <?php
                        if (!empty($a['materiales'])) {
                            $lista = explode(',', $a['materiales']);
                            foreach ($lista as $i => $mat) {
                                echo ($i + 1) . '. ' . htmlspecialchars(trim($mat)) . '<br>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($a['armas_indiv'])) {
                            $lista = explode(',', $a['armas_indiv']);
                            foreach ($lista as $i => $arma) {
                                echo ($i + 1) . '. ' . htmlspecialchars(trim($arma)) . '<br>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($a['armas_colect'])) {
                            $lista = explode(',', $a['armas_colect']);
                            foreach ($lista as $i => $arma) {
                                echo ($i + 1) . '. ' . htmlspecialchars(trim($arma)) . '<br>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
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
</div>

<?php include 'footer.php'; ?>
</body>
</html>
