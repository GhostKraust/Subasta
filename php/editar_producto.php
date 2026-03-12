<?php
require_once __DIR__ . "/auth.php";
require_admin();
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

$producto = null;
$selectIncremento = $hasIncremento ? ", incremento_minimo" : "";
$selectInicio = $hasInicio ? ", fecha_inicio" : "";
$selectFin = $hasFin ? ", fecha_fin" : "";
$selectExpiracion = $hasExpiracion ? ", fecha_expiracion" : "";
$stmt = $mysqli->prepare("SELECT id, nombre, descripcion, imagen_url, categoria_id, estado, $precioColumn AS precio$selectIncremento$selectInicio$selectFin$selectExpiracion FROM productos WHERE id = ? LIMIT 1");
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
$imagenes = json_decode($img, true);
if (!is_array($imagenes)) {
    $imagenes = [$img];
}
$imagenes = array_map(function($path) {
    return ($path !== "" && $path[0] !== "/" && !preg_match("~^https?://~", $path)) ? "../" . $path : $path;
}, $imagenes);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="auth-page products-page">
    <main class="auth admin-layout">
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
                    <?php if (!empty($imagenes) && !empty($imagenes[0])) { ?>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach ($imagenes as $imagen) { ?>
                                <img class="thumb" src="<?php echo htmlspecialchars($imagen); ?>" alt="" />
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <span class="field-hint">No hay imagen cargada.</span>
                    <?php } ?>
                </label>
                <label class="field">
                    <span>Nueva imagen (opcional)</span>
                    <input name="imagen[]" type="file" accept="image/*" multiple />
                    <small class="field-hint">Selecciona una o varias. Reemplazarán a las actuales.</small>
                </label>
                <label class="field">
                    <span>Precio inicial</span>
                    <input name="precio_inicial" type="number" min="0" step="0.01" required value="<?php echo htmlspecialchars($producto["precio"] ?? 0); ?>" />
                </label>
                <label class="field">
                    <span>Incremento minimo</span>
                    <input name="incremento_minimo" type="number" min="0" step="0.01" value="<?php echo htmlspecialchars($producto["incremento_minimo"] ?? 0); ?>" />
                    <?php if (!$hasIncremento) { ?>
                        <small class="field-hint">Agrega la columna incremento_minimo en la tabla productos.</small>
                    <?php } ?>
                </label>
                <label class="field">
                    <span>Fecha de expiración (Validez)</span>
                    <input name="fecha_expiracion" type="datetime-local" value="<?php echo (!empty($producto["fecha_expiracion"])) ? date("Y-m-d\TH:i", strtotime($producto["fecha_expiracion"])) : ""; ?>" />
                </label>
                <?php if ($hasInicio) { ?>
                    <?php $inicioValor = !empty($producto["fecha_inicio"]) ? date("Y-m-d\TH:i", strtotime($producto["fecha_inicio"])) : ""; ?>
                    <label class="field">
                        <span>Fecha y hora de inicio</span>
                        <input name="fecha_inicio" type="datetime-local" required value="<?php echo htmlspecialchars($inicioValor); ?>" />
                    </label>
                <?php } ?>
                <?php if ($hasFin) { ?>
                    <?php $finValor = !empty($producto["fecha_fin"]) ? date("Y-m-d\TH:i", strtotime($producto["fecha_fin"])) : ""; ?>
                    <label class="field">
                        <span>Fecha y hora de fin</span>
                        <input name="fecha_fin" type="datetime-local" required value="<?php echo htmlspecialchars($finValor); ?>" />
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
                        <option value="pausado" <?php echo ($producto["estado"] ?? "") === "pausado" ? "selected" : ""; ?>>Pausado</option>
                        <option value="finalizado" <?php echo ($producto["estado"] ?? "") === "finalizado" ? "selected" : ""; ?>>Finalizado</option>
                    </select>
                </label>
                <button class="btn" type="submit">Guardar cambios</button>
            </form>
            <div class="switch">
                <a class="link" href="productos.php">Volver a productos</a>
            </div>
        </section>
        <aside class="product-preview-wrapper">
            <h3 class="preview-title">Vista previa</h3>
            <div class="preview-card">
                <div class="preview-image-box">
                    <?php if (count($imagenes) > 1) { ?>
                        <div id="carousel-preview-desktop-init" class="carousel slide carousel-dark h-100" data-bs-ride="carousel">
                            <div class="carousel-inner h-100">
                                <?php foreach ($imagenes as $index => $imagen) { ?>
                                    <div class="carousel-item h-100 <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($imagen); ?>" class="d-block w-100 h-100" style="object-fit: cover;" alt="Vista previa">
                                    </div>
                                <?php } ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carousel-preview-desktop-init" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carousel-preview-desktop-init" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                    <?php } else { ?>
                        <img id="preview-image-desktop" src="<?php echo htmlspecialchars($imagenes[0] ?? ''); ?>" alt="Vista previa" style="<?php echo !empty($imagenes[0]) ? '' : 'display:none;'; ?>" />
                    <?php } ?>
                    <div id="preview-placeholder-desktop" style="width:100%; height:100%; display:grid; place-items:center; color:#94a3b8; font-weight:600; <?php echo !empty($imagenes[0]) ? 'display:none;' : ''; ?>">Sin imagen</div>
                    <div id="preview-category-desktop" class="preview-category-tag">Categoria</div>
                </div>
                <div class="preview-info">
                    <h2 id="preview-name-desktop" class="preview-product-title">Nombre del Producto</h2>
                    <p id="preview-description-desktop" class="preview-product-desc">Aquí va la descripción del producto que estás agregando.</p>
                    <div class="preview-prices">
                        <div class="preview-price-block">
                            <span class="preview-price-label">Precio inicial</span>
                            <span id="preview-price-desktop" class="preview-price-value">MXN $0.00</span>
                        </div>
                        <div class="preview-price-block">
                            <span class="preview-price-label">Puja actual</span>
                            <span id="preview-price-current-desktop" class="preview-price-value highlight">MXN $0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </main>
    <!-- Cropper Modal -->
    <div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropperModalLabel">Ajustar Imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cropper-modal-img-container">
                        <img id="cropperImage" src="" alt="Imagen para recortar">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn" id="cropButton">Recortar y Usar</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
    (function() {
        // Form inputs
        const nombreInput = document.querySelector('input[name="nombre"]');
        const descripcionInput = document.querySelector('input[name="descripcion"]');
        const imagenInput = document.querySelector('input[name="imagen[]"]');
        const precioInput = document.querySelector('input[name="precio_inicial"]');
        const categoriaSelect = document.querySelector('select[name="categoria_id"]');

        // Desktop Preview elements - Seleccionamos el contenedor padre porque podemos reemplazar la img por el carrusel
        const imageBoxDesktop = document.querySelector('.product-preview-wrapper .preview-image-box');
        const previewCategoryDesktop = document.getElementById('preview-category-desktop');
        const previewNameDesktop = document.getElementById('preview-name-desktop');
        const previewDescriptionDesktop = document.getElementById('preview-description-desktop');
        const previewPriceDesktop = document.getElementById('preview-price-desktop');
        const previewPriceCurrentDesktop = document.getElementById('preview-price-current-desktop');
        const previewPlaceholderDesktop = document.getElementById('preview-placeholder-desktop');
        // Guardamos el contenido inicial para restaurar si cancelan
        const initialContentDesktop = imageBoxDesktop.innerHTML;

        // Mobile Preview elements (using desktop preview since modal was removed)
        const imageBoxMobile = imageBoxDesktop;
        const previewCategoryMobile = document.getElementById('preview-category-desktop');
        const previewNameMobile = document.getElementById('preview-name-desktop');
        const previewDescriptionMobile = document.getElementById('preview-description-desktop');
        const previewPriceMobile = document.getElementById('preview-price-desktop');
        const previewPriceCurrentMobile = document.getElementById('preview-price-current-desktop');
        const previewPlaceholderMobile = document.getElementById('preview-placeholder-mobile');

        function updatePreview() {
            const name = nombreInput.value || 'Nombre del Producto';
            const desc = descripcionInput.value || 'Aquí va la descripción del producto que estás agregando.';
            const priceValue = parseFloat(precioInput.value) || 0;
            const formattedPrice = `MXN $${priceValue.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const selectedCategory = categoriaSelect.options[categoriaSelect.selectedIndex];
            const category = selectedCategory ? (selectedCategory.text || 'Categoría') : 'Categoría';

            // Update Desktop
            previewNameDesktop.textContent = name;
            previewDescriptionDesktop.textContent = desc;
            previewPriceDesktop.textContent = formattedPrice;
            previewPriceCurrentDesktop.textContent = formattedPrice;
            const catDesktop = document.getElementById('preview-category-desktop');
            if (catDesktop) catDesktop.textContent = category;

            // Update Mobile
            previewNameMobile.textContent = name;
            previewDescriptionMobile.textContent = desc;
            previewPriceMobile.textContent = formattedPrice;
            previewPriceCurrentMobile.textContent = formattedPrice;
            const catMobile = document.getElementById('preview-category-mobile');
            if (catMobile) catMobile.textContent = category;
        }

        [nombreInput, descripcionInput, precioInput].forEach(el => el.addEventListener('input', updatePreview));
        categoriaSelect.addEventListener('change', updatePreview);

        updatePreview();

        // --- Cropper Logic ---
        const cropperModalEl = document.getElementById('cropperModal');
        const cropperModal = new bootstrap.Modal(cropperModalEl);
        const cropperImage = document.getElementById('cropperImage');
        const cropButton = document.getElementById('cropButton');
        const cropperModalLabel = document.getElementById('cropperModalLabel');
        let cropper;

        cropperModalEl.addEventListener('shown.bs.modal', function() {
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropperImage, {
                aspectRatio: 4 / 3,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        });

        cropperModalEl.addEventListener('hidden.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });

        let filesToCrop = [];
        let croppedBlobs = [];
        let currentCropIndex = 0;

        function loadCropperForCurrentFile() {
            const file = filesToCrop[currentCropIndex];
            const reader = new FileReader();
            reader.onload = function(event) {
                if (cropper) {
                    cropper.replace(event.target.result);
                } else {
                    cropperImage.src = event.target.result;
                }
                if (cropperModalLabel) {
                    cropperModalLabel.textContent = `Ajustar Imagen (${currentCropIndex + 1} de ${filesToCrop.length})`;
                }
                if (currentCropIndex === 0) {
                    cropperModal.show();
                }
            };
            reader.readAsDataURL(file);
        }

        function finalizeCropping() {
            cropperModal.hide();
            const dataTransfer = new DataTransfer();
            croppedBlobs.forEach((blob, index) => {
                const file = new File([blob], `cropped_image_${index}.jpg`, { type: 'image/jpeg' });
                dataTransfer.items.add(file);
            });
            imagenInput.files = dataTransfer.files;
            updatePreviewWithCroppedImages();
        }

        function updatePreviewWithCroppedImages() {
            const buildCarousel = (targetId, categoryId) => {
                let innerHTML = `<div id="${targetId}" class="carousel slide carousel-dark h-100" data-bs-ride="carousel" data-bs-interval="3000"><div class="carousel-inner h-100">`;
                croppedBlobs.forEach((blob, index) => {
                    const url = URL.createObjectURL(blob);
                    innerHTML += `<div class="carousel-item h-100 ${index === 0 ? 'active' : ''}"><img src="${url}" class="d-block w-100 h-100" style="object-fit: cover;" alt="Vista previa ${index + 1}"></div>`;
                });
                innerHTML += `</div><button class="carousel-control-prev" type="button" data-bs-target="#${targetId}" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button><button class="carousel-control-next" type="button" data-bs-target="#${targetId}" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span></button><div id="${categoryId}" class="preview-category-tag">Categoria</div></div>`;
                return innerHTML;
            };
            const imageBoxDesktop = document.querySelector('.product-preview-wrapper .preview-image-box');
            const imageBoxMobile = imageBoxDesktop; // Using desktop only since modal was removed
            if (croppedBlobs.length > 1) {
                imageBoxDesktop.innerHTML = buildCarousel('carousel-preview-desktop', 'preview-category-desktop');
                
                // Inicializar carrusel manualmente para que funcione al instante
                new bootstrap.Carousel(document.getElementById('carousel-preview-desktop'));
            } else if (croppedBlobs.length === 1) {
                const url = URL.createObjectURL(croppedBlobs[0]);
                imageBoxDesktop.innerHTML = `<img id="preview-image-desktop" src="${url}" alt="Vista previa" style="display: block;" /><div id="preview-placeholder-desktop" style="display:none;">Sin imagen</div><div id="preview-category-desktop" class="preview-category-tag">Categoria</div>`;
            }
            updatePreview();
        }

        imagenInput.addEventListener('change', function(e) {
            filesToCrop = Array.from(e.target.files);
            if (filesToCrop.length > 0) {
                currentCropIndex = 0;
                croppedBlobs = [];
                loadCropperForCurrentFile();
            }
        });

        cropButton.addEventListener('click', function() {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas({ width: 800, height: 600, imageSmoothingQuality: 'high' });
            canvas.toBlob(function(blob) {
                croppedBlobs.push(blob);
                currentCropIndex++;
                if (currentCropIndex < filesToCrop.length) {
                    loadCropperForCurrentFile();
                } else {
                    finalizeCropping();
                }
            }, 'image/jpeg', 0.9);
        });

        const fpConfig = {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "j F, Y h:i K",
            locale: "es",
            minuteIncrement: 1,
        };

        const editInicio = document.querySelector('input[name="fecha_inicio"]');
        const editFin = document.querySelector('input[name="fecha_fin"]');

        if (editInicio) {
            const fpInicio = flatpickr(editInicio, {
                ...fpConfig,
                minDate: "today",
                onChange: function(selectedDates, dateStr) {
                    if (fpFin) fpFin.set('minDate', dateStr);
                }
            });
            const fpFin = editFin ? flatpickr(editFin, fpConfig) : null;
        }
    })();
    </script>
</body>
</html>