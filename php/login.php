<?php
require_once __DIR__ . "/../config/db.php";

session_start();

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if ($usuario === "" || $contrasena === "") {
        $error = "Completa usuario y contrasena.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, password FROM admin WHERE usuario = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            $stored = $admin["password"] ?? "";
            $isValid = false;
            if ($stored !== "") {
                $isValid = password_verify($contrasena, $stored) || $contrasena === $stored;
            }

            if ($admin && $isValid) {
                $_SESSION["admin_id"] = $admin["id"];
                $_SESSION["admin_user"] = $usuario;
                header("Location: dashboard.php");
                exit;
            }
        }
        $error = "Credenciales invalidas.";
    }
}

$statsActivos = 0;
$statsPujasHoy = 0;
$statsCerradas = 0;

$resultActivos = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'activo'");
if ($resultActivos) {
    $row = $resultActivos->fetch_assoc();
    $statsActivos = (int) ($row["total"] ?? 0);
}

$resultPujasHoy = $mysqli->query("SELECT COUNT(*) AS total FROM pujas WHERE DATE(fecha_puja) = CURDATE()");
if ($resultPujasHoy) {
    $row = $resultPujasHoy->fetch_assoc();
    $statsPujasHoy = (int) ($row["total"] ?? 0);
}

$resultCerradas = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'finalizado'");
if ($resultCerradas) {
    $row = $resultCerradas->fetch_assoc();
    $statsCerradas = (int) ($row["total"] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Iniciar sesion</title>
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
                    <div class="brand-tag">Panel de subastas</div>
                </div>
            </div>
            <h1>Iniciar sesion</h1>
            <p class="lead">Acceso solo para personal autorizado. Administra productos y subastas desde aqui.</p>
            <form class="auth-form" action="login.php" method="post">
                <label class="field">
                    <span>Usuario</span>
                    <input name="usuario" type="text" required autocomplete="username" placeholder="admin" />
                </label>
                <label class="field">
                    <span>Contrasena</span>
                    <input name="contrasena" type="password" required autocomplete="current-password" placeholder="********" />
                </label>
                <?php if ($error !== "") { ?>
                    <div class="field-hint"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>
                <button class="btn" type="submit">Entrar</button>
            </form>
            <div class="switch">Solo personal administrativo.</div>
        </section>
        <aside class="auth-panel">
            <div class="panel-content">
                <h2>Gestion rapida de subastas</h2>
                <p>Agrega productos, actualiza precios iniciales e incrementos minimos y revisa las pujas activas.</p>
                <div class="panel-stats">
                    <div>
                        <span>Productos activos</span>
                        <strong><?php echo number_format($statsActivos); ?></strong>
                    </div>
                    <div>
                        <span>Pujas hoy</span>
                        <strong><?php echo number_format($statsPujasHoy); ?></strong>
                    </div>
                    <div>
                        <span>Subastas cerradas</span>
                        <strong><?php echo number_format($statsCerradas); ?></strong>
                    </div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
