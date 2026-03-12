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
    <style>
        /* --- DISEÑO AISLADO PARA HISTORIAL --- */
        .history-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .history-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
            padding: 30px;
            overflow: hidden; /* Evita desbordes del contenedor */
        }

        /* Estilos propios para la tabla de historial (independiente de admin-table) */
        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            font-size: 0.9rem;
            table-layout: auto; /* Permite que las columnas se adapten */
        }

        .history-table th {
            text-align: left;
            padding: 16px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }

        .history-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }

        /* Columnas especificas */
        .col-date { width: 140px; white-space: nowrap; }
        .col-action { width: 120px; }
        .col-user { width: 180px; }
        
        /* Manejo de textos largos en cambios */
        .changes-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .change-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            word-break: break-word; /* CRUCIAL: Rompe palabras largas */
            overflow-wrap: break-word;
        }

        .change-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .change-diff {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        /* Colores de diff */
        .val-old { text-decoration: line-through; color: #94a3b8; background: rgba(0,0,0,0.03); padding: 2px 6px; border-radius: 4px; }
        .val-new { color: #15803d; background: #dcfce7; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
        .arrow { color: #cbd5e1; }

        /* Badges de estado */
        .status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-default { background: #f1f5f9; color: #475569; }

        /* Avatar */
        .user-cell { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #64748b; display: grid; place-items: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }

        /* Filtros limpios */
        .history-filters { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9; align-items: flex-end; }
        .history-filters .field { flex: 1; min-width: 200px; }
        .history-filters input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .history-actions { display: flex; gap: 10px; }

        /* Responsive Cards para Movil */
        @media (max-width: 900px) {
            .history-table thead { display: none; }
            .history-table, .history-table tbody, .history-table tr, .history-table td { display: block; width: 100%; }
            .history-table tr { margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
            .history-table td { padding: 14px; text-align: right; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8fafc; }
            .history-table td::before { content: attr(data-label); float: left; font-weight: 700; color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; }
            
            /* Ajuste para columna de cambios en movil */
            .history-table td[data-label="Cambios"] { display: block; text-align: left; }
            .history-table td[data-label="Cambios"]::before { display: block; margin-bottom: 10px; }
            .col-date, .col-action, .col-user { width: auto; }
        }
    </style>
</head>
<body class="auth-page">
    <main class="history-container">
        <div class="panel-actions">
            <a class="btn btn-compact ghost" href="productos.php">Volver a productos</a>
            <a class="btn btn-compact ghost" href="dashboard.php">Inicio</a>
        </div>
        <section class="history-card">
            <div class="section-header">
                <h2 class="section-title">Historial de productos</h2>
            </div>
            <form class="history-filters" method="get">
                <label class="field">
                    <span>Usuario (Admin)</span>
                    <input name="admin" type="text" placeholder="Nombre del admin" value="<?php echo htmlspecialchars($filtroAdmin); ?>" />
                </label>
                <label class="field">
                    <span>Fecha Desde</span>
                    <input name="desde" type="date" value="<?php echo htmlspecialchars($filtroDesde); ?>" />
                </label>
                <label class="field">
                    <span>Fecha Hasta</span>
                    <input name="hasta" type="date" value="<?php echo htmlspecialchars($filtroHasta); ?>" />
                </label>
                <div class="history-actions">
                    <button class="btn btn-compact" type="submit">Buscar</button>
                    <a class="btn btn-compact ghost" href="historial_productos.php">Limpiar</a>
                </div>
            </form>
            <div class="table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th class="col-date">Fecha</th>
                            <th class="col-product">Producto</th>
                            <th class="col-action">Accion</th>
                            <th class="col-user">Usuario</th>
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
                                    
                                    // Determinar color del badge segun accion
                                    $accion = strtolower($row["accion"] ?? "");
                                    $badgeClass = "badge-default";
                                    if (in_array($accion, ['crear', 'reactivar', 'reanudar'])) $badgeClass = "badge-success";
                                    elseif ($accion === 'editar') $badgeClass = "badge-info";
                                    elseif (in_array($accion, ['eliminar', 'retirar'])) $badgeClass = "badge-danger";
                                    elseif ($accion === 'pausar') $badgeClass = "badge-warning";
                                ?>
                                <tr>
                                    <td data-label="Fecha" class="col-date">
                                        <div class="date-cell">
                                            <span class="date"><?php echo date("d/m/Y", strtotime($row["created_at"])); ?></span>
                                            <span class="time"><?php echo date("H:i", strtotime($row["created_at"])); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Producto" class="col-product">
                                        <div class="product-cell">
                                            <strong class="product-name"><?php echo htmlspecialchars($row["producto_nombre"] ?? ""); ?></strong>
                                            <span class="product-id">ID: <?php echo (int) $row["producto_id"]; ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Accion" class="col-action"><span class="status-badge <?php echo $badgeClass; ?>"><?php echo ucfirst($accion); ?></span></td>
                                    <td data-label="Usuario" class="col-user">
                                        <div class="user-cell">
                                            <div class="user-avatar"><?php echo strtoupper(substr($row["usuario_nombre"] ?? "S", 0, 1)); ?></div>
                                            <span><?php echo htmlspecialchars($row["usuario_nombre"] ?? "Sistema"); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Cambios" class="changes-col">
                                        <?php if (empty($changeList)) { ?>
                                            <span class="no-changes">Sin detalles</span>
                                        <?php } else { ?>
                                            <div class="changes-list">
                                                <?php foreach ($changeList as $field => $pair) { ?>
                                                    <?php
                                                        $before = $pair["before"] ?? null;
                                                        $after = $pair["after"] ?? null;
                                                        $fieldLabel = ucfirst(str_replace('_', ' ', $field));
                                                    ?>
                                                    <div class="change-item">
                                                        <span class="change-label"><?php echo htmlspecialchars($fieldLabel); ?></span>
                                                        <div class="change-diff">
                                                            <?php if ($before !== null && $before !== "") { ?>
                                                                <span class="val-old"><?php echo htmlspecialchars(display_value($before)); ?></span>
                                                                <span class="arrow">&rarr;</span>
                                                            <?php } ?>
                                                            <span class="val-new"><?php echo htmlspecialchars(display_value($after)); ?></span>
                                                        </div>
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
