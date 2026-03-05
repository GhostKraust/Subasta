<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo no permitido.");
}

$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");
$precioInicial = (float) ($_POST["precio_inicial"] ?? 0);
$incrementoMinimo = (float) ($_POST["incremento_minimo"] ?? 0);
$categoriaId = (int) ($_POST["categoria_id"] ?? 0);
$fechaInicioRaw = trim($_POST["fecha_inicio"] ?? "");
$fechaFinRaw = trim($_POST["fecha_fin"] ?? "");

if ($nombre === "" || $descripcion === "" || $precioInicial <= 0 || $categoriaId <= 0) {
    http_response_code(400);
    exit("Faltan datos requeridos.");
}

if (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit("Error al subir imagen.");
}

$maxSize = 5 * 1024 * 1024;
if ($_FILES["imagen"]["size"] > $maxSize) {
    http_response_code(400);
    exit("La imagen supera el tamano permitido.");
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES["imagen"]["tmp_name"]);
$allowed = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];

if (!isset($allowed[$mime])) {
    http_response_code(400);
    exit("Formato de imagen no permitido.");
}

$ext = $allowed[$mime];
$uploadDir = __DIR__ . "/../uploads/productos";
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    http_response_code(500);
    exit("No se pudo crear la carpeta de subida.");
}

$filename = "producto_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
$targetPath = $uploadDir . "/" . $filename;
if (!move_uploaded_file($_FILES["imagen"]["tmp_name"], $targetPath)) {
    http_response_code(500);
    exit("No se pudo guardar la imagen.");
}

$imagenUrl = "uploads/productos/" . $filename;

 $stmtCategoria = $mysqli->prepare("SELECT id FROM categorias WHERE id = ? LIMIT 1");
 if ($stmtCategoria) {
     $stmtCategoria->bind_param("i", $categoriaId);
     $stmtCategoria->execute();
     $result = $stmtCategoria->get_result();
     $row = $result ? $result->fetch_assoc() : null;
     $stmtCategoria->close();
     if (!$row) {
         $categoriaId = 0;
     }
 }

if ($categoriaId <= 0) {
    http_response_code(400);
    exit("Categoria no valida.");
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

if (!$hasIncremento) {
    http_response_code(400);
    exit("Falta la columna incremento_minimo en productos.");
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

$fechaInicio = $hasInicio ? str_replace("T", " ", $fechaInicioRaw) : null;
$fechaFin = $hasFin ? str_replace("T", " ", $fechaFinRaw) : null;

if ($hasInicio && $fechaInicioRaw === "") {
    http_response_code(400);
    exit("Falta fecha de inicio.");
}

if ($hasFin && $fechaFinRaw === "") {
    http_response_code(400);
    exit("Falta fecha de fin.");
}

if ($hasInicio && $hasFin) {
    $inicioDt = DateTime::createFromFormat("Y-m-d H:i", $fechaInicio);
    $finDt = DateTime::createFromFormat("Y-m-d H:i", $fechaFin);
    if (!$inicioDt || !$finDt) {
        http_response_code(400);
        exit("Formato de fecha no valido.");
    }
    if ($inicioDt >= $finDt) {
        http_response_code(400);
        exit("La fecha y hora de fin debe ser posterior a la de inicio.");
    }
}

if ($hasIncremento || $hasInicio || $hasFin) {
    $columns = "nombre, descripcion, imagen_url, $precioColumn";
    $values = "?, ?, ?, ?";
    $types = "sssd";
    $params = [$nombre, $descripcion, $imagenUrl, $precioInicial];

    if ($hasIncremento) {
        $columns .= ", incremento_minimo";
        $values .= ", ?";
        $types .= "d";
        $params[] = $incrementoMinimo;
    }
    if ($hasInicio) {
        $columns .= ", fecha_inicio";
        $values .= ", ?";
        $types .= "s";
        $params[] = $fechaInicio;
    }
    if ($hasFin) {
        $columns .= ", fecha_fin";
        $values .= ", ?";
        $types .= "s";
        $params[] = $fechaFin;
    }

    $columns .= ", categoria_id, estado";
    $values .= ", ?, 'activo'";
    $types .= "i";
    $params[] = $categoriaId;

    $stmt = $mysqli->prepare("INSERT INTO productos ($columns) VALUES ($values)");
    if (!$stmt) {
        http_response_code(500);
        exit("No se pudo preparar el guardado.");
    }
    $stmt->bind_param($types, ...$params);
} else {
    $stmt = $mysqli->prepare(
        "INSERT INTO productos (nombre, descripcion, imagen_url, $precioColumn, categoria_id, estado) VALUES (?, ?, ?, ?, ?, 'activo')"
    );
    if (!$stmt) {
        http_response_code(500);
        exit("No se pudo preparar el guardado.");
    }
    $stmt->bind_param("sssdi", $nombre, $descripcion, $imagenUrl, $precioInicial, $categoriaId);
}

if (!$stmt->execute()) {
    http_response_code(500);
    exit("No se pudo guardar el producto.");
}

$stmt->close();
header("Location: productos.php?ok=1");
exit;
