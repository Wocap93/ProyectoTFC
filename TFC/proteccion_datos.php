<?php include 'seguridad.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Protección de Datos y Seguridad</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<header>
    <h2>Protección de Datos / Aviso Legal</h2>
</header>

<main class="contenedor">
    
    <p class="aviso" style="color: #f44336; font-weight:bold;">
        ⚠️ Acceso restringido. Toda actividad es monitorizada y puede ser auditada.
    </p>

    <h3>Normativa aplicable</h3>
    <ul>
        <li>RGPD – Reglamento (UE) 2016/679</li>
        <li>LOPD-GDD – Ley Orgánica 3/2018</li>
        <li>ENS – Esquema Nacional de Seguridad</li>
    </ul>

    <h3>Finalidad</h3>
    <p>
        Gestión interna del cuartel: control de armas, material y personal autorizado.
    </p>

    <h3>Seguridad del acceso</h3>
    <ul>
        <li>Autenticación con Directorio Activo</li>
        <li>Restricción total a intranet militar</li>
        <li>Roles según destino y función</li>
        <li>Operaciones registradas en auditoría</li>
    </ul>

    <h3>Datos tratados</h3>
    <p>
        Identificación básica, rol dentro del cuartel, UID de tarjeta NFC (si procede)
        y registros de acceso con fines de seguridad.
    </p>

    <h3>Contraseñas</h3>
    <p>
        No se almacenan en el sistema. Se validan en el servidor de Directorio Activo,
        siguiendo la política de seguridad vigente (longitud mínima, caducidad, bloqueo
        por intentos fallidos, etc.).
    </p>

    <h3>Responsable</h3>
    <p>
        Administrador TIC del Cuartel. <br>
        Para cualquier incidencia, contacte con Oficina de Sistemas.
    </p>

</main>

<footer>
    <a href="index.php">Volver</a>
</footer>

</body>
</html>

