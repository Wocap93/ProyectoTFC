<?php
include 'seguridad.php';
include 'conexion.php';

// === RATE LIMIT ===
const MAX_INTENTOS = 3;
const BLOQUEO_SEG  = 10;

if (!isset($_SESSION['login_rl'])) {
    $_SESSION['login_rl'] = [
        'intentos'         => 0,
        'ultimo_ts'        => 0,
        'bloqueado_hasta'  => 0,
        'ultimo_user'      => null,
    ];
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$ahora = time();
$bloqueado = $ahora < ($_SESSION['login_rl']['bloqueado_hasta'] ?? 0);
$seg_restantes = max(0, ($_SESSION['login_rl']['bloqueado_hasta'] ?? 0) - $ahora);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    if ($bloqueado) {
        // No mostramos error aquí para evitar duplicado, lo gestionará el HTML
    } else {

        $usuario = trim($_POST['usuario'] ?? '');
        $clave   = $_POST['clave'] ?? '';

        // 1️⃣ Validación en AD
        $ldap_host = "ldap://192.168.100.10";
        $ldapconn  = @ldap_connect($ldap_host);

        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ldapconn, LDAP_OPT_NETWORK_TIMEOUT, 2);
        ldap_set_option($ldapconn, LDAP_OPT_TIMELIMIT, 2);

        $ldaprdn = "INTRANET\\" . $usuario;

        if (@ldap_bind($ldapconn, $ldaprdn, $clave)) {

            // Resetear rate-limit
            $_SESSION['login_rl'] = [
                'intentos'         => 0,
                'ultimo_ts'        => 0,
                'bloqueado_hasta'  => 0,
                'ultimo_user'      => $usuario,
            ];

            // 2️⃣ Buscar rol en MySQL
            $stmt = $conexion->prepare("SELECT id_usuario, rol, email FROM USUARIOS WHERE nombre_usuario = ?");
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->bind_result($id_usuario, $rol, $email);

            if ($stmt->fetch()) {

                session_regenerate_id(true);

                $_SESSION['usuario']       = $usuario;
                $_SESSION['id_usuario']    = $id_usuario;
                $_SESSION['rol']           = $rol;
                $_SESSION['email']         = $email;
                $_SESSION['password_ldap'] = $clave;   // ← necesaria para IMAP

                $stmt->close();
                ldap_unbind($ldapconn);

                header('Location: index.php');
                exit;

            } else {
                $error = "⚠ Usuario válido en dominio, pero sin rol en la base de datos.";
            }

            $stmt->close();

        } else {

            // ❌ Credenciales incorrectas
            $_SESSION['login_rl']['ultimo_user'] = $usuario;
            $_SESSION['login_rl']['ultimo_ts']   = $ahora;
            $_SESSION['login_rl']['intentos']++;

            if ($_SESSION['login_rl']['intentos'] >= MAX_INTENTOS) {
                $_SESSION['login_rl']['bloqueado_hasta'] = $ahora + BLOQUEO_SEG;
            } 
        }

        ldap_unbind($ldapconn);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Furri del Cuartel</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="contenedor">
<?php if (!isset($_SESSION['usuario'])): ?>

    <div class="form-login">
        <h2>🔐 Acceso al sistema</h2>

<?php
$ahora = time();
$bloqueado = $ahora < ($_SESSION['login_rl']['bloqueado_hasta'] ?? 0);
$seg_restantes = max(0, ($_SESSION['login_rl']['bloqueado_hasta'] ?? 0) - $ahora);

// Mensaje de intento fallido (solo si no está bloqueado)
if ($_SESSION['login_rl']['intentos'] > 0 && !$bloqueado) {
    $restan = MAX_INTENTOS - $_SESSION['login_rl']['intentos'];
    if ($restan > 0) {
        echo "<p class='mensaje-error'>❌ Intento fallido. Intentos restantes: {$restan}</p>";
    }
}

// Mensaje de bloqueo con cuenta atrás (sin duplicados)
if ($bloqueado) {
    header("Refresh: 1");
    echo "<p class='mensaje-error'>⏳ Demasiados intentos. Espera {$seg_restantes}s…</p>";
}

// Mostrar solo errores importantes
if (isset($error) && !$bloqueado && $_SESSION['login_rl']['intentos'] === 0) {
    echo "<p class='mensaje-error'>" . htmlspecialchars($error) . "</p>";
}
?>

        <form method="POST" autocomplete="off">
            <label>Usuario:</label>
            <input type="text" name="usuario" required <?= $bloqueado ? 'disabled' : '' ?>>

            <label>Contraseña:</label>
            <input type="password" name="clave" required <?= $bloqueado ? 'disabled' : '' ?>>

            <input type="submit" name="login" value="Entrar" <?= $bloqueado ? 'disabled' : '' ?>>
        </form>

    </div>

<?php else: ?>

    <h2>📋 Menú principal</h2>
    <p>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?>.</p>

    <ul class="menu">
        <li><a href="ver_personal.php">🧍 Ver personal</a></li>
        <?php if (in_array($_SESSION['rol'], ['admin', 'oficina'])): ?>
            <li><a href="alta_militar.php">➕ Alta militar</a></li>
        <?php endif; ?>
        <li><a href="ver_asignaciones.php">📋 Ver asignaciones</a></li>
        <li><a href="ver_materiales.php">📦 Ver materiales</a></li>
        <?php if (in_array($_SESSION['rol'], ['admin', 'furriel'])): ?>
            <li><a href="asignar_material.php">🎒 Asignar Material</a></li>
            <li><a href="gestionar_materiales.php">🛠 Gestionar materiales</a></li>
        <?php endif; ?>
        <li><a href="ver_armamento.php">🔍 Ver armamento</a></li>
        <?php if (in_array($_SESSION['rol'], ['admin', 'armero'])): ?>
            <li><a href="añadir_armamento.php">➕ Añadir armamento</a></li>
            <li><a href="asignar_arma_individual.php">🔫 Arma individual</a></li>
            <li><a href="asignar_arma_colectiva.php">🧨 Arma colectiva</a></li>
        <?php endif; ?>
    </ul>

<?php endif; ?>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
