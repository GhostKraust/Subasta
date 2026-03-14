<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$hasRole = false;
$checkRole = $mysqli->query("SHOW COLUMNS FROM admin LIKE 'rol'");
if ($checkRole && $checkRole->num_rows > 0) {
    $hasRole = true;
}

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit("Personal invalido.");
}

$selectRole = $hasRole ? ", rol" : "";
$stmt = $mysqli->prepare("SELECT id, usuario$selectRole FROM admin WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit("No se pudo consultar el personal.");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$admin) {
    http_response_code(404);
    exit("Personal no encontrado.");
}

$error = trim($_GET["error"] ?? "");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Editar personal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
</head>
<body class="auth-page">
    <main class="auth">
        <section class="auth-card">
            <div class="auth-brand">
                <div class="brand-mark">A</div>
                <div>
                    <div class="brand-name">Administracion</div>
                    <div class="brand-tag">Editar personal</div>
                </div>
            </div>
            <h1>Editar usuario</h1>
            <?php if ($error === "exists") { ?>
                <p class="lead">El usuario ya existe. Usa otro nombre.</p>
            <?php } elseif ($error === "invalid") { ?>
                <p class="lead">Completa los datos obligatorios.</p>
            <?php } elseif ($error === "error") { ?>
                <p class="lead">No se pudo guardar el cambio. Intenta de nuevo.</p>
            <?php } else { ?>
                <p class="lead">Actualiza el usuario o cambia la contrasena.</p>
            <?php } ?>
            <form class="auth-form" action="actualizar_personal.php" method="post">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $admin["id"]; ?>" />
                <label class="field">
                    <span>Usuario</span>
                    <input name="usuario" type="text" required value="<?php echo htmlspecialchars($admin["usuario"] ?? ""); ?>" />
                </label>
                <?php if ($hasRole) { ?>
                    <label class="field">
                        <span>Rol</span>
                        <select name="rol" required>
                            <option value="admin" <?php echo (($admin["rol"] ?? "admin") === "admin") ? "selected" : ""; ?>>Administrador</option>
                            <option value="operativo" <?php echo (($admin["rol"] ?? "") === "operativo") ? "selected" : ""; ?>>Operativo</option>
                        </select>
                    </label>
                <?php } ?>
                <label class="field">
                    <span>Nueva contrasena</span>
                    <input name="contrasena" type="password" placeholder="Deja en blanco para no cambiar" />
                    <small class="field-hint">Si no escribes nada, la contrasena se conserva.</small>
                </label>
                <button class="btn" type="submit">Guardar cambios</button>
            </form>
            <div class="switch">
                <a class="link" href="panel.php">Volver al panel</a>
            </div>
        </section>
        <aside class="auth-panel">
            <div class="panel-content">
                <h2>Gestion de personal</h2>
                <p>Actualiza accesos administrativos sin afectar las subastas publicadas.</p>
                <div class="panel-stats">
                    <div>
                        <span>Acceso</span>
                        <strong>Administrador</strong>
                    </div>
                    <div>
                        <span>Seguridad</span>
                        <strong>Contrasena segura</strong>
                    </div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
