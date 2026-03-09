<?php

function log_producto_historial($mysqli, $accion, $productoId, $productoNombre, $usuarioId, $usuarioNombre, $cambios)
{
    if (!$mysqli || $productoId <= 0 || $accion === "") {
        return;
    }

    $ip = $_SERVER["REMOTE_ADDR"] ?? null;
    $changesJson = $cambios ? json_encode($cambios, JSON_UNESCAPED_UNICODE) : null;
    $usuarioId = $usuarioId !== null ? (int) $usuarioId : null;
    $productoNombre = $productoNombre !== "" ? $productoNombre : null;
    $usuarioNombre = $usuarioNombre !== "" ? $usuarioNombre : null;

    $stmt = $mysqli->prepare(
        "INSERT INTO historial_productos (producto_id, producto_nombre, accion, usuario_id, usuario_nombre, cambios, ip) " .
        "VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "ississs",
        $productoId,
        $productoNombre,
        $accion,
        $usuarioId,
        $usuarioNombre,
        $changesJson,
        $ip
    );
    $stmt->execute();
    $stmt->close();
}
