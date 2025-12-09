<?php
include 'seguridad.php';
include 'header.php';
include 'conexion.php';

// SOLO si está logueado
if (!isset($_SESSION['usuario'], $_SESSION['password_ldap'], $_SESSION['email'])) {
    header("Location: index.php");
    exit;
}

/* ===============================
   DATOS DEL USUARIO
   =============================== */

$usuario_login    = $_SESSION['usuario'];
$usuario_password = $_SESSION['password_ldap']; // contraseña REAL del dominio
$email_usuario    = $_SESSION['email'];         // ej: pepe@intranet.local

/* ===============================
   ABRIR BANDEJA IMAP (993 SSL)
   =============================== */

ini_set('imap.enable_insecure_auth', 1);

// (De momento SIN timeouts agresivos, para que no corte la conexión)
$mailbox = @imap_open(
    '{proyecto.intranet.local:143/imap/notls}INBOX',
    $usuario_login,
    $usuario_password
);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bandeja de Entrada</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>📥 Bandeja de entrada</h2>

<?php
/* ===============================
   ERROR DE CONEXIÓN IMAP
   =============================== */
if (!$mailbox) {
    echo "<p class='mensaje-error'>❌ Error al conectar IMAP: " . imap_last_error() . "</p>";
    echo "<p>Revisa la contraseña o el servidor de correo.</p>";
} 
else {

    // BORRAR mensaje (lo hacemos aquí antes de listar)
    if (isset($_GET['borrar'])) {
        $msg = intval($_GET['borrar']);
        imap_delete($mailbox, $msg);
        imap_expunge($mailbox);
        imap_close($mailbox);
        header("Location: bandeja.php");
        exit;
    }

    $emails = imap_search($mailbox, 'ALL') ?: [];

    if (empty($emails)) {
        echo "<p>No hay mensajes.</p>";
    } 
    else {

        rsort($emails);

        echo "<table>";
        echo "<tr><th>Asunto</th><th>Remitente</th><th>Fecha</th><th></th></tr>";

        foreach ($emails as $num) {

    $overview = imap_fetch_overview($mailbox, $num, 0)[0];
    $subject  = htmlspecialchars($overview->subject ?? '');
    $from     = htmlspecialchars($overview->from ?? '');
    $date     = htmlspecialchars($overview->date ?? '');

    // 📍 Si NO está leído, aplicamos color especial
    $row_style = (empty($overview->seen))
        ? "background-color: #334d33;"
        : "";

    echo "<tr style='$row_style'>";
    echo "<td>$subject</td>";
    echo "<td>$from</td>";
    echo "<td>$date</td>";
    echo "<td>
            <a href='ver_correo.php?id=$num' class='boton-accion'>📄 Ver</a>
            <a href='enviar_mensaje.php?reenviar=1&id=$num' class='boton-accion'>🔁 Reenviar</a>
            <a onclick=\"return confirm('¿Borrar mensaje?')\"
               href=\"bandeja.php?borrar=$num\" 
               class='boton-accion boton-rojo'>🗑️</a>
         </td>";
    echo "</tr>";
}


        echo "</table>";
    }

    imap_close($mailbox);
}
?>

    <div style="text-align:center; margin-top:30px;">
        <a href="enviar_mensaje.php" class="boton-volver">✉️ Redactar nuevo mensaje</a><br><br>
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>

</main>

<?php include 'footer.php'; ?>
</body>
</html>

