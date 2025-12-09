<?php
// error_mantenimiento.php
// Página genérica de error / mantenimiento

// Código HTTP para mantenimiento
http_response_code(503);

// Nunca mostrar errores aquí
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema en mantenimiento</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php
// ❗ OJO: aquí NO se incluye seguridad.php
// Para mantener la estructura de la web:
include 'header.php';
if (file_exists('nav.php')) {

}
?>

<main class="contenedor">
    <div class="tarjeta-mantenimiento">
        <h2>🛠 Sistema en mantenimiento</h2>
        <p>En este momento el sistema de gestión de la Furri del Cuartel no está disponible.</p>
        <p>Estamos realizando tareas de mantenimiento o se ha producido un error interno.</p>
        <p>Por favor, vuelve a intentarlo pasados unos minutos.</p>

        <a class="boton-volver" href="index.php">⬅ Volver al inicio</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
