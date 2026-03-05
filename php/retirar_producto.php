<?php
require_once __DIR__ . "/auth.php";
require_admin();
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

$stmt = $mysqli->prepare("UPDATE productos SET estado = 'finalizado' WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    exit("No se pudo preparar la actualizacion.");
}

$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("No se pudo retirar el producto.");
}
$stmt->close();

header("Location: productos.php?estado=retirado");
exit;
