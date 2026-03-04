<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$hasRole = false;
$checkRole = $mysqli->query("SHOW COLUMNS FROM admin LIKE 'rol'");
if ($checkRole && $checkRole->num_rows > 0) {
    $hasRole = true;
}

$id = (int) ($_POST["id"] ?? 0);
$usuario = trim($_POST["usuario"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";
$rol = strtolower(trim($_POST["rol"] ?? "admin"));
if (!in_array($rol, ["admin", "operativo"], true)) {
    $rol = "admin";
}

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
    $sql = "UPDATE admin SET usuario = ?, password = ?";
    $types = "ss";
    $params = [$usuario, $hash];
    if ($hasRole) {
        $sql .= ", rol = ?";
        $types .= "s";
        $params[] = $rol;
    }
    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $id;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        header("Location: editar_personal.php?id=" . $id . "&error=error");
        exit;
    }
    $stmt->bind_param($types, ...$params);
} else {
    $sql = "UPDATE admin SET usuario = ?";
    $types = "s";
    $params = [$usuario];
    if ($hasRole) {
        $sql .= ", rol = ?";
        $types .= "s";
        $params[] = $rol;
    }
    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $id;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        header("Location: editar_personal.php?id=" . $id . "&error=error");
        exit;
    }
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $stmt->close();
    header("Location: panel.php?staff=updated");
    exit;
}

$stmt->close();
header("Location: editar_personal.php?id=" . $id . "&error=error");
exit;
