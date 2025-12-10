<?php

include 'seguridad.php';
include 'header.php';
include 'conexion.php';

// Comprobación de sesión: ahora usamos password_ldap y email
if (
    !isset($_SESSION['usuario']) ||
    !isset($_SESSION['password_ldap']) ||
    !isset($_SESSION['email'])
) {
    header("Location: index.php");
    exit;
}

$servidor         = 'proyecto.intranet.local';
$usuario_login    = $_SESSION['usuario'];
$usuario_password = $_SESSION['password_ldap'];  // ← contraseña del dominio

// Verificar si hay ID
if (!isset($_GET['id'])) {
    header("Location: enviar_mensaje.php");
    exit;
}

$id_correo = intval($_GET['id']);

// Abrir bandeja IMAP en 993/SSL (como en el resto del proyecto)
ini_set('imap.enable_insecure_auth', 1);

$mailbox = @imap_open(
    '{proyecto.intranet.local:143/imap/notls}INBOX',
    $usuario_login,
    $usuario_password
);


if (!$mailbox) {
    die('<div class="mensaje-error">❌ Error al conectar IMAP: ' . imap_last_error() . '</div>');
}

// Obtener cabecera y cuerpo
$overview = imap_fetch_overview($mailbox, $id_correo, 0)[0];

// Intentamos cuerpo de texto plano (parte 1)
$mensaje = imap_fetchbody($mailbox, $id_correo, 1);
if ($mensaje === '') {
    // A veces el texto va en otra parte; probamos con 1.1 (multipart/alternative)
    $mensaje = imap_fetchbody($mailbox, $id_correo, '1.1');
}

// Procesar adjuntos
$estruct     = imap_fetchstructure($mailbox, $id_correo);
$attachments = [];

if (isset($estruct->parts) && count($estruct->parts)) {
    for ($i = 0; $i < count($estruct->parts); $i++) {
        $att = $estruct->parts[$i];

        if ($att->ifdparameters) {
            foreach ($att->dparameters as $object) {
                if (strtolower($object->attribute) == 'filename') {
                    $filename        = $object->value;
                    $attachment_body = imap_fetchbody($mailbox, $id_correo, $i + 1);

                    if ($att->encoding == 3) {         // BASE64
                        $attachment_body = base64_decode($attachment_body);
                    } elseif ($att->encoding == 4) {   // QUOTED-PRINTABLE
                        $attachment_body = quoted_printable_decode($attachment_body);
                    }

                    // Carpeta de adjuntos (asegúrate de que existe y tiene permisos)
                    $filepath = 'adjuntos/' . $filename;
                    file_put_contents($filepath, $attachment_body);
                    $attachments[] = $filepath;
                }
            }
        }
    }
}

imap_close($mailbox);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver correo</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>📄 Detalle del correo</h2>

    <p><strong>Asunto:</strong> <?= htmlspecialchars($overview->subject ?? '') ?></p>
    <p><strong>De:</strong> <?= htmlspecialchars($overview->from ?? '') ?></p>
    <p><strong>Fecha:</strong> <?= htmlspecialchars($overview->date ?? '') ?></p>
    <hr>
    <p style="white-space: pre-wrap; text-align: left;"><?= htmlspecialchars($mensaje) ?></p>

    <?php if ($attachments): ?>
        <h3>📎 Adjuntos</h3>
        <ul>
            <?php foreach ($attachments as $file): ?>
                <li>
                    <a href="descarga.php?file=<?= urlencode(basename($file)) ?>">
                        Descargar <?= htmlspecialchars(basename($file)) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No hay adjuntos.</p>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px;">
        <?php
        // Sacar solo el email del remitente (si viene en formato "Nombre <correo>")
        $from = $overview->from ?? '';
        preg_match('/<(.+)>/', $from, $matches);
        $solo_email = isset($matches[1]) ? $matches[1] : $from;

        // Guardar mensaje en la sesión para reenviar
        $_SESSION['mensaje_reenviar'] = $mensaje;
        ?>
        <a href="enviar_mensaje.php?reenviar=1&destino=<?= urlencode($solo_email) ?>&asunto=<?= urlencode('FW: ' . ($overview->subject ?? '')) ?>"
           class="boton-accion">
            🔁 Reenviar
        </a>

        <br><br><br>
        <a href="bandeja.php" class="boton-volver">⬅️ Volver a la bandeja</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>

