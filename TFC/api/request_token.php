<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'method']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['uid'])) {
  http_response_code(400);
  echo json_encode(['error' => 'no uid']);
  exit;
}

$uid = preg_replace('/[^0-9a-f]/i', '', $input['uid']);
if ($uid === '') {
  http_response_code(400);
  echo json_encode(['error' => 'bad uid']);
  exit;
}

try {
  $pdo = new PDO(
    "mysql:host=localhost;dbname=FURRI_CUARTEL;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Buscar si existe el UID en la tabla de tarjetas NFC
  $stmt = $pdo->prepare("SELECT ldap_uid FROM NFC_TOKENS WHERE uid = ?");
  $stmt->execute([$uid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $ldap_uid = $row['ldap_uid'] ?? null;

  // Generar token temporal (válido 15 segundos)
  $token   = bin2hex(random_bytes(16));
  $expires = (new DateTime('+15 seconds'))->format('Y-m-d H:i:s');

  // Insertar registro efímero
  $ins = $pdo->prepare("
    INSERT INTO NFC_EPHEMERAL (token, uid, ldap_uid, expires_at) 
    VALUES (?, ?, ?, ?)
  ");
  $ins->execute([$token, $uid, $ldap_uid, $expires]);

  echo json_encode(['ok' => true, 'token' => $token, 'expires' => $expires]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error' => 'server',
    'msg' => $e->getMessage() // 🔹 en producción mejor ocultarlo
  ]);
}

