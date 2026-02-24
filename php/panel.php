<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

$categorias = [];
$result = $mysqli->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
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

$productos = [];
$selectIncremento = $hasIncremento ? ", p.incremento_minimo" : "";
$queryProductos = "SELECT p.id, p.nombre, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria$selectIncremento FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.id DESC";
$resultProductos = $mysqli->query($queryProductos);
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Agregar producto</title>
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
                    <div class="brand-tag">Agregar producto</div>
                </div>
            </div>
            <h1>Nuevo producto</h1>
            <?php if (!empty($_GET["ok"])) { ?>
                <p class="lead">Producto guardado correctamente.</p>
            <?php } elseif (!empty($_GET["deleted"])) { ?>
                <p class="lead">Producto eliminado correctamente.</p>
            <?php } elseif (!empty($_GET["updated"])) { ?>
                <p class="lead">Producto actualizado correctamente.</p>
            <?php } else { ?>
                <p class="lead">Completa los datos para publicar una nueva subasta.</p>
            <?php } ?>
            <form class="auth-form" action="guardar_producto.php" method="post" enctype="multipart/form-data">
                <label class="field">
                    <span>Nombre del producto</span>
                    <input name="nombre" type="text" required placeholder="Ej. Paquete de viaje" />
                </label>
                <label class="field">
                    <span>Descripcion</span>
                    <input name="descripcion" type="text" required placeholder="Breve descripcion" />
                </label>
                <label class="field">
                    <span>Imagen del producto</span>
                    <input name="imagen" type="file" accept="image/*" required />
                    <small class="field-hint">Se subira al servidor y se guardara en la base de datos.</small>
                </label>
                <label class="field">
                    <span>Precio inicial</span>
                    <input name="precio_inicial" type="number" min="0" step="0.01" required placeholder="800" />
                </label>
                <label class="field">
                    <span>Incremento minimo</span>
                    <input name="incremento_minimo" type="number" min="1" step="1" required placeholder="100" />
                </label>
                <label class="field">
                    <span>Categoria</span>
                    <select name="categoria_id" required>
                        <option value="" disabled selected>Selecciona una categoria</option>
                        <?php foreach ($categorias as $categoria) { ?>
                            <option value="<?php echo (int) $categoria["id"]; ?>">
                                <?php echo htmlspecialchars($categoria["nombre"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <button class="btn" type="submit">Guardar producto</button>
            </form>
            <div class="admin-list">
                <h2 class="section-title">Productos existentes</h2>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Producto</th>
                                <th>Categoria</th>
                                <th>Precio inicial</th>
                                <?php if ($hasIncremento) { ?>
                                    <th>Incremento</th>
                                <?php } ?>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productos) === 0) { ?>
                                <tr>
                                    <td colspan="<?php echo $hasIncremento ? 7 : 6; ?>">No hay productos registrados.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($productos as $producto) { ?>
                                    <?php
                                        $img = $producto["imagen_url"] ?? "";
                                        if ($img !== "" && $img[0] !== "/" && !preg_match("~^https?://~", $img)) {
                                            $img = "../" . $img;
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($img !== "") { ?>
                                                <img class="thumb" src="<?php echo htmlspecialchars($img); ?>" alt="" />
                                            <?php } ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($producto["nombre"] ?? ""); ?></td>
                                        <td><?php echo htmlspecialchars($producto["categoria"] ?? "Sin categoria"); ?></td>
                                        <td>$<?php echo number_format((float) $producto["precio"], 2); ?></td>
                                        <?php if ($hasIncremento) { ?>
                                            <td>$<?php echo number_format((float) ($producto["incremento_minimo"] ?? 0), 2); ?></td>
                                        <?php } ?>
                                        <td><span class="status-tag"><?php echo htmlspecialchars($producto["estado"] ?? ""); ?></span></td>
                                        <td>
                                            <div class="action-row">
                                                <a class="btn btn-small btn-outline" href="editar_producto.php?id=<?php echo (int) $producto["id"]; ?>">Editar</a>
                                                <form action="eliminar_producto.php" method="post" onsubmit="return confirm('Seguro que quieres eliminar este producto?');">
                                                    <input type="hidden" name="id" value="<?php echo (int) $producto["id"]; ?>" />
                                                    <button class="btn btn-small" type="submit">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="switch">
                <a class="link" href="dashboard.php">Ir al dashboard</a>
                <span>·</span>
                <a class="link" href="subasta.php">Volver a subasta</a>
            </div>
        </section>
        <aside class="auth-panel">
            <div class="panel-content">
                <h2>Control administrativo</h2>
                <p>Este formulario se conecta al backend para guardar productos en la base de datos.</p>
                <div class="panel-stats">
                    <div>
                        <span>Precio inicial recomendado</span>
                        <strong>800+</strong>
                    </div>
                    <div>
                        <span>Incremento minimo</span>
                        <strong>100+</strong>
                    </div>
                    <div>
                        <span>Estado</span>
                        <strong>activo</strong>
                    </div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
