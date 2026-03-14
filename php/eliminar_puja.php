<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    header("Location: panel.php?puja=error");
    exit;
}

$id = (int) ($_POST["id"] ?? 0);
$moneda = strtoupper($_POST["moneda"] ?? "MXN");
if (!in_array($moneda, ["MXN", "USD", "CAD"], true)) {
    $moneda = "MXN";
}

$redirectQuery = $moneda !== "MXN" ? "?moneda=" . urlencode($moneda) : "";

if ($id <= 0) {
    header("Location: panel.php?puja=error" . ($redirectQuery === "" ? "" : "&" . ltrim($redirectQuery, "?")));
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM pujas WHERE id = ? LIMIT 1");
if (!$stmt) {
    header("Location: panel.php?puja=error" . ($redirectQuery === "" ? "" : "&" . ltrim($redirectQuery, "?")));
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

$status = $deleted ? "deleted" : "error";
header("Location: panel.php?puja=" . $status . ($redirectQuery === "" ? "" : "&" . ltrim($redirectQuery, "?")));
exit;
