<header>
    <div class="header-content">
        <div class="titulo">
            <h2>Sistema de Gestión - Furri del Cuartel</h2>
        </div>

        <?php if (isset($_SESSION['usuario'])): ?>
<div class="acciones-usuario">
    <?php 
        $usuario = ucfirst(htmlspecialchars($_SESSION['usuario']));
        $rol = ucfirst(htmlspecialchars($_SESSION['rol']));
    ?>
    
    <span>👤 <?= $usuario ?> (<?= $rol ?>)</span>

    <a href="enviar_mensaje.php">📩 Correo</a>

    <?php if ($_SESSION['rol'] === 'admin'): ?>
        <a href="admin_usuarios.php">⚙️ Usuarios</a>
    <?php endif; ?>

    <a href="index.php?logout=1">📕 Cerrar sesión</a>
</div>


            <!-- 🔔 AVISO SIEMPRE VISIBLE DEL ESTADO DE LA CONTRASEÑA -->
            <?php
            if (isset($_SESSION['pwd_days_left'])):

                $d = (int)$_SESSION['pwd_days_left'];

                if ($d <= 0) {
                    // Caducada
                    $bg = '#f8d7da'; 
                    $bd = '#f5c6cb'; 
                    $fg = '#721c24';
                    $texto = "⚠️ Tu contraseña ha caducado.";
                } 
                elseif ($d <= 10) {
                    // A punto de caducar
                    $bg = '#fff3cd'; 
                    $bd = '#ffeeba'; 
                    $fg = '#856404';
                    $texto = "⏳ Tu contraseña caduca en {$d} día" . ($d == 1 ? '' : 's') . ".";
                } 
                else {
                    // Todo bien
                    $bg = '#d4edda'; 
                    $bd = '#c3e6cb'; 
                    $fg = '#155724';
                    $texto = "🔒 Contraseña válida. Quedan {$d} día" . ($d == 1 ? '' : 's') . ".";
                }
            ?>

                <div style="
                    background:<?= $bg ?>;
                    border:1px solid <?= $bd ?>;
                    color:<?= $fg ?>;
                    padding:6px 10px;
                    border-radius:8px;
                    margin:10px auto 0;
                    max-width:700px;
                    text-align:center;
                ">
                    <?= $texto ?>

                    <a href="cambiar_password.php"
                       style="color:<?= $fg ?>; text-decoration:underline; margin-left:6px;">
                        Cambiar ahora
                    </a>
                </div>

            <?php endif; ?>
        <?php endif; ?>
    </div>
</header>

