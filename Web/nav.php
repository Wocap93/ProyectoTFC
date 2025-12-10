<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<nav>
    <ul class="nav-links">
        <li><a href="index.php">🏠 Inicio</a></li>
            <li><a href="ver_personal.php">🧍 Ver personal</a></li>
<?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'oficina'])): ?>
    <li><a href="alta_militar.php">➕ Alta militar</a></li>
<?php endif; ?>
        <li><a href="ver_asignaciones.php">📋 Ver asignaciones</a></li>
        <li><a href="ver_materiales.php">📦 Ver materiales</a></li>
<?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'furriel'])): ?>
    <li><a href="asignar_material.php">🎒 Asignar Material</a></li>
    <li><a href="gestionar_materiales.php">🛠️ Gestionar materiales</a></li>
<?php endif; ?>
        <li><a href="ver_armamento.php">🔍 Ver armamento</a></li>

<?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'armero'])): ?>
    <li><a href="añadir_armamento.php">➕ Añadir armamento</a></li>
    <li><a href="asignar_arma_individual.php">🔫 Arma individual</a></li>
    <li><a href="asignar_arma_colectiva.php">🧨 Arma colectiva</a></li>
<?php endif; ?>
    </ul>
    
</nav>
