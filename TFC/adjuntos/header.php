<header>
    <div class="header-content">
        <div class="titulo">
            <h1>🪖 Sistema de Gestión - Furri del Cuartel</h1>
        </div>

        <?php if (isset($_SESSION['usuario'])): ?>
            <div class="acciones-usuario">
                <span>👤 <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                <!-- <a href="bandeja.php">📩 Correo</a> -->
                <?php if ($_SESSION['rol'] === 'admin'): ?>
                    <a href="admin_usuarios.php">⚙️ Usuarios</a>
                <?php endif; ?>
                <a href="panel_usuario.php">🛠️ Mi cuenta</a>
                <a href="index.php?logout=1">📕 Cerrar sesión</a>
            </div>
        <?php endif; ?>
    </div>
</header>
