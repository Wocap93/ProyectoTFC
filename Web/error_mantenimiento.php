<?php
http_response_code(503);
ini_set('display_errors', 0);
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema en mantenimiento</title>
    <link rel="stylesheet" href="estilo.css"> <!-- 👍 YA USA TU CSS REAL -->
</head>

<body>

<header>
    <h1>🪖 Sistema Furri del Cuartel</h1>
</header>

<main class="contenedor">
    <h2>⚠️ Sistema no disponible</h2>

    <p>Se ha producido un error interno o se están realizando labores de mantenimiento.</p>
    <p>El servicio se restaurará en breve.</p>

    <p style="opacity:0.7; margin-top:10px;">
        Incidente registrado: <?= date("Y-m-d H:i:s") ?>
    </p>

    <a href="index.php" class="boton-volver">⬅ Volver al inicio</a>
</main>

<footer>
    © 2025 ACART - Sistema Furri | Equipo 12
</footer>

</body>
</html>

