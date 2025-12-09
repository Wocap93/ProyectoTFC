<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo '<pre>';
    print_r($_POST);
    print_r($_FILES);
    echo '</pre>';

    if (isset($_FILES["adjunto"]) && $_FILES["adjunto"]["error"] == UPLOAD_ERR_OK) {
        $tmp_name = $_FILES["adjunto"]["tmp_name"];
        $name = basename($_FILES["adjunto"]["name"]);
        $ruta_destino = __DIR__ . "/adjuntos/" . $name;

        if (move_uploaded_file($tmp_name, $ruta_destino)) {
            echo "<p style='color: green;'>✅ Archivo subido correctamente a: adjuntos/$name</p>";
        } else {
            echo "<p style='color: red;'>❌ Error al mover el archivo.</p>";
        }
    } elseif (isset($_FILES["adjunto"])) {
        echo "<p style='color: red;'>❌ Error en la subida (código: " . $_FILES["adjunto"]["error"] . ")</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prueba de subida de archivos</title>
</head>
<body>
    <h2>Prueba de subida de archivos</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="adjunto" required>
        <br><br>
        <input type="submit" value="Subir archivo">
    </form>
</body>
</html>

