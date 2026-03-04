<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

$id = (int) ($_POST["id"] ?? 0);
$nuevoEstado = trim($_POST["estado"] ?? "");

if ($id <= 0 || !in_array($nuevoEstado, ["activo", "pausado"], true)) {
    header("Location: panel.php?estado=error");
    exit;
}

$stmt = $mysqli->prepare("SELECT estado FROM productos WHERE id = ? LIMIT 1");
if (!$stmt) {
    header("Location: panel.php?estado=error");
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$producto) {
    header("Location: panel.php?estado=error");
    exit;
}

if (($producto["estado"] ?? "") === "finalizado") {
    header("Location: panel.php?estado=finalizado");
    exit;
}

$update = $mysqli->prepare("UPDATE productos SET estado = ? WHERE id = ?");
if (!$update) {
    header("Location: panel.php?estado=error");
    exit;
}

$update->bind_param("si", $nuevoEstado, $id);
if (!$update->execute()) {
    $update->close();
    header("Location: panel.php?estado=error");
    exit;
}
$update->close();

header("Location: panel.php?estado=" . ($nuevoEstado === "pausado" ? "pausado" : "activo"));
exit;
