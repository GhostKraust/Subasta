<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/lib/historial_productos.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    header("Location: productos.php?estado=error");
    exit;
}

$id = (int) ($_POST["id"] ?? 0);
$nuevoEstado = trim($_POST["estado"] ?? "");
$inicioRaw = trim($_POST["fecha_inicio"] ?? "");
$finRaw = trim($_POST["fecha_fin"] ?? "");

if ($id <= 0 || !in_array($nuevoEstado, ["activo", "pausado"], true)) {
    header("Location: productos.php?estado=error");
    exit;
}

$stmt = $mysqli->prepare("SELECT nombre, estado, fecha_inicio, fecha_fin FROM productos WHERE id = ? LIMIT 1");
if (!$stmt) {
    header("Location: productos.php?estado=error");
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$producto) {
    header("Location: productos.php?estado=error");
    exit;
}

if (($producto["estado"] ?? "") === "finalizado" && $nuevoEstado !== "activo") {
    header("Location: productos.php?estado=finalizado");
    exit;
}

$reactivando = ($producto["estado"] ?? "") === "finalizado" && $nuevoEstado === "activo";
$inicioSql = null;
$finSql = null;

if ($reactivando) {
    if ($inicioRaw === "" || $finRaw === "") {
        header("Location: productos.php?estado=fechas");
        exit;
    }
    $inicioDate = DateTime::createFromFormat("Y-m-d\\TH:i", $inicioRaw);
    $finDate = DateTime::createFromFormat("Y-m-d\\TH:i", $finRaw);
    $now = new DateTime();
    $nowFloor = new DateTime($now->format("Y-m-d H:i:00"));
    if (!$inicioDate || !$finDate || $inicioDate < $nowFloor || $finDate <= $inicioDate) {
        header("Location: productos.php?estado=fechas");
        exit;
    }
    $inicioSql = $inicioDate->format("Y-m-d H:i:00");
    $finSql = $finDate->format("Y-m-d H:i:00");
}

$update = $reactivando
    ? $mysqli->prepare("UPDATE productos SET estado = ?, fecha_inicio = ?, fecha_fin = ? WHERE id = ?")
    : $mysqli->prepare("UPDATE productos SET estado = ? WHERE id = ?");
if (!$update) {
    header("Location: productos.php?estado=error");
    exit;
}

if ($reactivando) {
    $update->bind_param("sssi", $nuevoEstado, $inicioSql, $finSql, $id);
} else {
    $update->bind_param("si", $nuevoEstado, $id);
}
if (!$update->execute()) {
    $update->close();
    header("Location: productos.php?estado=error");
    exit;
}
$update->close();

$accion = $reactivando ? "reactivar" : ($nuevoEstado === "pausado" ? "pausar" : "reanudar");
$before = [
    "estado" => $producto["estado"] ?? "",
    "fecha_inicio" => $producto["fecha_inicio"] ?? null,
    "fecha_fin" => $producto["fecha_fin"] ?? null
];
$after = [
    "estado" => $nuevoEstado,
    "fecha_inicio" => $reactivando ? $inicioSql : ($producto["fecha_inicio"] ?? null),
    "fecha_fin" => $reactivando ? $finSql : ($producto["fecha_fin"] ?? null)
];

$changes = [];
foreach ($before as $field => $oldValue) {
    $newValue = $after[$field] ?? null;
    if ($oldValue !== $newValue) {
        $changes[$field] = ["before" => $oldValue, "after" => $newValue];
    }
}
if (!empty($changes)) {
    $usuarioId = $_SESSION["admin_id"] ?? null;
    $usuarioNombre = trim($_SESSION["admin_user"] ?? "");
    $productoNombre = $producto["nombre"] ?? "";
    log_producto_historial($mysqli, $accion, $id, $productoNombre, $usuarioId, $usuarioNombre, ["changes" => $changes]);
}

if ($reactivando) {
    $clear = $mysqli->prepare("DELETE FROM pujas WHERE producto_id = ?");
    if ($clear) {
        $clear->bind_param("i", $id);
        $clear->execute();
        $clear->close();
    }
}

header("Location: productos.php?estado=" . ($nuevoEstado === "pausado" ? "pausado" : "activo"));
exit;
