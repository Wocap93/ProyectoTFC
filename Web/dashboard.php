<?php
session_start();
$user = $_SESSION['user'] ?? null;
?>
<!doctype html>
<meta charset="utf-8">
<title>Panel</title>
<h1>Panel</h1>
<?php if ($user): ?>
  <p>Has iniciado sesión como <b><?=htmlspecialchars($user)?></b>.</p>
<?php else: ?>
  <p>No hay sesión iniciada.</p>
<?php endif; ?>

