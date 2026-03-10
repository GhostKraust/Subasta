<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$historial = [];
$filtroAdmin = trim($_GET["admin"] ?? "");
$filtroDesde = trim($_GET["desde"] ?? "");
$filtroHasta = trim($_GET["hasta"] ?? "");

$conditions = [];
$params = [];
$types = "";

if ($filtroAdmin !== "") {
    $conditions[] = "usuario_nombre LIKE ?";
    $params[] = "%" . $filtroAdmin . "%";
    $types .= "s";
}

if ($filtroDesde !== "") {
    $conditions[] = "created_at >= ?";
    $params[] = $filtroDesde . " 00:00:00";
    $types .= "s";
}

if ($filtroHasta !== "") {
    $conditions[] = "created_at <= ?";
    $params[] = $filtroHasta . " 23:59:59";
    $types .= "s";
}

$sql = "SELECT id, producto_id, producto_nombre, accion, usuario_id, usuario_nombre, cambios, created_at FROM historial_productos";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $historial[] = $row;
        }
    }
    $stmt->close();
}

function display_value($value)
{
    if ($value === null || $value === "") {
        return "-";
    }
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    return (string) $value;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Historial de productos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
    <link href="../css/dashboard.css" rel="stylesheet" />
</head>
<body class="auth-page products-page">
    <main class="auth admin-layout">
        <div class="panel-actions">
            <a class="btn btn-compact ghost" href="productos.php">Volver a productos</a>
            <a class="btn btn-compact ghost" href="dashboard.php">Inicio</a>
        </div>
        <section class="auth-card admin-products">
            <div class="section-header">
                <h2 class="section-title">Historial de productos</h2>
            </div>
            <form class="history-filters" method="get">
                <label class="field">
                    <span>Admin</span>
                    <input name="admin" type="text" placeholder="Nombre del admin" value="<?php echo htmlspecialchars($filtroAdmin); ?>" />
                </label>
                <label class="field">
                    <span>Desde</span>
                    <input name="desde" type="date" value="<?php echo htmlspecialchars($filtroDesde); ?>" />
                </label>
                <label class="field">
                    <span>Hasta</span>
                    <input name="hasta" type="date" value="<?php echo htmlspecialchars($filtroHasta); ?>" />
                </label>
                <div class="history-actions">
                    <button class="btn btn-compact" type="submit">Buscar</button>
                    <a class="btn btn-compact ghost" href="historial_productos.php">Limpiar</a>
                </div>
            </form>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Accion</th>
                            <th>Usuario</th>
                            <th>Cambios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historial) === 0) { ?>
                            <tr>
                                <td colspan="5">Sin registros.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($historial as $row) { ?>
                                <?php
                                    $cambios = $row["cambios"] ? json_decode($row["cambios"], true) : null;
                                    $changeList = $cambios["changes"] ?? [];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["created_at"] ?? ""); ?></td>
                                    <td><?php echo htmlspecialchars($row["producto_nombre"] ?? "#" . (int) $row["producto_id"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["accion"] ?? ""); ?></td>
                                    <td><?php echo htmlspecialchars($row["usuario_nombre"] ?? ""); ?></td>
                                    <td>
                                        <?php if (empty($changeList)) { ?>
                                            <span class="field-hint">-</span>
                                        <?php } else { ?>
                                            <div class="history-changes">
                                                <?php foreach ($changeList as $field => $pair) { ?>
                                                    <?php
                                                        $before = $pair["before"] ?? null;
                                                        $after = $pair["after"] ?? null;
                                                    ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($field); ?>:</strong>
                                                        <?php echo htmlspecialchars(display_value($before)); ?> -> <?php echo htmlspecialchars(display_value($after)); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
