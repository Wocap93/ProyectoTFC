<?php
$conexion = new mysqli("localhost", "root", "", "FURRI_CUARTEL");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>

