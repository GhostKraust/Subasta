<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

$id = (int) ($_POST["id"] ?? 0);
$usuario = trim($_POST["usuario"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";

if ($id <= 0 || $usuario === "") {
    header("Location: editar_personal.php?id=" . $id . "&error=invalid");
    exit;
}

$stmtCheck = $mysqli->prepare("SELECT id FROM admin WHERE usuario = ? AND id <> ? LIMIT 1");
if ($stmtCheck) {
    $stmtCheck->bind_param("si", $usuario, $id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmtCheck->close();

    if ($exists) {
        header("Location: editar_personal.php?id=" . $id . "&error=exists");
        exit;
    }
}

if ($contrasena !== "") {
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE admin SET usuario = ?, password = ? WHERE id = ?");
    if (!$stmt) {
        header("Location: editar_personal.php?id=" . $id . "&error=error");
        exit;
    }
    $stmt->bind_param("ssi", $usuario, $hash, $id);
} else {
    $stmt = $mysqli->prepare("UPDATE admin SET usuario = ? WHERE id = ?");
    if (!$stmt) {
        header("Location: editar_personal.php?id=" . $id . "&error=error");
        exit;
    }
    $stmt->bind_param("si", $usuario, $id);
}

if ($stmt->execute()) {
    $stmt->close();
    header("Location: panel.php?staff=updated");
    exit;
}

$stmt->close();
header("Location: editar_personal.php?id=" . $id . "&error=error");
exit;
