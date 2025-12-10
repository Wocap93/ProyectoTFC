<?php
// ========================
// seguridad.php (optimizado + cálculo días contraseña)
// ========================

// No mostrar errores al usuario, pero sí loguearlos
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ================================
// MANEJADOR GLOBAL DE ERRORES (500)
// ================================
register_shutdown_function(function () {
    $error = error_get_last();
    $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error && in_array($error['type'], $fatales, true)) {

        // Guardar log exacto del error
        error_log("ERROR FATAL: " . print_r($error, true));

        // Evitar bucle
        if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'error_mantenimiento.php') {
            return;
        }

        // Limpia cualquier salida previa para evitar pantalla blanca
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Código HTTP adecuado
        http_response_code(500);

        // Mostrar página mantenimiento SIEMPRE
        include __DIR__ . '/error_mantenimiento.php';

        exit;
    }
});


// ================================
// COOKIES + SESIÓN
// ================================
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================
// PÁGINAS QUE NO REQUIEREN LOGIN
// ================================
$PUBLICAS = [
    'index.php',
    'error_mantenimiento.php',
    'cambiar_password.php'
];

$script = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (!in_array($script, $PUBLICAS, true) && !isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// ================================
// CABECERAS DE SEGURIDAD
// ================================
if (!headers_sent()) {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: same-origin");
}

// ================================
// INACTIVIDAD
// ================================
$TIEMPO_INACTIVIDAD = 600;

if (isset($_SESSION['ultimo_actividad'])) {
    if (time() - $_SESSION['ultimo_actividad'] > $TIEMPO_INACTIVIDAD) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
}

$_SESSION['ultimo_actividad'] = time();

// ================================
// SANITIZAR VALORES DE SESIÓN
// ================================
if (isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', $_SESSION['usuario']);
}

if (isset($_SESSION['email'])) {
    $_SESSION['email'] = filter_var($_SESSION['email'], FILTER_SANITIZE_EMAIL);
}

// ================================
// CALCULAR DÍAS RESTANTES DE CONTRASEÑA (LDAP)
// ================================

if (isset($_SESSION['usuario'], $_SESSION['password_ldap']) && $script !== 'cambiar_password.php') {

    $CACHE = 21600; // 6 horas

    // Si ya se calculó hace menos de CACHE segundos → no recalcular
    if (isset($_SESSION['pwd_check_ts'], $_SESSION['pwd_days_left'])) {
        if (time() - $_SESSION['pwd_check_ts'] < $CACHE) {
            return; // ya está
        }
    }

    $usuario = $_SESSION['usuario'];
    $clave   = $_SESSION['password_ldap'];

    $LDAP_HOST = "ldap://192.168.100.10";
    $BASE_DN   = "DC=intranet,DC=local";
    $DOMINIO   = "INTRANET";

    // Conectar
    $dc = @ldap_connect($LDAP_HOST);
    if ($dc) {
        ldap_set_option($dc, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($dc, LDAP_OPT_REFERRALS, 0);

        // Autenticar con el propio usuario
        if (@ldap_bind($dc, "$DOMINIO\\$usuario", $clave)) {

            // 1) Obtener pwdLastSet
            $sr1 = @ldap_search($dc, $BASE_DN, "(sAMAccountName=$usuario)", ["pwdLastSet"]);
            $en1 = $sr1 ? ldap_first_entry($dc, $sr1) : null;
            $val1 = $en1 ? ldap_get_values($dc, $en1, "pwdLastSet") : null;
            $pwdLastSet = ($val1 && $val1['count'] > 0) ? (int)$val1[0] : 0;

            // 2) Obtener maxPwdAge del dominio
            $sr2 = @ldap_search($dc, $BASE_DN, "(objectClass=domainDNS)", ["maxPwdAge"]);
            $en2 = $sr2 ? ldap_first_entry($dc, $sr2) : null;
            $val2 = $en2 ? ldap_get_values($dc, $en2, "maxPwdAge") : null;
            $maxPwdAge = ($val2 && $val2['count'] > 0) ? (int)$val2[0] : 0;

            // Conversión de FILETIME
            $unixLastSet = $pwdLastSet ? (int)(($pwdLastSet / 10000000) - 11644473600) : time();

            if ($maxPwdAge == 0) {
                // Dominio sin expiración → aplicas política web
                $dias = 60;
                $expires = $unixLastSet + ($dias * 86400);
            } else {
                // maxPwdAge es negativo → convertirlo a segundos positivos
                $expires = $unixLastSet + (int)(abs($maxPwdAge) / 10000000);
            }

            $dias_restantes = (int)floor(($expires - time()) / 86400);
        } else {
            // Si falla el bind → asigna un valor prudente
            $dias_restantes = 60;
        }
    } else {
        $dias_restantes = 60;
    }

    // Guardar en sesión
    $_SESSION['pwd_days_left'] = $dias_restantes;
    $_SESSION['pwd_check_ts']  = time();
}

// ================================
// FIN
// ================================
?>

