<?php
session_start();

// Si ya está logueado, lo mandamos al índice
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

/* ======== CONFIGURACIÓN LDAP (Windows Server) ======== */
/* Cambiado a LDAPS porque es más rápido y estable */
$ldap_host   = "ldaps://Servidor.intranet.local:636";
$ldap_domain = "INTRANET";   // INTRANET\\usuario
$ldap_fqdn   = "intranet.local";

/* Aceptar certificados no válidos (entorno local / XAMPP) */
ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

/* ======== CONFIGURACIÓN MySQL (XAMPP) ======== */
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "FURRI_CUARTEL";

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $mensaje = "❌ Debes introducir usuario y contraseña.";
    } else {

        // 1️⃣ Conectar a LDAP (LDAPS)
        $ldapconn = @ldap_connect($ldap_host);

        if (!$ldapconn) {
            $mensaje = "❌ No se pudo conectar al servidor LDAP.";
        } else {

            /* ======== OPCIONES LDAP PARA VELOCIDAD ======== */
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

            // Timeouts para evitar colgados largos
            ldap_set_option($ldapconn, LDAP_OPT_NETWORK_TIMEOUT, 2);
            ldap_set_option($ldapconn, LDAP_OPT_TIMELIMIT, 2);
            ldap_set_option($ldapconn, LDAP_OPT_CONNECT_TIMEOUT, 2);

            /* ======== FORMATO DE USUARIO ======== */
            // Opción 1: INTRANET\usuario
            $ldaprdn = $ldap_domain . "\\" . $usuario;

            /* ======== VALIDACIÓN CONTRA AD ======== */
            if (@ldap_bind($ldapconn, $ldaprdn, $password)) {

                // VALIDADO en AD → buscar rol en BD
                $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

                if ($mysqli->connect_errno) {
                    $mensaje = "❌ Error de conexión a MySQL.";
                } else {

                    $stmt = $mysqli->prepare("SELECT rol FROM USUARIOS WHERE nombre_usuario = ?");
                    $stmt->bind_param("s", $usuario);
                    $stmt->execute();
                    $stmt->bind_result($rol);
                    $stmt->fetch();
                    $stmt->close();
                    $mysqli->close();

                    if ($rol) {
                        // Todo OK → crear sesión
                        $_SESSION['usuario'] = $usuario;
                        $_SESSION['rol']     = $rol;

                        ldap_unbind($ldapconn);

                        // Redirigir
                        header('Location: index.php');
                        exit;

                    } else {
                        $mensaje = "⚠️ Usuario válido en AD, pero no registrado en la intranet.";
                    }
                }

            } else {
                $mensaje = "❌ Usuario o contraseña incorrectos en Active Directory.";
            }

            ldap_unbind($ldapconn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login intranet</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<?php include 'header.php'; ?>
<main class="contenedor">
    <h2>🔐 Acceso a la intranet</h2>

    <?php if ($mensaje): ?>
        <p class="mensaje-error"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Usuario</label>
        <input type="text" name="usuario" required>

        <label>Contraseña</label>
        <input type="password" name="password" required>

        <input type="submit" value="Iniciar sesión">
    </form>
</main>
<?php include 'footer.php'; ?>
</body>
</html>

