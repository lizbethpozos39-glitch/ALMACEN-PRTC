<?php

require_once __DIR__ . '/conexion.php';

function esc($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function moneda($valor)
{
    return '$' . number_format((float)$valor, 2, '.', ',');
}

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    die("Cotización no válida.");
}

/*
|--------------------------------------------------------------------------
| CLIENTES
|--------------------------------------------------------------------------
*/

$clientes = [];

$resultado = $conexion->query("
    SELECT id, nombre, razon_social, rfc
    FROM clientes
    WHERE estatus = 'ACTIVO'
    ORDER BY nombre ASC
");

if ($resultado) {

    while ($fila = $resultado->fetch_assoc()) {
        $clientes[] = $fila;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCTOS
|--------------------------------------------------------------------------
*/

$productos = [];

$resultado = $conexion->query("
    SELECT
        id,
        codigo,
        descripcion,
        tipo,
        unidad,
        precio,
        iva
    FROM productos
    WHERE activo = 1
    ORDER BY descripcion ASC
");

if ($resultado) {

    while ($fila = $resultado->fetch_assoc()) {
        $productos[] = $fila;
    }
}

/*
|--------------------------------------------------------------------------
| CARGAR COTIZACIÓN
|--------------------------------------------------------------------------
*/

$stmt = $conexion->prepare("
    SELECT *
    FROM cotizaciones
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

$cotizacion = $resultado->fetch_assoc();

$stmt->close();

if (!$cotizacion) {
    die("La cotización no existe.");
}

/*
|--------------------------------------------------------------------------
| CARGAR DETALLES
|--------------------------------------------------------------------------
*/

$detalles = [];

$stmt = $conexion->prepare("
    SELECT *
    FROM cotizacion_detalles
    WHERE cotizacion_id = ?
    ORDER BY orden ASC, id ASC
");

$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $detalles[] = $fila;
}

$stmt->close();

$mensaje = "";
$tipo_mensaje = "error";

/*
|--------------------------------------------------------------------------
| PROCESAR ACTUALIZACIÓN
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {

    $cliente_id = (int)($_POST["cliente_id"] ?? 0);
    $responsable = trim($_POST["responsable"] ?? "");
    $fecha = $_POST["fecha"] ?? date("Y-m-d");
    $vigencia = $_POST["vigencia"] ?? null;
    $descuento_general = (float)($_POST["descuento"] ?? 0);
    $estatus = $_POST["estatus"] ?? "BORRADOR";
    $observaciones = trim($_POST["observaciones"] ?? "");
    $condiciones = trim($_POST["condiciones"] ?? "");
    $fecha_seguimiento = $_POST["fecha_seguimiento"] ?? null;

    $producto_ids = $_POST["producto_id"] ?? [];
    $descripciones = $_POST["descripcion"] ?? [];
    $cantidades = $_POST["cantidad"] ?? [];
    $unidades = $_POST["unidad"] ?? [];
    $precios = $_POST["precio_unitario"] ?? [];
    $descuentos = $_POST["descuento_detalle"] ?? [];
    $ivas = $_POST["iva_porcentaje"] ?? [];

    if ($cliente_id <= 0) {

        $mensaje = "Debes seleccionar un cliente.";

    } else {

        $detalle_nuevos = [];

        $subtotal = 0;
        $iva_total = 0;

        $total_filas = count($descripciones);

        for ($i = 0; $i < $total_filas; $i++) {

            $descripcion = trim($descripciones[$i] ?? "");

            if ($descripcion === "") {
                continue;
            }

            $producto_id = (int)($producto_ids[$i] ?? 0);

            $cantidad =
                (float)($cantidades[$i] ?? 1);

            $unidad =
                trim($unidades[$i] ?? "PZA");

            $precio =
                (float)($precios[$i] ?? 0);

            $descuento_detalle =
                (float)($descuentos[$i] ?? 0);

            $iva_porcentaje =
                (float)($ivas[$i] ?? 0);

            if ($cantidad <= 0) {
                $cantidad = 1;
            }

            if ($precio < 0) {
                $precio = 0;
            }

            if ($descuento_detalle < 0) {
                $descuento_detalle = 0;
            }

            $importe_bruto =
                $cantidad * $precio;

            if ($descuento_detalle > $importe_bruto) {
                $descuento_detalle = $importe_bruto;
            }

            $importe =
                $importe_bruto - $descuento_detalle;

            $iva_importe =
                $importe * ($iva_porcentaje / 100);

            $subtotal += $importe;

            $iva_total += $iva_importe;

            $detalle_nuevos[] = [
                "producto_id" =>
                    $producto_id > 0 ? $producto_id : null,

                "descripcion" =>
                    $descripcion,

                "cantidad" =>
                    $cantidad,

                "unidad" =>
                    $unidad !== "" ? $unidad : "PZA",

                "precio_unitario" =>
                    $precio,

                "descuento" =>
                    $descuento_detalle,

                "iva_porcentaje" =>
                    $iva_porcentaje,

                "iva_importe" =>
                    $iva_importe,

                "importe" =>
                    $importe
            ];
        }

        if (empty($detalle_nuevos)) {

            $mensaje = "Debes agregar al menos un concepto válido.";

        } else {

            if ($descuento_general < 0) {
                $descuento_general = 0;
            }

            if ($descuento_general > $subtotal) {
                $descuento_general = $subtotal;
            }

            /*
            |--------------------------------------------------------------------------
            | RECALCULAR IVA CON DESCUENTO GENERAL
            |--------------------------------------------------------------------------
            */

            if ($subtotal > 0 && $descuento_general > 0) {

                $iva_total = 0;

                foreach ($detalle_nuevos as &$detalle) {

                    $proporcion =
                        $detalle["importe"] / $subtotal;

                    $descuento_proporcional =
                        $descuento_general * $proporcion;

                    $base =
                        $detalle["importe"] -
                        $descuento_proporcional;

                    $iva_total +=
                        $base *
                        ($detalle["iva_porcentaje"] / 100);
                }

                unset($detalle);
            }

            $total =
                ($subtotal - $descuento_general) +
                $iva_total;

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR
            |--------------------------------------------------------------------------
            */

            $conexion->begin_transaction();

            try {

                $stmt = $conexion->prepare("
                    UPDATE cotizaciones
                    SET
                        cliente_id = ?,
                        responsable = ?,
                        fecha = ?,
                        vigencia = ?,
                        subtotal = ?,
                        descuento = ?,
                        iva = ?,
                        total = ?,
                        estatus = ?,
                        observaciones = ?,
                        condiciones = ?,
                        fecha_seguimiento = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "isssddddssssi",
                    $cliente_id,
                    $responsable,
                    $fecha,
                    $vigencia,
                    $subtotal,
                    $descuento_general,
                    $iva_total,
                    $total,
                    $estatus,
                    $observaciones,
                    $condiciones,
                    $fecha_seguimiento,
                    $id
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "No fue posible actualizar la cotización."
                    );
                }

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | ELIMINAR DETALLES ANTERIORES
                |--------------------------------------------------------------------------
                */

                $stmt = $conexion->prepare("
                    DELETE FROM cotizacion_detalles
                    WHERE cotizacion_id = ?
                ");

                $stmt->bind_param("i", $id);

                if (!$stmt->execute()) {
                    throw new Exception(
                        "No fue posible actualizar los conceptos."
                    );
                }

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | INSERTAR DETALLES NUEVOS
                |--------------------------------------------------------------------------
                */

                $stmt = $conexion->prepare("
                    INSERT INTO cotizacion_detalles (
                        cotizacion_id,
                        producto_id,
                        descripcion,
                        cantidad,
                        unidad,
                        precio_unitario,
                        descuento,
                        iva_porcentaje,
                        iva_importe,
                        importe,
                        orden
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($detalle_nuevos as $indice => $detalle) {

                    $producto_id = $detalle["producto_id"];

                    $stmt->bind_param(
                        "iisdsdddddi",
                        $id,
                        $producto_id,
                        $detalle["descripcion"],
                        $detalle["cantidad"],
                        $detalle["unidad"],
                        $detalle["precio_unitario"],
                        $detalle["descuento"],
                        $detalle["iva_porcentaje"],
                        $detalle["iva_importe"],
                        $detalle["importe"],
                        $indice + 1
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            "No fue posible guardar los conceptos."
                        );
                    }
                }

                $stmt->close();

                $conexion->commit();

                header(
                    "Location: ver_cotizacion.php?id=" .
                    $id .
                    "&actualizada=1"
                );

                exit;

            } catch (Exception $e) {

                $conexion->rollback();

                $mensaje = $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Editar cotización <?= esc($cotizacion["folio"]) ?></title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #1f2937;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: #1f2937;
    color: white;
    overflow-y: auto;
}

.sidebar h2 {
    margin: 0;
    padding: 22px 20px;
    background: #111827;
}

.sidebar .seccion {
    padding: 15px 20px 7px;
    color: #9ca3af;
    font-size: 12px;
    text-transform: uppercase;
}

.sidebar a {
    display: block;
    padding: 12px 20px;
    color: #d1d5db;
    text-decoration: none;
    font-size: 14px;
}

.sidebar a:hover,
.sidebar a.activo {
    background: #2563eb;
    color: white;
}

.contenido {
    margin-left: 240px;
    padding: 30px;
}

.encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.encabezado h1 {
    margin: 0;
}

.panel {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 7px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.campo label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 6px;
}

.campo input,
.campo select,
.campo textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.columna-completa {
    grid-column: 1 / -1;
}

.detalles {
    overflow-x: auto;
}

#tablaDetalles {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

#tablaDetalles th,
#tablaDetalles td {
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
}

#tablaDetalles th {
    background: #f9fafb;
    font-size: 12px;
}

#tablaDetalles input,
#tablaDetalles select {
    width: 100%;
    padding: 8px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
}

.total-linea {
    text-align: right;
    font-weight: bold;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 6px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-success {
    background: #16a34a;
    color: white;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.mensaje {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    background: #fee2e2;
    color: #991b1b;
}

.resumen {
    display: flex;
    justify-content: flex-end;
}

.resumen-contenido {
    width: 350px;
}

.resumen-fila {
    display: flex;
    justify-content: space-between;
    padding: 8px;
}

.resumen-fila.total {
    border-top: 2px solid #111827;
    font-size: 20px;
    font-weight: bold;
}

.acciones-finales {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

@media(max-width: 1000px) {

    .grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 15px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .columna-completa {
        grid-column: auto;
    }
}

</style>

</head>

<body>

<div class="sidebar">

    <h2>Comercial</h2>

    <div class="seccion">Principal</div>

    <a href="index.php">Inicio</a>

    <div class="seccion">Clientes</div>

    <a href="clientes.php">Clientes</a>

    <div class="seccion">Productos y servicios</div>

    <a href="productos.php">Productos / Servicios</a>

    <div class="seccion">Ventas</div>

    <a href="cotizaciones.php" class="activo">Cotizaciones</a>

    <div class="seccion">Seguimiento</div>

    <a href="seguimientos.php">Seguimientos</a>

    <div class="seccion">Administración</div>

    <a href="reportes.php">Reportes</a>
    <a href="auditoria.php">Auditoría</a>

</div>

<div class="contenido">

    <div class="encabezado">

        <div>

            <h1>
                Editar <?= esc($cotizacion["folio"]) ?>
            </h1>

            <div>
                Puedes modificar todos los datos de la cotización.
            </div>

        </div>

        <div>

            <a
                href="ver_cotizacion.php?id=<?= $id ?>"
                class="btn btn-secondary"
            >
                Ver cotización
            </a>

            <a
                href="cotizaciones.php"
                class="btn btn-secondary"
            >
                Regresar
            </a>

        </div>

    </div>

    <?php if ($mensaje !== ""): ?>

        <div class="mensaje">
            <?= esc($mensaje) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="panel">

            <div class="grid">

                <div class="campo">

                    <label>Cliente *</label>

                    <select name="cliente_id" required>

                        <?php foreach ($clientes as $cliente): ?>

                            <option
                                value="<?= (int)$cliente["id"] ?>"
                                <?= $cotizacion["cliente_id"] == $cliente["id"] ? "selected" : "" ?>
                            >

                                <?= esc($cliente["nombre"]) ?>

                                <?php if (!empty($cliente["razon_social"])): ?>

                                    -
                                    <?= esc($cliente["razon_social"]) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label>Responsable</label>

                    <input
                        type="text"
                        name="responsable"
                        value="<?= esc($cotizacion["responsable"]) ?>"
                    >

                </div>

                <div class="campo">

                    <label>Fecha</label>

                    <input
                        type="date"
                        name="fecha"
                        value="<?= esc($cotizacion["fecha"]) ?>"
                        required
                    >

                </div>

                <div class="campo">

                    <label>Vigencia</label>

                    <input
                        type="date"
                        name="vigencia"
                        value="<?= esc($cotizacion["vigencia"]) ?>"
                    >

                </div>

                <div class="campo">

                    <label>Estatus</label>

                    <select name="estatus">

                        <?php

                        $estados = [
                            "BORRADOR" => "Borrador",
                            "ENVIADA" => "Enviada",
                            "EN_SEGUIMIENTO" => "En seguimiento",
                            "ACEPTADA" => "Aceptada",
                            "RECHAZADA" => "Rechazada",
                            "VENCIDA" => "Vencida"
                        ];

                        foreach ($estados as $valor => $texto):

                        ?>

                            <option
                                value="<?= $valor ?>"
                                <?= $cotizacion["estatus"] === $valor ? "selected" : "" ?>
                            >
                                <?= $texto ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label>Próximo seguimiento</label>

                    <input
                        type="date"
                        name="fecha_seguimiento"
                        value="<?= esc($cotizacion["fecha_seguimiento"]) ?>"
                    >

                </div>

            </div>

        </div>

        <div class="panel">

            <h2>Conceptos</h2>

            <div class="detalles">

                <table id="tablaDetalles">

                    <thead>

                        <tr>

                            <th>Producto / Servicio</th>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Precio</th>
                            <th>Desc. $</th>
                            <th>IVA %</th>
                            <th>Importe</th>
                            <th></th>

                        </tr>

                    </thead>

                    <tbody id="detalleBody">

                    <?php foreach ($detalles as $detalle): ?>

                        <tr class="fila-detalle">

                            <td>

                                <select
                                    name="producto_id[]"
                                    class="producto"
                                >

                                    <option value="">
                                        Seleccionar
                                    </option>

                                    <?php foreach ($productos as $producto): ?>

                                        <option
                                            value="<?= (int)$producto["id"] ?>"
                                            data-descripcion="<?= esc($producto["descripcion"]) ?>"
                                            data-unidad="<?= esc($producto["unidad"]) ?>"
                                            data-precio="<?= esc($producto["precio"]) ?>"
                                            data-iva="<?= esc($producto["iva"]) ?>"
                                            <?= ((int)$detalle["producto_id"] === (int)$producto["id"]) ? "selected" : "" ?>
                                        >

                                            <?= esc($producto["codigo"]) ?>
                                            -
                                            <?= esc($producto["descripcion"]) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>

                            <td>

                                <input
                                    type="text"
                                    name="descripcion[]"
                                    class="descripcion"
                                    value="<?= esc($detalle["descripcion"]) ?>"
                                    required
                                >

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="cantidad[]"
                                    class="cantidad"
                                    min="0.01"
                                    step="0.01"
                                    value="<?= esc($detalle["cantidad"]) ?>"
                                >

                            </td>

                            <td>

                                <input
                                    type="text"
                                    name="unidad[]"
                                    class="unidad"
                                    value="<?= esc($detalle["unidad"]) ?>"
                                >

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="precio_unitario[]"
                                    class="precio"
                                    min="0"
                                    step="0.01"
                                    value="<?= esc($detalle["precio_unitario"]) ?>"
                                >

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="descuento_detalle[]"
                                    class="descuento-detalle"
                                    min="0"
                                    step="0.01"
                                    value="<?= esc($detalle["descuento"]) ?>"
                                >

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="iva_porcentaje[]"
                                    class="iva"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?= esc($detalle["iva_porcentaje"]) ?>"
                                >

                            </td>

                            <td class="total-linea">
                                <?= moneda($detalle["importe"]) ?>
                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="btn btn-danger btn-eliminar"
                                >
                                    ×
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <br>

            <button
                type="button"
                class="btn btn-secondary"
                id="btnAgregar"
            >
                + Agregar concepto
            </button>

        </div>

        <div class="panel">

            <div class="grid">

                <div class="campo">

                    <label>Descuento general ($)</label>

                    <input
                        type="number"
                        name="descuento"
                        id="descuentoGeneral"
                        min="0"
                        step="0.01"
                        value="<?= esc($cotizacion["descuento"]) ?>"
                    >

                </div>

                <div class="campo columna-completa">

                    <label>Observaciones</label>

                    <textarea name="observaciones"><?= esc($cotizacion["observaciones"]) ?></textarea>

                </div>

                <div class="campo columna-completa">

                    <label>Condiciones comerciales</label>

                    <textarea name="condiciones"><?= esc($cotizacion["condiciones"]) ?></textarea>

                </div>

            </div>

        </div>

        <div class="panel">

            <div class="resumen">

                <div class="resumen-contenido">

                    <div class="resumen-fila">

                        <span>Subtotal:</span>

                        <strong id="resumenSubtotal">
                            $0.00
                        </strong>

                    </div>

                    <div class="resumen-fila">

                        <span>Descuento:</span>

                        <strong id="resumenDescuento">
                            $0.00
                        </strong>

                    </div>

                    <div class="resumen-fila">

                        <span>IVA:</span>

                        <strong id="resumenIva">
                            $0.00
                        </strong>

                    </div>

                    <div class="resumen-fila total">

                        <span>Total:</span>

                        <span id="resumenTotal">
                            $0.00
                        </span>

                    </div>

                </div>

            </div>

            <div class="acciones-finales">

                <a
                    href="ver_cotizacion.php?id=<?= $id ?>"
                    class="btn btn-secondary"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    name="guardar"
                    class="btn btn-success"
                >
                    Guardar cambios
                </button>

            </div>

        </div>

    </form>

</div>

<script>

const tablaBody = document.getElementById("detalleBody");

const btnAgregar = document.getElementById("btnAgregar");

const productosOptions = `
<option value="">Seleccionar</option>

<?php foreach ($productos as $producto): ?>

<option
    value="<?= (int)$producto["id"] ?>"
    data-descripcion="<?= htmlspecialchars($producto["descripcion"], ENT_QUOTES, 'UTF-8') ?>"
    data-unidad="<?= htmlspecialchars($producto["unidad"], ENT_QUOTES, 'UTF-8') ?>"
    data-precio="<?= htmlspecialchars($producto["precio"], ENT_QUOTES, 'UTF-8') ?>"
    data-iva="<?= htmlspecialchars($producto["iva"], ENT_QUOTES, 'UTF-8') ?>"
>
    <?= htmlspecialchars($producto["codigo"], ENT_QUOTES, 'UTF-8') ?>
    -
    <?= htmlspecialchars($producto["descripcion"], ENT_QUOTES, 'UTF-8') ?>
</option>

<?php endforeach; ?>
`;

function moneda(valor)
{
    return "$" + Number(valor || 0).toLocaleString("es-MX", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function recalcular()
{
    let subtotal = 0;
    let ivaTotal = 0;

    document.querySelectorAll(".fila-detalle").forEach(function(fila) {

        const cantidad =
            parseFloat(fila.querySelector(".cantidad").value) || 0;

        const precio =
            parseFloat(fila.querySelector(".precio").value) || 0;

        let descuento =
            parseFloat(
                fila.querySelector(".descuento-detalle").value
            ) || 0;

        const iva =
            parseFloat(fila.querySelector(".iva").value) || 0;

        const bruto =
            cantidad * precio;

        if (descuento > bruto) {
            descuento = bruto;
        }

        const importe =
            bruto - descuento;

        subtotal += importe;

        ivaTotal += importe * (iva / 100);

        fila.querySelector(".total-linea").textContent =
            moneda(importe);
    });

    let descuentoGeneral =
        parseFloat(
            document.getElementById("descuentoGeneral").value
        ) || 0;

    if (descuentoGeneral > subtotal) {
        descuentoGeneral = subtotal;
    }

    let ivaFinal = ivaTotal;

    if (subtotal > 0 && descuentoGeneral > 0) {

        ivaFinal = 0;

        document.querySelectorAll(".fila-detalle").forEach(function(fila) {

            const cantidad =
                parseFloat(fila.querySelector(".cantidad").value) || 0;

            const precio =
                parseFloat(fila.querySelector(".precio").value) || 0;

            let descuento =
                parseFloat(
                    fila.querySelector(".descuento-detalle").value
                ) || 0;

            const iva =
                parseFloat(fila.querySelector(".iva").value) || 0;

            const bruto = cantidad * precio;

            if (descuento > bruto) {
                descuento = bruto;
            }

            const importe = bruto - descuento;

            const proporcion = importe / subtotal;

            const descuentoProp =
                descuentoGeneral * proporcion;

            const base =
                importe - descuentoProp;

            ivaFinal += base * (iva / 100);
        });
    }

    const total =
        subtotal - descuentoGeneral + ivaFinal;

    document.getElementById("resumenSubtotal").textContent =
        moneda(subtotal);

    document.getElementById("resumenDescuento").textContent =
        moneda(descuentoGeneral);

    document.getElementById("resumenIva").textContent =
        moneda(ivaFinal);

    document.getElementById("resumenTotal").textContent =
        moneda(total);
}

function configurarFila(fila)
{
    fila.querySelector(".producto").addEventListener("change", function() {

        const opcion =
            this.options[this.selectedIndex];

        if (!opcion || !opcion.value) {
            return;
        }

        fila.querySelector(".descripcion").value =
            opcion.dataset.descripcion || "";

        fila.querySelector(".unidad").value =
            opcion.dataset.unidad || "PZA";

        fila.querySelector(".precio").value =
            opcion.dataset.precio || "0";

        fila.querySelector(".iva").value =
            opcion.dataset.iva || "16";

        recalcular();
    });

    fila.querySelectorAll("input").forEach(function(input) {
        input.addEventListener("input", recalcular);
    });

    fila.querySelector(".btn-eliminar").addEventListener("click", function() {

        const filas =
            document.querySelectorAll(".fila-detalle");

        if (filas.length <= 1) {
            alert("Debe existir al menos un concepto.");
            return;
        }

        fila.remove();

        recalcular();
    });
}

document.querySelectorAll(".fila-detalle").forEach(configurarFila);

btnAgregar.addEventListener("click", function() {

    const filaOriginal =
        document.querySelector(".fila-detalle");

    const nuevaFila =
        filaOriginal.cloneNode(true);

    nuevaFila.querySelector(".producto").innerHTML =
        productosOptions;

    nuevaFila.querySelector(".producto").value = "";

    nuevaFila.querySelector(".descripcion").value = "";

    nuevaFila.querySelector(".cantidad").value = "1";

    nuevaFila.querySelector(".unidad").value = "PZA";

    nuevaFila.querySelector(".precio").value = "0";

    nuevaFila.querySelector(".descuento-detalle").value = "0";

    nuevaFila.querySelector(".iva").value = "16";

    nuevaFila.querySelector(".total-linea").textContent =
        "$0.00";

    tablaBody.appendChild(nuevaFila);

    configurarFila(nuevaFila);

    recalcular();
});

document
    .getElementById("descuentoGeneral")
    .addEventListener("input", recalcular);

recalcular();

</script>

</body>
</html>