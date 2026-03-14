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
$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");
$precioInicial = (float) ($_POST["precio_inicial"] ?? 0);
$incrementoMinimo = (float) ($_POST["incremento_minimo"] ?? 0);
$categoriaId = (int) ($_POST["categoria_id"] ?? 0);
$estado = trim($_POST["estado"] ?? "activo");
$fechaInicioRaw = trim($_POST["fecha_inicio"] ?? "");
$fechaFinRaw = trim($_POST["fecha_fin"] ?? "");
$fechaExpiracionRaw = trim($_POST["fecha_expiracion"] ?? "");

if ($id <= 0 || $nombre === "" || $descripcion === "" || $precioInicial <= 0 || $categoriaId <= 0) {
    http_response_code(400);
    exit("Faltan datos requeridos.");
}

if (!in_array($estado, ["activo", "finalizado", "pausado"], true)) {
    http_response_code(400);
    exit("Estado no valido.");
}

$stmtCategoria = $mysqli->prepare("SELECT id FROM categorias WHERE id = ? LIMIT 1");
if ($stmtCategoria) {
    $stmtCategoria->bind_param("i", $categoriaId);
    $stmtCategoria->execute();
    $result = $stmtCategoria->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmtCategoria->close();
    if (!$row) {
        http_response_code(400);
        exit("Categoria no valida.");
    }
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

$hasExpiracion = false;
$checkExp = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_expiracion'");
if ($checkExp && $checkExp->num_rows > 0) {
    $hasExpiracion = true;
}

$selectIncremento = $hasIncremento ? ", incremento_minimo" : "";
$selectInicio = $hasInicio ? ", fecha_inicio" : "";
$selectFin = $hasFin ? ", fecha_fin" : "";
$selectExpiracion = $hasExpiracion ? ", fecha_expiracion" : "";
$stmtPrev = $mysqli->prepare(
    "SELECT nombre, descripcion, imagen_url, categoria_id, estado, $precioColumn AS precio" .
    "$selectIncremento$selectInicio$selectFin$selectExpiracion FROM productos WHERE id = ? LIMIT 1"
);
if (!$stmtPrev) {
    http_response_code(500);
    exit("No se pudo consultar el producto.");
}
$stmtPrev->bind_param("i", $id);
$stmtPrev->execute();
$resultPrev = $stmtPrev->get_result();
$productoActual = $resultPrev ? $resultPrev->fetch_assoc() : null;
$stmtPrev->close();
if (!$productoActual) {
    http_response_code(404);
    exit("Producto no encontrado.");
}

$fechaInicio = $hasInicio ? str_replace("T", " ", $fechaInicioRaw) : null;
$fechaFin = $hasFin ? str_replace("T", " ", $fechaFinRaw) : null;
$fechaExpiracion = ($hasExpiracion && $fechaExpiracionRaw !== "") ? str_replace("T", " ", $fechaExpiracionRaw) : null;

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

$imagenUrl = null;
if (isset($_FILES["imagen"]) && !empty($_FILES["imagen"]["name"][0])) {
    $maxSize = 5 * 1024 * 1024;
    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];
    $uploadDir = __DIR__ . "/../uploads/productos";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        exit("No se pudo crear carpeta.");
    }

    $imagenesGuardadas = [];
    $files = $_FILES["imagen"];
    $count = count($files["name"]);

    for ($i = 0; $i < $count; $i++) {
        if ($files["error"][$i] !== UPLOAD_ERR_OK) continue;
        if ($files["size"][$i] > $maxSize) continue;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($files["tmp_name"][$i]);
        if (!isset($allowed[$mime])) continue;

        $ext = $allowed[$mime];
        $filename = "producto_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "_" . $i . "." . $ext;
        $targetPath = $uploadDir . "/" . $filename;

        if (move_uploaded_file($files["tmp_name"][$i], $targetPath)) {
            $imagenesGuardadas[] = "uploads/productos/" . $filename;
        }
    }

    if (!empty($imagenesGuardadas)) {
        $imagenUrl = count($imagenesGuardadas) > 1 ? json_encode($imagenesGuardadas) : $imagenesGuardadas[0];
    }
}
$imagenAnterior = $productoActual["imagen_url"] ?? "";

