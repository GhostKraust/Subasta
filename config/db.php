<?php
require_once __DIR__ . "/../php/lib/security.php";
send_security_headers();

$DB_HOST = getenv("SUBASTA_DB_HOST") ?: "localhost";
$DB_USER = getenv("SUBASTA_DB_USER") ?: "root";
$DB_PASS = getenv("SUBASTA_DB_PASS") ?: "";
$DB_NAME = getenv("SUBASTA_DB_NAME") ?: "subasta";

$APP_TZ = getenv("SUBASTA_APP_TZ") ?: "America/Mexico_City";
date_default_timezone_set($APP_TZ);

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit("Error de conexion a la base de datos.");
}

if (!$mysqli->set_charset("utf8mb4")) {
    http_response_code(500);
    exit("Error al configurar charset.");
}

$tzOffset = (new DateTime("now", new DateTimeZone($APP_TZ)))->format("P");
$mysqli->query("SET time_zone = '" . $mysqli->real_escape_string($tzOffset) . "'");

$checkLoginTable = $mysqli->query("SHOW TABLES LIKE 'login_intentos'");
if ($checkLoginTable && $checkLoginTable->num_rows === 0) {
    $mysqli->query(
        "CREATE TABLE login_intentos (" .
        "id INT NOT NULL AUTO_INCREMENT," .
        "usuario VARCHAR(50) NOT NULL," .
        "ip VARCHAR(45) NOT NULL," .
        "intentos INT NOT NULL DEFAULT 0," .
        "bloqueado_hasta DATETIME DEFAULT NULL," .
        "actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP," .
        "PRIMARY KEY (id)," .
        "UNIQUE KEY uniq_usuario_ip (usuario, ip)" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

$checkHistTable = $mysqli->query("SHOW TABLES LIKE 'ganadores_historial'");
if ($checkHistTable && $checkHistTable->num_rows === 0) {
    $mysqli->query(
        "CREATE TABLE ganadores_historial (" .
        "id INT NOT NULL AUTO_INCREMENT," .
        "producto_id INT NOT NULL," .
        "producto_nombre VARCHAR(255) NOT NULL," .
        "categoria_id INT DEFAULT NULL," .
        "nombre_usuario VARCHAR(150) DEFAULT NULL," .
        "correo_usuario VARCHAR(150) DEFAULT NULL," .
        "telefono_usuario VARCHAR(20) DEFAULT NULL," .
        "monto_puja DECIMAL(10,2) DEFAULT NULL," .
        "fecha_puja DATETIME DEFAULT NULL," .
        "fecha_cierre DATETIME NOT NULL," .
        "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP," .
        "PRIMARY KEY (id)," .
        "UNIQUE KEY uniq_producto_cierre (producto_id, fecha_cierre)," .
        "KEY idx_fecha_cierre (fecha_cierre)" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

$checkProdHistTable = $mysqli->query("SHOW TABLES LIKE 'historial_productos'");
if ($checkProdHistTable && $checkProdHistTable->num_rows === 0) {
    $mysqli->query(
        "CREATE TABLE historial_productos (" .
        "id INT NOT NULL AUTO_INCREMENT," .
        "producto_id INT NOT NULL," .
        "producto_nombre VARCHAR(255) DEFAULT NULL," .
        "accion VARCHAR(30) NOT NULL," .
        "usuario_id INT DEFAULT NULL," .
        "usuario_nombre VARCHAR(100) DEFAULT NULL," .
        "cambios LONGTEXT DEFAULT NULL," .
        "ip VARCHAR(45) DEFAULT NULL," .
        "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP," .
        "PRIMARY KEY (id)," .
        "KEY idx_producto (producto_id)," .
        "KEY idx_accion (accion)," .
        "KEY idx_fecha (created_at)" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function mantenerEstadoSubastas($mysqli) {
    // Lógica para mover ganadores al historial
if ($checkHistTable && $checkHistTable->num_rows > 0) {
    $mysqli->query(
        "INSERT IGNORE INTO ganadores_historial (" .
        "producto_id, producto_nombre, categoria_id, nombre_usuario, correo_usuario, telefono_usuario, monto_puja, fecha_puja, fecha_cierre" .
        ") " .
        "SELECT p.id, p.nombre, p.categoria_id, w.nombre_usuario, w.correo_usuario, w.telefono_usuario, w.monto_puja, w.fecha_puja, " .
        "COALESCE(p.fecha_fin, w.fecha_puja) " .
        "FROM productos p " .
        "INNER JOIN (" .
            "SELECT pu1.* FROM pujas pu1 " .
            "INNER JOIN (" .
                "SELECT producto_id, MAX(monto_puja) AS max_monto FROM pujas GROUP BY producto_id" .
            ") mx ON pu1.producto_id = mx.producto_id AND pu1.monto_puja = mx.max_monto " .
            "INNER JOIN (" .
                "SELECT producto_id, MAX(fecha_puja) AS max_fecha FROM pujas GROUP BY producto_id" .
            ") mf ON pu1.producto_id = mf.producto_id AND pu1.fecha_puja = mf.max_fecha" .
        ") w ON w.producto_id = p.id " .
        "LEFT JOIN ganadores_historial gh ON gh.producto_id = p.id AND gh.fecha_cierre = COALESCE(p.fecha_fin, w.fecha_puja) " .
        "WHERE (p.estado = 'finalizado' OR (p.fecha_fin IS NOT NULL AND p.fecha_fin < DATE_SUB(NOW(), INTERVAL 2 DAY))) AND gh.id IS NULL"
    );
}

    // Lógica para pausar o finalizar productos
$checkFinColumn = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_fin'");
if ($checkFinColumn && $checkFinColumn->num_rows > 0) {
    $resultToPause = $mysqli->query(
        "SELECT id FROM productos " .
        "WHERE estado = 'activo' AND fecha_fin IS NOT NULL AND fecha_fin < NOW()"
    );
    if ($resultToPause) {
        while ($producto = $resultToPause->fetch_assoc()) {
            $productoId = (int) ($producto["id"] ?? 0);
            if ($productoId <= 0) {
                continue;
            }
            $stmtPause = $mysqli->prepare("UPDATE productos SET estado = 'pausado' WHERE id = ?");
            if ($stmtPause) {
                $stmtPause->bind_param("i", $productoId);
                $stmtPause->execute();
                $stmtPause->close();
            }
        }
    }

    $resultToClose = $mysqli->query(
        "SELECT id, nombre, categoria_id, fecha_fin FROM productos " .
        "WHERE estado = 'pausado' AND fecha_fin IS NOT NULL AND fecha_fin < DATE_SUB(NOW(), INTERVAL 2 DAY)"
    );
    if ($resultToClose) {
        while ($producto = $resultToClose->fetch_assoc()) {
            $productoId = (int) ($producto["id"] ?? 0);
            $fechaCierre = $producto["fecha_fin"] ?? null;
            if ($productoId <= 0 || !$fechaCierre) {
                continue;
            }

            $stmtWinner = $mysqli->prepare(
                "SELECT nombre_usuario, correo_usuario, telefono_usuario, monto_puja, fecha_puja " .
                "FROM pujas WHERE producto_id = ? " .
                "ORDER BY monto_puja DESC, fecha_puja DESC LIMIT 1"
            );
            $winner = null;
            if ($stmtWinner) {
                $stmtWinner->bind_param("i", $productoId);
                $stmtWinner->execute();
                $resWinner = $stmtWinner->get_result();
                $winner = $resWinner ? $resWinner->fetch_assoc() : null;
                $stmtWinner->close();
            }

            if ($winner) {
                $stmtHist = $mysqli->prepare(
                    "INSERT IGNORE INTO ganadores_historial (" .
                    "producto_id, producto_nombre, categoria_id, nombre_usuario, correo_usuario, telefono_usuario, monto_puja, fecha_puja, fecha_cierre" .
                    ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if ($stmtHist) {
                    $productoNombre = $producto["nombre"] ?? "";
                    $categoriaId = (int) ($producto["categoria_id"] ?? 0);
                    $nombre = $winner["nombre_usuario"] ?? null;
                    $correo = $winner["correo_usuario"] ?? null;
                    $telefono = $winner["telefono_usuario"] ?? null;
                    $monto = (float) ($winner["monto_puja"] ?? 0);
                    $fechaPuja = $winner["fecha_puja"] ?? null;
                    $stmtHist->bind_param(
                        "isisssdss",
                        $productoId,
                        $productoNombre,
                        $categoriaId,
                        $nombre,
                        $correo,
                        $telefono,
                        $monto,
                        $fechaPuja,
                        $fechaCierre
                    );
                    $stmtHist->execute();
                    $stmtHist->close();
                }
            }

            $stmtFinal = $mysqli->prepare("UPDATE productos SET estado = 'finalizado' WHERE id = ?");
            if ($stmtFinal) {
                $stmtFinal->bind_param("i", $productoId);
                $stmtFinal->execute();
                $stmtFinal->close();
            }

            $stmtClear = $mysqli->prepare("DELETE FROM pujas WHERE producto_id = ?");
            if ($stmtClear) {
                $stmtClear->bind_param("i", $productoId);
                $stmtClear->execute();
                $stmtClear->close();
            }
        }
    }
}
}
