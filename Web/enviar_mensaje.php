<?php

include 'seguridad.php';
include 'header.php';
include 'conexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

if (
    !isset($_SESSION['usuario']) ||
    !isset($_SESSION['password_ldap']) ||
    !isset($_SESSION['email'])
) {
    header("Location: index.php");
    exit;
}

$servidor          = 'proyecto.intranet.local';
$usuario_login     = $_SESSION['usuario'];
$usuario_password  = $_SESSION['password_ldap'];
$email_from        = $_SESSION['email'];
$rol_usuario       = ucfirst($_SESSION['rol']);

$mensaje_envio = '';
$color_envio   = 'green';

// Campos para reenviar
$destino  = $_GET['destino'] ?? '';
$asunto   = $_GET['asunto'] ?? '';
$mensaje_original = '';

if (isset($_GET['reenviar']) && isset($_SESSION['mensaje_reenviar'])) {
    $mensaje_original = "--- Mensaje reenviado ---\n" . $_SESSION['mensaje_reenviar'];
}

// ==============================
//  ENVÍO DEL CORREO
// ==============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $destino  = trim($_POST['destino'] ?? '');
    $asunto   = trim($_POST['asunto'] ?? '');
    $mensaje  = trim($_POST['mensaje'] ?? '');

    $cuerpo   = $mensaje . "\n\n Enviado por: " . $usuario_login;

    $mail = new PHPMailer(true);

    try {
        // 🔌 Usar Postfix local SIN cifrado (intranet)
        $mail->isSMTP();
        $mail->Host        = 'proyecto.intranet.local'; // o 127.0.0.1 si lo prefieres
        $mail->Port        = 25;
        $mail->SMTPAuth    = false;

        // ❌ NADA de TLS ni SSL en intranet local
        $mail->SMTPSecure  = false;
        $mail->SMTPAutoTLS = false;

        // (Opcional, por si acaso PHPMailer insiste en mirar el cert)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->setFrom($email_from, $rol_usuario);

        if (empty($destino)) {
            throw new Exception("Dirección de destino vacía");
        }
        $mail->addAddress($destino);

        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;
        if (
            isset($_FILES['adjunto']) &&
            $_FILES['adjunto']['error'] == UPLOAD_ERR_OK
        ) {
            $mail->addAttachment($_FILES['adjunto']['tmp_name'], basename($_FILES['adjunto']['name']));
        }

        $mail->send();
        $mensaje_envio = "✅ Mensaje enviado correctamente.";
        unset($_SESSION['mensaje_reenviar']);

    } catch (Exception $e) {
        $mensaje_envio = "❌ Error: {$e->getMessage()}";
        $color_envio   = 'red';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Correo Intranet</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contenedor">
    <h2>✉️ Correo Intranet</h2>

    <div style="text-align:center;margin-bottom:10px;">
        <a href="bandeja.php" class="boton-accion">📥 Ver bandeja de entrada</a><br><br><br>
    </div>

    <div class="contenedor-secundario">
        <h3>📤 Enviar mensaje</h3>

        <?php if ($mensaje_envio): ?>
            <p class="<?= $color_envio === 'green' ? 'mensaje-exito' : 'mensaje-error' ?>">
                <?= $mensaje_envio ?>
            </p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="text" name="destino"
                   value="<?= htmlspecialchars($destino) ?>"
                   placeholder="Destinatario"
                   required>

            <input type="text" name="asunto"
                   value="<?= htmlspecialchars($asunto) ?>"
                   placeholder="Asunto"
                   required>

            <textarea name="mensaje" rows="5" required><?= htmlspecialchars($mensaje_original) ?></textarea>

            <input type="file" name="adjunto">
            <input type="submit" value="Enviar">
        </form>
    </div>

    <div style="text-align:center;margin-top:20px;">
        <a href="index.php" class="boton-volver">⬅️ Volver al menú</a>
    </div>
</main>

</body>
</html>

