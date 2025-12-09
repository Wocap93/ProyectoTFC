<?php
// Siempre lo primero: seguridad (ya arranca sesión y cabeceras)
include 'seguridad.php';

// Si no hay sesión de usuario, fuera
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

/* ======== CONFIG LDAP ======== */

$LDAP_HOST_SSL  = "ldaps://192.168.100.10:636";  // IP/host del DC con LDAPS
$BASE_DN        = "DC=intranet,DC=local";
$DOMINIO        = "INTRANET";

$LDAP_ADMIN_USER = "INTRANET\\Administrador";
$LDAP_ADMIN_PASS = "Patata!2";  // <<< CAMBIA ESTO SI HACE FALTA

// No exigir certificado en entorno local
ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

$msg     = "";
$tipoMsg = "error"; // error | ok | aviso
$usuario = $_SESSION['usuario'];

/* ======== POST: CAMBIO DE CONTRASEÑA ======== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $modo    = $_POST['modo'] ?? 'todo'; // todo | dominio | correo
    $newpwd  = trim($_POST['newpwd']  ?? '');
    $newpwd2 = trim($_POST['newpwd2'] ?? '');

    if ($newpwd === '' || $newpwd2 === '') {
        $msg = "❌ Debes rellenar ambos campos.";
        $tipoMsg = "error";
    } elseif ($newpwd !== $newpwd2) {
        $msg = "❌ Las contraseñas nuevas no coinciden.";
        $tipoMsg = "error";
    } else {

        /* ==========================================
           MODO 1: SOLO CORREO (Linux + BD + sesión)
           NO toca Active Directory
           ========================================== */
        if ($modo === 'correo') {

            // 1) Actualizar usuario de sistema (para Dovecot)
            $usuario_sanit = escapeshellarg($usuario);
            $pwd_sanit     = escapeshellarg($newpwd);

            // Ajusta la ruta si which chpasswd te da otra
            $cmd_passwd = "echo {$usuario_sanit}:{$pwd_sanit} | sudo /usr/sbin/chpasswd 2>&1";

            $outU = [];
            $retU = 0;
            exec($cmd_passwd, $outU, $retU);

            if ($retU !== 0) {
                $msg = "❌ Error actualizando la contraseña del correo en el servidor. Contacta con el administrador.";
                $tipoMsg = "error";
                error_log("Error al ejecutar chpasswd para $usuario (modo correo): codigo=$retU salida=" . implode(" | ", $outU));
            } else {
                // 2) Actualizar BD (clave_dovecot)
                try {
                    $pdo = new PDO(
                        "mysql:host=localhost;dbname=FURRI_CUARTEL;charset=utf8mb4",
                        "root",
                        "",
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );

                    $stmt = $pdo->prepare(
                        "UPDATE USUARIOS SET clave_dovecot = :c WHERE nombre_usuario = :u"
                    );
                    $stmt->execute([
                        ':c' => '{PLAIN}' . $newpwd,
                        ':u' => $usuario
                    ]);

                    // 3) Actualizar también la sesión para IMAP
                    $_SESSION['password_ldap'] = $newpwd;

                    $msg     = "✅ Contraseña del CORREO actualizada correctamente. Tu contraseña del dominio de Windows NO ha cambiado.";
                    $tipoMsg = "ok";

                } catch (Throwable $e) {
                    $msg = "⚠️ El servidor de correo se ha actualizado, pero hubo un problema al guardar el cambio en la base de datos.";
                    $tipoMsg = "aviso";
                    error_log("Error BD cambiando clave_dovecot para $usuario: " . $e->getMessage());
                }
            }
        }

        /* ==========================================
           MODO 2: SOLO DOMINIO (AD)
           NO toca Linux ni BD ni Dovecot
           ========================================== */
        elseif ($modo === 'dominio') {

            // 1) Conectar a LDAP (LDAPS)
            $dc = @ldap_connect($LDAP_HOST_SSL);
            if (!$dc) {
                $msg = "❌ Error conectando a LDAP (LDAPS).";
                $tipoMsg = "error";
            } else {
                ldap_set_option($dc, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($dc, LDAP_OPT_REFERRALS, 0);

                // 2) Bind como Administrador (cuenta de servicio)
                if (!@ldap_bind($dc, $LDAP_ADMIN_USER, $LDAP_ADMIN_PASS)) {

                    $diag = '';
                    ldap_get_option($dc, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
                    $msg = "❌ No se pudo autenticar la cuenta de servicio LDAP (administrador).<br>Detalle: " .
                           htmlspecialchars($diag);
                    $tipoMsg = "error";

                } else {

                    // 3) Buscar DN del usuario (por sAMAccountName)
                    $filtro = "(sAMAccountName=" . ldap_escape($usuario, '', LDAP_ESCAPE_FILTER) . ")";
                    $sr     = @ldap_search($dc, $BASE_DN, $filtro, ["distinguishedName"]);
                    $entry  = $sr ? @ldap_first_entry($dc, $sr) : null;
                    $vals   = $entry ? @ldap_get_values($dc, $entry, "distinguishedName") : null;
                    $userDn = ($vals && $vals["count"] > 0) ? $vals[0] : null;

                    if (!$userDn) {
                        $msg = "❌ Usuario no encontrado en el dominio.";
                        $tipoMsg = "error";
                    } else {

                        // 4) Preparar nueva contraseña en UTF-16LE con comillas
                        $encNew = iconv('UTF-8', 'UTF-16LE', '"' . $newpwd . '"');

                        // 5) Aplicar cambio en AD
                        $ok = @ldap_mod_replace($dc, $userDn, ["unicodePwd" => $encNew]);

                        if (!$ok) {
                            $diag = '';
                            ldap_get_option($dc, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
                            $msg = "❌ Error al cambiar la contraseña en AD: " . htmlspecialchars($diag);
                            $tipoMsg = "error";
                        } else {

                            // Solo dominio -> forzamos re-login
                            session_unset();
                            session_destroy();
                            header('Location: index.php?pwdchanged=1');
                            exit;
                        }
                    }
                }
            }
        }

        /* ==========================================
           MODO 3: TODO (Dominio + Linux + BD)
           ⇨ Lo que tenías al principio pero bien organizado
           ========================================== */
        elseif ($modo === 'todo') {

            // 1) Conectar a LDAP (LDAPS)
            $dc = @ldap_connect($LDAP_HOST_SSL);
            if (!$dc) {
                $msg = "❌ Error conectando a LDAP (LDAPS).";
                $tipoMsg = "error";
            } else {
                ldap_set_option($dc, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($dc, LDAP_OPT_REFERRALS, 0);

                // 2) Bind como Administrador (cuenta de servicio)
                if (!@ldap_bind($dc, $LDAP_ADMIN_USER, $LDAP_ADMIN_PASS)) {

                    $diag = '';
                    ldap_get_option($dc, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
                    $msg = "❌ No se pudo autenticar la cuenta de servicio LDAP (administrador).<br>Detalle: " .
                           htmlspecialchars($diag);
                    $tipoMsg = "error";

                } else {

                    // 3) Buscar DN del usuario (por sAMAccountName)
                    $filtro = "(sAMAccountName=" . ldap_escape($usuario, '', LDAP_ESCAPE_FILTER) . ")";
                    $sr     = @ldap_search($dc, $BASE_DN, $filtro, ["distinguishedName"]);
                    $entry  = $sr ? @ldap_first_entry($dc, $sr) : null;
                    $vals   = $entry ? @ldap_get_values($dc, $entry, "distinguishedName") : null;
                    $userDn = ($vals && $vals["count"] > 0) ? $vals[0] : null;

                    if (!$userDn) {
                        $msg = "❌ Usuario no encontrado en el dominio.";
                        $tipoMsg = "error";
                    } else {

                        // 4) Preparar nueva contraseña en UTF-16LE con comillas
                        $encNew = iconv('UTF-8', 'UTF-16LE', '"' . $newpwd . '"');

                        // 5) Aplicar cambio en AD
                        $ok = @ldap_mod_replace($dc, $userDn, ["unicodePwd" => $encNew]);

                        if (!$ok) {
                            $diag = '';
                            ldap_get_option($dc, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
                            $msg = "❌ Error al cambiar la contraseña en AD: " . htmlspecialchars($diag);
                            $tipoMsg = "error";
                        } else {

                            // AD OK, ahora intentamos sincronizar Linux + BD
                            $ok_linux = false;
                            $ok_bd    = false;

                            // 6) Linux (para Dovecot)
                            $usuario_sanit = escapeshellarg($usuario);
                            $pwd_sanit     = escapeshellarg($newpwd);

                            $cmd_passwd = "echo {$usuario_sanit}:{$pwd_sanit} | sudo /usr/sbin/chpasswd 2>&1";
                            $outU = [];
                            $retU = 0;
                            exec($cmd_passwd, $outU, $retU);

                            if ($retU !== 0) {
                                error_log("Error al ejecutar chpasswd para $usuario (modo todo): codigo=$retU salida=" . implode(" | ", $outU));
                            } else {
                                $ok_linux = true;
                            }

                            // 7) BD (clave_dovecot)
                            try {
                                $pdo = new PDO(
                                    "mysql:host=localhost;dbname=FURRI_CUARTEL;charset=utf8mb4",
                                    "root",
                                    "",
                                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                                );

                                $stmt = $pdo->prepare(
                                    "UPDATE USUARIOS SET clave_dovecot = :c WHERE nombre_usuario = :u"
                                );
                                $stmt->execute([
                                    ':c' => '{PLAIN}' . $newpwd,
                                    ':u' => $usuario
                                ]);

                                $ok_bd = true;

                            } catch (Throwable $e) {
                                error_log("Error BD cambiando clave_dovecot (modo todo) para $usuario: " . $e->getMessage());
                            }

                            if ($ok_linux && $ok_bd) {
                                // Todo OK -> cerrar sesión y volver al login
                                session_unset();
                                session_destroy();
                                header('Location: index.php?pwdchanged=1');
                                exit;
                            } else {
                                // AD está bien, pero algo falló en Linux o BD
                                $msg = "⚠️ La contraseña se ha cambiado correctamente en el dominio, "
                                     . "pero ha habido un problema al sincronizar el servidor de correo. "
                                     . "Contacta con el administrador o usa el modo 'Solo correo' para reintentar.";
                                $tipoMsg = "aviso";
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar contraseña</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<?php include 'header.php'; ?>
<?php include 'nav.php'; ?>

<main class="contenedor">

    <h2>🔐 Cambio de contraseña</h2>

    <?php if ($msg): ?>
        <?php
        $clase = 'mensaje-error';
        if ($tipoMsg === 'ok')    $clase = 'mensaje-ok';
        if ($tipoMsg === 'aviso') $clase = 'mensaje-aviso';
        ?>
        <p class="<?= $clase ?>"><?= $msg ?></p>
    <?php endif; ?>

    <form method="POST" id="formPwd">

        <h3>¿Qué quieres cambiar?</h3>

        <label style="display:block; margin-bottom:6px;">
            <input type="radio" name="modo" value="todo" checked>
             <strong>Todo</strong> (Dominio Windows + Linux + Correo)
        </label>
        <small style="display:block;margin-bottom:10px;">
            Cambia tu contraseña en el dominio y la sincroniza con el servidor Linux y el correo IMAP.
            Se cerrará tu sesión y tendrás que iniciar sesión de nuevo con la nueva contraseña.
        </small>

        <label style="display:block; margin-bottom:6px;">
            <input type="radio" name="modo" value="dominio">
             <strong>Solo contraseña del DOMINIO</strong> (Windows / Active Directory)
        </label>
        <small style="display:block;margin-bottom:10px;">
            Cambia solo tu contraseña de dominio (la que usas en los equipos Windows).
            No modifica la contraseña del correo. Se cerrará tu sesión y tendrás que entrar de nuevo.
        </small>

        <label style="display:block; margin-bottom:6px;">
            <input type="radio" name="modo" value="correo">
             <strong>Solo contraseña del CORREO</strong> (IMAP / servidor Linux)
        </label>
        <small style="display:block;margin-bottom:15px;">
            Usa esta opción si has cambiado la contraseña en Windows y ahora el correo de la intranet no funciona.
            La contraseña del dominio NO se modifica.
        </small>

        <hr style="margin:15px 0;">

        <label>Nueva contraseña</label>
        <input name="newpwd" type="password" required>

        <label>Repite la nueva contraseña</label>
        <input name="newpwd2" type="password" required>

        <input type="submit" value="Cambiar ahora">
    </form>

</main>

<?php include 'footer.php'; ?>

</body>
</html>

