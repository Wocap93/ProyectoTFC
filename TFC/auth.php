<?php
declare(strict_types=1);
session_start();



const TABLE_EPHEMERAL = 'NFC_EPHEMERAL';
const TABLE_USUARIOS  = 'USUARIOS';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=FURRI_CUARTEL;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error BD";
    exit;
}

if (empty($_GET['token'])) {
    http_response_code(400);
    echo "No token";
    exit;
}

$token = preg_replace('/[^0-9a-f]/i', '', $_GET['token']);
if ($token === '' || strlen($token) > 128) {
    http_response_code(400);
    echo "Token inválido";
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT token, uid, ldap_uid, expires_at, used
        FROM " . TABLE_EPHEMERAL . "
        WHERE token = :token
        FOR UPDATE
    ");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->rollBack();
        http_response_code(403);
        echo "Token inválido";
        exit;
    }

    if ((int)$row['used'] === 1) {
        $pdo->rollBack();
        http_response_code(403);
        echo "Token ya usado";
        exit;
    }

    $now = new DateTime((string)$pdo->query("SELECT NOW()")->fetchColumn());
    $expires = new DateTime($row['expires_at']);
    if ($now > $expires) {
        $pdo->rollBack();
        http_response_code(403);
        echo "Token caducado";
        exit;
    }

    $pdo->prepare("UPDATE " . TABLE_EPHEMERAL . " SET used = 1 WHERE token = :t AND used = 0")
        ->execute([':t' => $token]);

    $pdo->commit();

    $ldap_uid = $row['ldap_uid'] ?? '';
    if ($ldap_uid === '') {
        http_response_code(403);
        echo "Tarjeta válida pero sin usuario asignado.";
        exit;
    }

    // 🔹 aquí añadimos la contraseña IMAP
    $stmt = $pdo->prepare("
        SELECT id_usuario, rol, email, clave_dovecot
        FROM " . TABLE_USUARIOS . "
        WHERE nombre_usuario = :n
    ");
    $stmt->execute([':n' => $ldap_uid]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(403);
        echo "Usuario válido pero sin rol asignado.";
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario']       = $ldap_uid;
    $_SESSION['id_usuario']    = $user['id_usuario'];
    $_SESSION['rol']           = $user['rol'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['clave_dovecot'] = str_replace('{PLAIN}', '', $user['clave_dovecot']);
    $_SESSION['auth_time']     = time();

    header("Location: /index.php");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "Error interno";
    exit;
}

