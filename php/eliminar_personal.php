<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

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
