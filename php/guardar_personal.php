<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

$usuario = trim($_POST["usuario"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";

if ($usuario === "" || $contrasena === "") {
    header("Location: panel.php?staff=error");
    exit;
}

$stmtCheck = $mysqli->prepare("SELECT id FROM admin WHERE usuario = ? LIMIT 1");
if ($stmtCheck) {
    $stmtCheck->bind_param("s", $usuario);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmtCheck->close();

    if ($exists) {
        header("Location: panel.php?staff=exists");
        exit;
    }
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare("INSERT INTO admin (usuario, password) VALUES (?, ?)");
if (!$stmt) {
    header("Location: panel.php?staff=error");
    exit;
}

$stmt->bind_param("ss", $usuario, $hash);
if ($stmt->execute()) {
    $stmt->close();
    header("Location: panel.php?staff=created");
    exit;
}

$stmt->close();
header("Location: panel.php?staff=error");
exit;
