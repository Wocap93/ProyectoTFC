<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

$mensaje = '';
$color = '';

// Convertimos usuario web a correo interno
$email_usuario = strtolower($_SESSION['usuario']) . "@intranet.local";
$password = '';  // Aquí hay dos opciones:

// OPCIÓN 1 - Ideal (si guardas las contraseñas reales para dovecot en tu tabla)
$stmt = $conexion->prepare("SELECT clave_dovecot FROM USUARIOS WHERE id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();
$stmt->bind_result($clave_dovecot);
$stmt->fetch();
$stmt->close();

$password = substr($clave_dovecot, 7);  // Quitamos el "{PLAIN}" que almacenamos

// OPCIÓN 2 - Si los usuarios ya validan directamente contra dovecot real (cuentas de sistema), usarían su contraseña real aquí

// Intentamos conexión IMAP
$mailbox = imap_open("{localhost:143/imap/notls}INBOX", $email_usuario, $password);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bandeja de entrada</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<?php include 'header.php'; ?>
<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>📥 Bandeja de entrada</h2>

    <?php
    if (!$mailbox) {
        echo "<p class='mensaje-error'>❌ Error al conectar al servidor de correo.</p>";
    } else {
        $emails = imap_search($mailbox, 'ALL');

        if (!$emails) {
            echo "<p>No hay mensajes.</p>";
        } else {
            // Orden descendente (mensajes recientes primero)
            rsort($emails);

            echo "<table>";
            echo "<tr><th>Asunto</th><th>Remitente</th><th>Fecha</th></tr>";

            foreach ($emails as $email_number) {
                $overview = imap_fetch_overview($mailbox, $email_number, 0)[0];

                echo "<tr>";
                echo "<td>" . htmlspecialchars($overview->subject) . "</td>";
                echo "<td>" . htmlspecialchars($overview->from) . "</td>";
                echo "<td>" . htmlspecialchars($overview->date) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        imap_close($mailbox);
    }
    ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="enviar_mensaje.php" class="boton-volver">✉️ Redactar nuevo mensaje</a><br><br>
        <a href="index.php" class="boton-volver">⬅️ Volver al menú principal</a>
    </div>

</main>

<?php include 'footer.php'; ?>
</body>
</html>
