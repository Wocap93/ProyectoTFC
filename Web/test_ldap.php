<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "ldaps://Servidor.intranet.local:636";
$user = "INTRANET\\Administrador";
$pass = "Patata!2";

ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

$dc = ldap_connect($host);
ldap_set_option($dc, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($dc, LDAP_OPT_REFERRALS, 0);

if (!$dc) {
    die("No se pudo conectar a LDAP");
}

if (@ldap_bind($dc, $user, $pass)) {
    echo "OK: bind como Administrador correcto";
} else {
    $err = ldap_error($dc);
    echo "ERROR bind: $err";
}

