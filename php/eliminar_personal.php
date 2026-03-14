<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    header("Location: panel.php?staff=error");
    exit;
}

$id = (int) ($_POST["id"] ?? 0);
$currentAdminId = (int) ($_SESSION["admin_id"] ?? 0);

if ($id <= 0) {
    header("Location: panel.php?staff=error");
    exit;
}

if ($id === $currentAdminId) {
    header("Location: panel.php?staff=self");
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM admin WHERE id = ?");
if (!$stmt) {
    header("Location: panel.php?staff=error");
    exit;
}

$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $stmt->close();
    header("Location: panel.php?staff=deleted");
    exit;
}

$stmt->close();
header("Location: panel.php?staff=error");
exit;