if ($hasIncremento || $hasInicio || $hasFin || $hasExpiracion) {
    if ($imagenUrl !== null) {
        $stmt = $mysqli->prepare(
            "UPDATE productos SET nombre = ?, descripcion = ?, imagen_url = ?, $precioColumn = ?" .
            ($hasIncremento ? ", incremento_minimo = ?" : "") .
            ($hasInicio ? ", fecha_inicio = ?" : "") .
            ($hasFin ? ", fecha_fin = ?" : "") .
            ($hasExpiracion ? ", fecha_expiracion = ?" : "") .
            ", categoria_id = ?, estado = ? WHERE id = ?"
        );
        if (!$stmt) {
            http_response_code(500);
            exit("No se pudo preparar la actualizacion.");
        }
        $types = "sssd";
        $params = [$nombre, $descripcion, $imagenUrl, $precioInicial];
        if ($hasIncremento) {
            $types .= "d";
            $params[] = $incrementoMinimo;
        }
        if ($hasInicio) {
            $types .= "s";
            $params[] = $fechaInicio;
        }
        if ($hasFin) {
            $types .= "s";
            $params[] = $fechaFin;
        }
        if ($hasExpiracion) {
            $types .= "s";
            $params[] = $fechaExpiracion;
        }
        $types .= "isi";
        $params[] = $categoriaId;
        $params[] = $estado;
        $params[] = $id;
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $mysqli->prepare(
            "UPDATE productos SET nombre = ?, descripcion = ?, $precioColumn = ?" .
            ($hasIncremento ? ", incremento_minimo = ?" : "") .
            ($hasInicio ? ", fecha_inicio = ?" : "") .
            ($hasFin ? ", fecha_fin = ?" : "") .
            ($hasExpiracion ? ", fecha_expiracion = ?" : "") .
            ", categoria_id = ?, estado = ? WHERE id = ?"
        );
        if (!$stmt) {
            http_response_code(500);
            exit("No se pudo preparar la actualizacion.");
        }
        $types = "ssd";
        $params = [$nombre, $descripcion, $precioInicial];
        if ($hasIncremento) {
            $types .= "d";
            $params[] = $incrementoMinimo;
        }
        if ($hasInicio) {
            $types .= "s";
            $params[] = $fechaInicio;
        }
        if ($hasFin) {
            $types .= "s";
            $params[] = $fechaFin;
        }
        if ($hasExpiracion) {
            $types .= "s";
            $params[] = $fechaExpiracion;
        }
        $types .= "isi";
        $params[] = $categoriaId;
        $params[] = $estado;
        $params[] = $id;
        $stmt->bind_param($types, ...$params);
    }
} else {
    if ($imagenUrl !== null) {
        $stmt = $mysqli->prepare(
            "UPDATE productos SET nombre = ?, descripcion = ?, imagen_url = ?, $precioColumn = ?, categoria_id = ?, estado = ? WHERE id = ?"
        );
        if (!$stmt) {
            http_response_code(500);
            exit("No se pudo preparar la actualizacion.");
        }
        $stmt->bind_param("sssdssi", $nombre, $descripcion, $imagenUrl, $precioInicial, $categoriaId, $estado, $id);
    } else {
        $stmt = $mysqli->prepare(
            "UPDATE productos SET nombre = ?, descripcion = ?, $precioColumn = ?, categoria_id = ?, estado = ? WHERE id = ?"
        );
        if (!$stmt) {
            http_response_code(500);
            exit("No se pudo preparar la actualizacion.");
        }
        $stmt->bind_param("sdsisi", $nombre, $descripcion, $precioInicial, $categoriaId, $estado, $id);
    }
}

if (!$stmt->execute()) {
    http_response_code(500);
    exit("No se pudo actualizar el producto.");
}

$stmt->close();

function add_change(&$changes, $field, $before, $after)
{
    if ($before !== $after) {
        $changes[$field] = ["before" => $before, "after" => $after];
    }
}

$before = [
    "nombre" => $productoActual["nombre"] ?? "",
    "descripcion" => $productoActual["descripcion"] ?? "",
    "imagen_url" => $productoActual["imagen_url"] ?? "",
    "precio_inicial" => (float) ($productoActual["precio"] ?? 0),
    "incremento_minimo" => $hasIncremento ? (float) ($productoActual["incremento_minimo"] ?? 0) : null,
    "fecha_inicio" => $hasInicio ? ($productoActual["fecha_inicio"] ?? null) : null,
    "fecha_fin" => $hasFin ? ($productoActual["fecha_fin"] ?? null) : null,
    "fecha_expiracion" => $hasExpiracion ? ($productoActual["fecha_expiracion"] ?? null) : null,
    "categoria_id" => (int) ($productoActual["categoria_id"] ?? 0),
    "estado" => $productoActual["estado"] ?? ""
];
$after = [
    "nombre" => $nombre,
    "descripcion" => $descripcion,
    "imagen_url" => $imagenUrl !== null ? $imagenUrl : ($productoActual["imagen_url"] ?? ""),
    "precio_inicial" => $precioInicial,
    "incremento_minimo" => $hasIncremento ? $incrementoMinimo : null,
    "fecha_inicio" => $hasInicio ? $fechaInicio : null,
    "fecha_fin" => $hasFin ? $fechaFin : null,
    "fecha_expiracion" => $hasExpiracion ? $fechaExpiracion : null,
    "categoria_id" => $categoriaId,
    "estado" => $estado
];

$changes = [];
foreach ($before as $field => $oldValue) {
    $newValue = $after[$field] ?? null;
    add_change($changes, $field, $oldValue, $newValue);
}

if (!empty($changes)) {
    $usuarioId = $_SESSION["admin_id"] ?? null;
    $usuarioNombre = trim($_SESSION["admin_user"] ?? "");
    log_producto_historial(
        $mysqli,
        "editar",
        $id,
        $after["nombre"],
        $usuarioId,
        $usuarioNombre,
        ["changes" => $changes]
    );
}

if ($imagenUrl !== null && $imagenAnterior !== "" && str_starts_with($imagenAnterior, "uploads/productos/")) {
    // Intentar borrar las anteriores, ya sea 1 o JSON
    $antiguas = json_decode($imagenAnterior, true);
    if (!is_array($antiguas)) $antiguas = [$imagenAnterior];
    
    foreach($antiguas as $pathAntigua) {
        if (str_starts_with($pathAntigua, "uploads/productos/")) {
            $fullPath = __DIR__ . "/../" . $pathAntigua;
            if (is_file($fullPath)) unlink($fullPath);
        }
    }
}

header("Location: productos.php?updated=1");
exit;
