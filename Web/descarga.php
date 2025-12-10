<?php
if (!isset($_GET['file'])) {
    die("Archivo no especificado.");
}

$archivo = basename($_GET['file']);
$ruta = 'adjuntos/' . $archivo;

if (!file_exists($ruta)) {
    die("Archivo no encontrado.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;

