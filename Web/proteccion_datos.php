<?php 
include 'seguridad.php'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Protección de Datos</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="contenedor">

<h2>🔒 Política de Protección de Datos y Seguridad de la Información</h2>

<p class="aviso" style="color:#e53935;font-weight:bold;">
⚠️ Sistema de uso exclusivo para personal militar autorizado. Toda actividad es monitorizada y puede ser auditada.
</p>

<h3>Marco normativo aplicable</h3>
<p>
Este sistema cumple con el Reglamento (UE) 2016/679 de Protección de Datos (RGPD), la Ley Orgánica 3/2018 (LOPD-GDD), el Real Decreto 311/2022 por el que se regula el Esquema Nacional de Seguridad (ENS), así como normativa interna del Ministerio de Defensa en materia de seguridad y protección de datos.
</p>

<h3>Principios del tratamiento</h3>
<p>
El tratamiento se rige por los principios del artículo 5 del RGPD: licitud del tratamiento, lealtad y transparencia, limitación de la finalidad, minimización de datos, limitación del plazo de conservación y confidencialidad. Asimismo, se adoptan las medidas técnicas y organizativas establecidas en el artículo 32 del RGPD para garantizar la integridad, disponibilidad y seguridad de la información, bajo el principio de responsabilidad proactiva.
</p>

<h3>Finalidad</h3>
<p>
Los datos se emplean únicamente para controlar el acceso al sistema, gestionar el armamento individual y colectivo, administrar el material logístico y asegurar la trazabilidad de las acciones realizadas, con el fin de garantizar la operatividad y seguridad del cuartel.
Cualquier uso distinto queda prohibido.
</p>

<h3>Legitimación</h3>
<p>
El tratamiento se fundamenta en el interés público esencial vinculado a la defensa nacional (art. 6.1.e RGPD) y en la ejecución de funciones propias de la autoridad militar, sin necesidad de consentimiento expreso del usuario.
</p>

<h3>Datos tratados</h3>
<p>
Se tratan datos identificativos del personal (usuario de dominio, nombre y apellidos), datos organizativos (empleo militar, destino y permisos asignados) y datos de seguridad (registros de acceso, UID de tarjetas NFC).
Las contraseñas no se almacenan en esta aplicación y son verificadas directamente contra el Directorio Activo mediante autenticación LDAP.
</p>

<h3>Medidas de seguridad</h3>
<p>
El sistema aplica controles de acceso basados en roles siguiendo el principio de mínimo privilegio. Se utiliza autenticación robusta con servicios de Directorio Activo, auditoría de actividad crítica, backup interno seguro y aislamiento del entorno en la intranet militar. La infraestructura se encuentra alineada con el Esquema Nacional de Seguridad en categoría Media.
</p>

<h3>Conservación</h3>
<p>
Los datos se conservan durante el tiempo estrictamente necesario para cumplir la finalidad operativa. Los registros de seguridad se mantendrán durante el periodo legalmente establecido para auditoría militar.
</p>

<h3>Cesión y comunicación de datos</h3>
<p>
Los datos no se comunican a terceros ni se realizan transferencias internacionales. Toda la información permanece bajo custodia del Ministerio de Defensa.
</p>

<h3>Derechos de los usuarios</h3>
<p>
El personal podrá ejercer los derechos de acceso y rectificación a través de la Oficina de Sistemas del cuartel. No es aplicable la supresión de datos cuando estos deban conservarse para fines de seguridad, auditoría o defensa nacional, de acuerdo con la legislación vigente.
</p>

<h3>Responsable del Tratamiento</h3>
<p>
Administrador TIC del Cuartel, actuando bajo la autoridad militar correspondiente.  
Contacto interno: Oficina de Sistemas.
</p>

<p class="info">
La Política completa de Protección de Datos está disponible en la Memoria Técnica del sistema.
</p>
<div style="margin-top: 20px;">
    <a href="index.php" 
       style="display:inline-block; padding:10px 20px; background-color:#4CAF50; color:white; 
              text-decoration:none; border-radius:8px; font-weight:bold; box-shadow:0 2px 4px #00000055;">
        ⬅️ Volver al menú
    </a>
</div>

</main>

<?php include 'footer.php'; ?>

</body>
</html>

