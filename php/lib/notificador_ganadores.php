<?php
require_once __DIR__ . "/mailer.php";

function ensure_ganadores_notificados_table($mysqli)
{
    $sql = "CREATE TABLE IF NOT EXISTS ganadores_notificados (" .
        "producto_id INT PRIMARY KEY," .
        "correo VARCHAR(255) NOT NULL," .
        "enviado_en DATETIME NOT NULL" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($sql);
}

function log_mail_event($message)
{
    $dir = __DIR__ . "/../../logs";
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = date("Y-m-d H:i:s") . " " . $message . "\n";
    file_put_contents($dir . "/mailer.log", $line, FILE_APPEND);
}

function notificar_ganadores($mysqli, $config, $limit = 5)
{
    ensure_ganadores_notificados_table($mysqli);

    $query = "SELECT p.id, p.nombre, w.nombre_usuario, w.correo_usuario, w.monto_puja, w.fecha_puja " .
        "FROM productos p " .
        "LEFT JOIN (" .
            "SELECT pu1.* " .
            "FROM pujas pu1 " .
            "INNER JOIN (" .
                "SELECT producto_id, MAX(monto_puja) AS max_monto " .
                "FROM pujas GROUP BY producto_id" .
            ") mx ON pu1.producto_id = mx.producto_id AND pu1.monto_puja = mx.max_monto " .
            "INNER JOIN (" .
                "SELECT producto_id, MAX(fecha_puja) AS max_fecha " .
                "FROM pujas GROUP BY producto_id" .
            ") mf ON pu1.producto_id = mf.producto_id AND pu1.fecha_puja = mf.max_fecha" .
        ") w ON w.producto_id = p.id " .
        "LEFT JOIN ganadores_notificados n ON n.producto_id = p.id " .
        "WHERE (p.estado = 'finalizado' OR (p.fecha_fin IS NOT NULL AND p.fecha_fin < DATE_SUB(NOW(), INTERVAL 2 DAY))) " .
        "AND n.producto_id IS NULL " .
        "AND w.correo_usuario IS NOT NULL AND w.correo_usuario <> '' " .
        "ORDER BY p.id DESC " .
        "LIMIT " . (int) $limit;

    $result = $mysqli->query($query);
    if (!$result) {
        log_mail_event("Error consulta ganadores: " . $mysqli->error);
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $to = trim((string) ($row["correo_usuario"] ?? ""));
        $nombre = trim((string) ($row["nombre_usuario"] ?? ""));
        $producto = trim((string) ($row["nombre"] ?? ""));
        $monto = (float) ($row["monto_puja"] ?? 0);

        if ($to === "") {
            continue;
        }

        $subject = "Ganaste la subasta: " . $producto;
        $body = "Hola " . ($nombre !== "" ? $nombre : "ganador") . ",\n\n" .
            "Felicidades, ganaste la subasta del producto '" . $producto . "'.\n" .
            "Monto ganador: $" . number_format($monto, 2) . " MXN.\n\n" .
            "Gracias por participar.";

        $send = send_smtp_mail(
            $config["host"],
            $config["port"],
            $config["from"],
            $to,
            $subject,
            $body
        );

        if (!$send["ok"]) {
            log_mail_event("Fallo envio a " . $to . ": " . $send["error"]);
            continue;
        }

        $stmt = $mysqli->prepare("INSERT INTO ganadores_notificados (producto_id, correo, enviado_en) VALUES (?, ?, NOW())");
        if ($stmt) {
            $productoId = (int) ($row["id"] ?? 0);
            $stmt->bind_param("is", $productoId, $to);
            $stmt->execute();
            $stmt->close();
        }

        log_mail_event("Enviado a " . $to . " por producto " . $producto);
    }
}
