<?php
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

$productoId = (int) ($_POST["producto_id"] ?? 0);
$nombre = trim($_POST["nombre_usuario"] ?? "");
$correo = trim($_POST["correo_usuario"] ?? "");
$telefono = trim($_POST["telefono_usuario"] ?? "");
$monto = (float) ($_POST["monto_puja"] ?? 0);
$categoriaFiltro = (int) ($_POST["categoria"] ?? 0);

if ($productoId <= 0 || $nombre === "" || $correo === "" || $telefono === "" || $monto <= 0) {
    header("Location: producto.php?id=" . $productoId . "&status=error");
    exit;
}

$precioColumn = "predcio_inicial";
$checkPrecio = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'predcio_inicial'");
if ($checkPrecio && $checkPrecio->num_rows === 0) {
    $checkPrecioAlt = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'precio_inicial'");
    if ($checkPrecioAlt && $checkPrecioAlt->num_rows > 0) {
        $precioColumn = "precio_inicial";
    }
}

$hasIncremento = false;
$checkInc = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'incremento_minimo'");
if ($checkInc && $checkInc->num_rows > 0) {
    $hasIncremento = true;
}

$hasInicio = false;
$checkInicio = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_inicio'");
if ($checkInicio && $checkInicio->num_rows > 0) {
    $hasInicio = true;
}

$hasFin = false;
$checkFin = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_fin'");
if ($checkFin && $checkFin->num_rows > 0) {
    $hasFin = true;
}

$producto = null;
$selectIncremento = $hasIncremento ? ", incremento_minimo" : "";
$selectInicio = $hasInicio ? ", fecha_inicio" : "";
$selectFin = $hasFin ? ", fecha_fin" : "";
$stmtProducto = $mysqli->prepare("SELECT id, estado, $precioColumn AS precio$selectIncremento$selectInicio$selectFin FROM productos WHERE id = ? LIMIT 1");
if ($stmtProducto) {
    $stmtProducto->bind_param("i", $productoId);
    $stmtProducto->execute();
    $result = $stmtProducto->get_result();
    $producto = $result ? $result->fetch_assoc() : null;
    $stmtProducto->close();
}

if (!$producto || ($producto["estado"] ?? "") !== "activo") {
    header("Location: producto.php?id=" . $productoId . "&status=error");
    exit;
}

$inicio = $hasInicio && !empty($producto["fecha_inicio"]) ? new DateTime($producto["fecha_inicio"]) : null;
$fin = $hasFin && !empty($producto["fecha_fin"]) ? new DateTime($producto["fecha_fin"]) : null;
$ahora = new DateTime();
$activoTiempo = ($inicio === null || $ahora >= $inicio) && ($fin === null || $ahora <= $fin);
if (!$activoTiempo) {
    header("Location: producto.php?id=" . $productoId . "&status=error");
    exit;
}

$maxPuja = 0.0;
$stmtMax = $mysqli->prepare("SELECT MAX(monto_puja) AS max_puja FROM pujas WHERE producto_id = ?");
if ($stmtMax) {
    $stmtMax->bind_param("i", $productoId);
    $stmtMax->execute();
    $result = $stmtMax->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmtMax->close();
    if ($row && $row["max_puja"] !== null) {
        $maxPuja = (float) $row["max_puja"];
    }
}

$precioActual = (float) ($producto["precio"] ?? 0);
if ($maxPuja > $precioActual) {
    $precioActual = $maxPuja;
}

$incremento = $hasIncremento ? (float) ($producto["incremento_minimo"] ?? 0) : 0.0;
$minimo = $precioActual + $incremento;

if ($monto < $minimo) {
    header("Location: producto.php?id=" . $productoId . "&status=error");
    exit;
}

$stmtInsert = $mysqli->prepare("INSERT INTO pujas (producto_id, nombre_usuario, correo_usuario, telefono_usuario, monto_puja, fecha_puja) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmtInsert) {
    header("Location: producto.php?id=" . $productoId . "&status=error");
    exit;
}

$stmtInsert->bind_param("isssd", $productoId, $nombre, $correo, $telefono, $monto);
if (!$stmtInsert->execute()) {
    $stmtInsert->close();
    header("Location: producto.php?id=" . $productoId . "&status=error");
    exit;
}

$stmtInsert->close();

$redirect = "producto.php?id=" . $productoId . "&status=ok";
if ($categoriaFiltro > 0) {
    $redirect .= "&categoria=" . $categoriaFiltro;
}
header("Location: " . $redirect);
exit;
