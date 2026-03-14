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
    http_response_code(419);
    exit("Solicitud invalida.");
}

$id = (int) ($_POST["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit("ID invalido.");
}

$stmtPrev = $mysqli->prepare("SELECT nombre, estado FROM productos WHERE id = ? LIMIT 1");
$producto = null;
if ($stmtPrev) {
    $stmtPrev->bind_param("i", $id);
    $stmtPrev->execute();
    $resultPrev = $stmtPrev->get_result();
    $producto = $resultPrev ? $resultPrev->fetch_assoc() : null;
    $stmtPrev->close();
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

if ($producto) {
    $usuarioId = $_SESSION["admin_id"] ?? null;
    $usuarioNombre = trim($_SESSION["admin_user"] ?? "");
    $changes = [
        "changes" => [
            "estado" => ["before" => $producto["estado"] ?? "", "after" => "finalizado"]
        ]
    ];
    log_producto_historial($mysqli, "retirar", $id, $producto["nombre"] ?? "", $usuarioId, $usuarioNombre, $changes);
}

header("Location: productos.php?estado=retirado");
exit;
