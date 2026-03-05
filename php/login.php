<?php
require_once __DIR__ . "/../config/db.php";

$maxIntentos = 5;
$ventanaMin = 15;
$bloqueoHoras = 2;
session_start();

$error = "";
$hasRole = false;
$checkRole = $mysqli->query("SHOW COLUMNS FROM admin LIKE 'rol'");
if ($checkRole && $checkRole->num_rows > 0) {
    $hasRole = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ahora = new DateTime();
    $bloqueado = false;
    $intentos = 0;

    if ($usuario !== "") {
        $stmtIntento = $mysqli->prepare("SELECT intentos, bloqueado_hasta, actualizado_en FROM login_intentos WHERE usuario = ? AND ip = ? LIMIT 1");
        if ($stmtIntento) {
            $stmtIntento->bind_param("ss", $usuario, $ip);
            $stmtIntento->execute();
            $resIntento = $stmtIntento->get_result();
            $rowIntento = $resIntento ? $resIntento->fetch_assoc() : null;
            $stmtIntento->close();
            if ($rowIntento) {
                $intentos = (int) ($rowIntento["intentos"] ?? 0);
                $bloqueadoHastaRaw = $rowIntento["bloqueado_hasta"] ?? null;
                if ($bloqueadoHastaRaw) {
                    $bloqueadoHasta = new DateTime($bloqueadoHastaRaw);
                    if ($bloqueadoHasta > $ahora) {
                        $bloqueado = true;
                        $diff = $ahora->diff($bloqueadoHasta);
                        $minutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                        $error = "Demasiados intentos. Intenta de nuevo en " . max(1, $minutos) . " minutos.";
                    }
                }

                $actualizadoRaw = $rowIntento["actualizado_en"] ?? null;
                if ($actualizadoRaw) {
                    $actualizado = new DateTime($actualizadoRaw);
                    $limite = (clone $ahora)->modify("-" . $ventanaMin . " minutes");
                    if ($actualizado < $limite) {
                        $intentos = 0;
                    }
                }
            }
        }
    }

    if ($bloqueado) {
        // bloqueado por intentos
    } elseif ($usuario === "" || $contrasena === "") {
        $error = "Completa usuario y contrasena.";
    } else {
        $selectRole = $hasRole ? ", rol" : "";
        $stmt = $mysqli->prepare("SELECT id, password$selectRole FROM admin WHERE usuario = ? LIMIT 1");
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
                $clearIntento = $mysqli->prepare("DELETE FROM login_intentos WHERE usuario = ? AND ip = ?");
                if ($clearIntento) {
                    $clearIntento->bind_param("ss", $usuario, $ip);
                    $clearIntento->execute();
                    $clearIntento->close();
                }

                $role = "admin";
                if ($hasRole) {
                    $role = strtolower(trim($admin["rol"] ?? "admin"));
                    if (!in_array($role, ["admin", "operativo"], true)) {
                        $role = "admin";
                    }
                }
                $_SESSION["admin_id"] = $admin["id"];
                $_SESSION["admin_user"] = $usuario;
                $_SESSION["admin_role"] = $role;
                $target = $role === "admin" ? "dashboard.php" : "panel.php";
                header("Location: " . $target);
                exit;
            }
        }

        if ($usuario !== "") {
            $intentos += 1;
            $bloqueadoHasta = null;
            if ($intentos >= $maxIntentos) {
                $bloqueadoHasta = (clone $ahora)->modify("+" . $bloqueoHoras . " hours");
            }

            $stmtUp = $mysqli->prepare(
                "INSERT INTO login_intentos (usuario, ip, intentos, bloqueado_hasta) " .
                "VALUES (?, ?, ?, ?) " .
                "ON DUPLICATE KEY UPDATE intentos = VALUES(intentos), bloqueado_hasta = VALUES(bloqueado_hasta)"
            );
            if ($stmtUp) {
                $bloqueadoStr = $bloqueadoHasta ? $bloqueadoHasta->format("Y-m-d H:i:s") : null;
                $stmtUp->bind_param("ssis", $usuario, $ip, $intentos, $bloqueadoStr);
                $stmtUp->execute();
                $stmtUp->close();
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
<body class="auth-page login-page">
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
    </main>
</body>
</html>
