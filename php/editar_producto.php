<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit("ID invalido.");
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

$producto = null;
$selectIncremento = $hasIncremento ? ", incremento_minimo" : "";
$stmt = $mysqli->prepare("SELECT id, nombre, descripcion, imagen_url, categoria_id, estado, $precioColumn AS precio$selectIncremento FROM productos WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$producto) {
    http_response_code(404);
    exit("Producto no encontrado.");
}

$categorias = [];
$resultCategorias = $mysqli->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($resultCategorias) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

$img = $producto["imagen_url"] ?? "";
if ($img !== "" && $img[0] !== "/" && !preg_match("~^https?://~", $img)) {
    $img = "../" . $img;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Editar producto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
</head>
<body class="auth-page">
    <main class="auth">
        <section class="auth-card">
            <div class="auth-brand">
                <div class="brand-mark">A</div>
                <div>
                    <div class="brand-name">Administracion</div>
                    <div class="brand-tag">Editar producto</div>
                </div>
            </div>
            <h1>Editar producto</h1>
            <p class="lead">Actualiza la informacion del producto seleccionado.</p>
            <form class="auth-form" action="actualizar_producto.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo (int) $producto["id"]; ?>" />
                <label class="field">
                    <span>Nombre del producto</span>
                    <input name="nombre" type="text" required value="<?php echo htmlspecialchars($producto["nombre"] ?? ""); ?>" />
                </label>
                <label class="field">
                    <span>Descripcion</span>
                    <input name="descripcion" type="text" required value="<?php echo htmlspecialchars($producto["descripcion"] ?? ""); ?>" />
                </label>
                <label class="field">
                    <span>Imagen actual</span>
                    <?php if ($img !== "") { ?>
                        <img class="thumb" src="<?php echo htmlspecialchars($img); ?>" alt="" />
                    <?php } else { ?>
                        <span class="field-hint">No hay imagen cargada.</span>
                    <?php } ?>
                </label>
                <label class="field">
                    <span>Nueva imagen (opcional)</span>
                    <input name="imagen" type="file" accept="image/*" />
                </label>
                <label class="field">
                    <span>Precio inicial</span>
                    <input name="precio_inicial" type="number" min="0" step="0.01" required value="<?php echo htmlspecialchars($producto["precio"] ?? 0); ?>" />
                </label>
                <?php if ($hasIncremento) { ?>
                    <label class="field">
                        <span>Incremento minimo</span>
                        <input name="incremento_minimo" type="number" min="0" step="0.01" value="<?php echo htmlspecialchars($producto["incremento_minimo"] ?? 0); ?>" />
                    </label>
                <?php } ?>
                <label class="field">
                    <span>Categoria</span>
                    <select name="categoria_id" required>
                        <?php foreach ($categorias as $categoria) { ?>
                            <option value="<?php echo (int) $categoria["id"]; ?>" <?php echo ((int) $producto["categoria_id"] === (int) $categoria["id"]) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($categoria["nombre"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label class="field">
                    <span>Estado</span>
                    <select name="estado" required>
                        <option value="activo" <?php echo ($producto["estado"] ?? "") === "activo" ? "selected" : ""; ?>>Activo</option>
                        <option value="finalizado" <?php echo ($producto["estado"] ?? "") === "finalizado" ? "selected" : ""; ?>>Finalizado</option>
                    </select>
                </label>
                <button class="btn" type="submit">Guardar cambios</button>
            </form>
            <div class="switch">
                <a class="link" href="panel.php">Volver al panel</a>
            </div>
        </section>
        <aside class="auth-panel">
            <div class="panel-content">
                <h2>Edicion rapida</h2>
                <p>Puedes actualizar precios, categoria y estado del producto.</p>
                <div class="panel-stats">
                    <div>
                        <span>Producto</span>
                        <strong><?php echo htmlspecialchars($producto["nombre"] ?? ""); ?></strong>
                    </div>
                    <div>
                        <span>Estado actual</span>
                        <strong><?php echo htmlspecialchars($producto["estado"] ?? ""); ?></strong>
                    </div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
