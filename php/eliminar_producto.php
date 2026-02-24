<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

$id = (int) ($_POST["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit("ID invalido.");
}

$imagenUrl = "";
$stmtImg = $mysqli->prepare("SELECT imagen_url FROM productos WHERE id = ? LIMIT 1");
if ($stmtImg) {
    $stmtImg->bind_param("i", $id);
    $stmtImg->execute();
    $result = $stmtImg->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmtImg->close();
    if ($row) {
        $imagenUrl = $row["imagen_url"] ?? "";
    }
}

$stmt = $mysqli->prepare("DELETE FROM productos WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    exit("No se pudo preparar la eliminacion.");
}

$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    http_response_code(500);
    exit("No se pudo eliminar el producto.");
}
$stmt->close();

if ($imagenUrl !== "" && str_starts_with($imagenUrl, "uploads/productos/")) {
    $path = __DIR__ . "/../" . $imagenUrl;
    if (is_file($path)) {
        unlink($path);
    }
}

header("Location: panel.php?deleted=1");
exit;
