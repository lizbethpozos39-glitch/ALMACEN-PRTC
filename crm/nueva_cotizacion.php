<?php

require_once __DIR__ . '/conexion.php';

function esc($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$mensaje = "";
$tipo_mensaje = "error";

$clientes = [];
$productos = [];

/*
|--------------------------------------------------------------------------
| CLIENTES
|--------------------------------------------------------------------------
*/

$resultado_clientes = $conexion->query("
    SELECT id, nombre, razon_social, rfc
    FROM clientes
    WHERE estatus = 'ACTIVO'
    ORDER BY nombre ASC
");

if ($resultado_clientes) {

    while ($fila = $resultado_clientes->fetch_assoc()) {
        $clientes[] = $fila;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCTOS
|--------------------------------------------------------------------------
*/

$resultado_productos = $conexion->query("
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

if ($resultado_productos) {

    while ($fila = $resultado_productos->fetch_assoc()) {
        $productos[] = $fila;
    }
}

/*
|--------------------------------------------------------------------------
| DATOS INICIALES
|--------------------------------------------------------------------------
*/

$cliente_id = (int)($_POST["cliente_id"] ?? 0);
$responsable = trim($_POST["responsable"] ?? "");
$fecha = $_POST["fecha"] ?? date("Y-m-d");
$vigencia = $_POST["vigencia"] ?? date("Y-m-d", strtotime("+15 days"));
$descuento_general = (float)($_POST["descuento"] ?? 0);
$estatus = $_POST["estatus"] ?? "BORRADOR";
$observaciones = trim($_POST["observaciones"] ?? "");
$condiciones = trim($_POST["condiciones"] ?? "");
$fecha_seguimiento = $_POST["fecha_seguimiento"] ?? "";

$filas = $_POST["producto_id"] ?? [""];
$descripciones = $_POST["descripcion"] ?? [""];
$cantidades = $_POST["cantidad"] ?? ["1"];
$unidades = $_POST["unidad"] ?? ["PZA"];
$precios = $_POST["precio_unitario"] ?? ["0"];
$descuentos = $_POST["descuento_detalle"] ?? ["0"];
$ivas = $_POST["iva_porcentaje"] ?? ["16"];

/*
|--------------------------------------------------------------------------
| GUARDAR
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {

    if ($cliente_id <= 0) {
        $mensaje = "Debes seleccionar un cliente.";
    } elseif (empty($descripciones)) {
        $mensaje = "Debes agregar al menos un concepto.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | GENERAR FOLIO
        |--------------------------------------------------------------------------
        */

        $anio = date("Y");

        $prefijo = "COT-" . $anio . "-";

        $stmtFolio = $conexion->prepare("
            SELECT folio
            FROM cotizaciones
            WHERE folio LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $patron = $prefijo . "%";

        $stmtFolio->bind_param("s", $patron);
        $stmtFolio->execute();

        $resultadoFolio = $stmtFolio->get_result();

        $ultimoNumero = 0;

        if ($filaFolio = $resultadoFolio->fetch_assoc()) {

            $ultimoFolio = $filaFolio["folio"];

            $partes = explode("-", $ultimoFolio);

            if (isset($partes[2])) {
                $ultimoNumero = (int)$partes[2];
            }
        }

        $stmtFolio->close();

        $numero = $ultimoNumero + 1;

        $folio = $prefijo . str_pad((string)$numero, 4, "0", STR_PAD_LEFT);

        /*
        |--------------------------------------------------------------------------
        | VALIDAR ESTATUS
        |--------------------------------------------------------------------------
        */

        $estatus_validos = [
            "BORRADOR",
            "ENVIADA",
            "EN_SEGUIMIENTO",
            "ACEPTADA",
            "RECHAZADA",
            "VENCIDA"
        ];

        if (!in_array($estatus, $estatus_validos, true)) {
            $estatus = "BORRADOR";
        }

        /*
        |--------------------------------------------------------------------------
        | PREPARAR DETALLES
        |--------------------------------------------------------------------------
        */

        $detalles = [];

        $subtotal = 0;
        $iva_total = 0;

        $total_filas = max(
            count($descripciones),
            count($cantidades),
            count($precios)
        );

        for ($i = 0; $i < $total_filas; $i++) {

            $descripcion = trim($descripciones[$i] ?? "");

            if ($descripcion === "") {
                continue;
            }

            $producto_id = (int)($filas[$i] ?? 0);
            $cantidad = (float)($cantidades[$i] ?? 0);
            $unidad = trim($unidades[$i] ?? "PZA");
            $precio = (float)($precios[$i] ?? 0);
            $descuento_detalle = (float)($descuentos[$i] ?? 0);
            $iva_porcentaje = (float)($ivas[$i] ?? 0);

            if ($cantidad <= 0) {
                $cantidad = 1;
            }

            if ($precio < 0) {
                $precio = 0;
            }

            if ($descuento_detalle < 0) {
                $descuento_detalle = 0;
            }

            if ($iva_porcentaje < 0) {
                $iva_porcentaje = 0;
            }

            if ($iva_porcentaje > 100) {
                $iva_porcentaje = 100;
            }

            $importe_bruto = $cantidad * $precio;

            if ($descuento_detalle > $importe_bruto) {
                $descuento_detalle = $importe_bruto;
            }

            $importe = $importe_bruto - $descuento_detalle;

            $iva_importe = $importe * ($iva_porcentaje / 100);

            $subtotal += $importe;
            $iva_total += $iva_importe;

            $detalles[] = [
                "producto_id" => $producto_id > 0 ? $producto_id : null,
                "descripcion" => $descripcion,
                "cantidad" => $cantidad,
                "unidad" => $unidad !== "" ? $unidad : "PZA",
                "precio_unitario" => $precio,
                "descuento" => $descuento_detalle,
                "iva_porcentaje" => $iva_porcentaje,
                "iva_importe" => $iva_importe,
                "importe" => $importe
            ];
        }

        if (empty($detalles)) {

            $mensaje = "Debes agregar al menos un concepto válido.";

        } else {

            if ($descuento_general < 0) {
                $descuento_general = 0;
            }

            if ($descuento_general > $subtotal) {
                $descuento_general = $subtotal;
            }

            /*
            | El descuento general reduce la base gravable.
            | Se distribuye proporcionalmente entre las partidas
            | para conservar el IVA correcto.
            */

            $base_despues_descuento = $subtotal - $descuento_general;

            $iva_recalculado = 0;

            if ($subtotal > 0 && $descuento_general > 0) {

                foreach ($detalles as $detalle) {

                    $proporcion = $detalle["importe"] / $subtotal;

                    $descuento_proporcional =
                        $descuento_general * $proporcion;

                    $base_linea =
                        $detalle["importe"] - $descuento_proporcional;

                    $iva_recalculado +=
                        $base_linea *
                        ($detalle["iva_porcentaje"] / 100);
                }

                $iva_total = $iva_recalculado;
            }

            $total = $base_despues_descuento + $iva_total;

            /*
            |--------------------------------------------------------------------------
            | TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            $conexion->begin_transaction();

            try {

                $stmt = $conexion->prepare("
                    INSERT INTO cotizaciones (
                        folio,
                        cliente_id,
                        responsable,
                        fecha,
                        vigencia,
                        subtotal,
                        descuento,
                        iva,
                        total,
                        estatus,
                        observaciones,
                        condiciones,
                        fecha_seguimiento
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sisssddddssss",
                    $folio,
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
                    $fecha_seguimiento
                );

                if (!$stmt->execute()) {
                    throw new Exception("No fue posible guardar la cotización.");
                }

                $cotizacion_id = $stmt->insert_id;

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | DETALLES
                |--------------------------------------------------------------------------
                */

                $stmtDetalle = $conexion->prepare("
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

                foreach ($detalles as $indice => $detalle) {

                    $producto_id = $detalle["producto_id"];

                    $stmtDetalle->bind_param(
                        "iisdsdddddi",
                        $cotizacion_id,
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

                    if (!$stmtDetalle->execute()) {
                        throw new Exception("No fue posible guardar los conceptos.");
                    }
                }

                $stmtDetalle->close();

                $conexion->commit();

                header(
                    "Location: ver_cotizacion.php?id=" .
                    $cotizacion_id .
                    "&creada=1"
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

<title>Nueva cotización</title>

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
    font-size: 20px;
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
    font-size: 28px;
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
    font-size: 14px;
}

.campo textarea {
    resize: vertical;
    min-height: 100px;
}

.columna-completa {
    grid-column: 1 / -1;
}

h2 {
    margin-top: 0;
    font-size: 19px;
}

/* DETALLES */

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
    vertical-align: middle;
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
    white-space: nowrap;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 6px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
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

.btn-light {
    background: #e5e7eb;
    color: #111827;
}

.mensaje {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
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
    padding: 8px 0;
}

.resumen-fila.total {
    border-top: 2px solid #111827;
    margin-top: 8px;
    padding-top: 12px;
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
            <h1>Nueva cotización</h1>
            <div>
                Crear una nueva propuesta comercial
            </div>
        </div>

        <a href="cotizaciones.php" class="btn btn-secondary">
            ← Regresar
        </a>

    </div>

    <?php if ($mensaje !== ""): ?>

        <div class="mensaje">
            <?= esc($mensaje) ?>
        </div>

    <?php endif; ?>

    <form method="POST" id="formCotizacion">

        <div class="panel">

            <h2>Datos de la cotización</h2>

            <div class="grid">

                <div class="campo">

                    <label>Cliente *</label>

                    <select name="cliente_id" required>

                        <option value="">Seleccionar cliente</option>

                        <?php foreach ($clientes as $cliente): ?>

                            <option
                                value="<?= (int)$cliente["id"] ?>"
                                <?= $cliente_id == $cliente["id"] ? "selected" : "" ?>
                            >

                                <?= esc($cliente["nombre"]) ?>

                                <?php if (!empty($cliente["razon_social"])): ?>
                                    - <?= esc($cliente["razon_social"]) ?>
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
                        value="<?= esc($responsable) ?>"
                    >

                </div>

                <div class="campo">

                    <label>Fecha *</label>

                    <input
                        type="date"
                        name="fecha"
                        value="<?= esc($fecha) ?>"
                        required
                    >

                </div>

                <div class="campo">

                    <label>Vigencia</label>

                    <input
                        type="date"
                        name="vigencia"
                        value="<?= esc($vigencia) ?>"
                    >

                </div>

                <div class="campo">

                    <label>Estatus</label>

                    <select name="estatus">

                        <option value="BORRADOR" <?= $estatus === "BORRADOR" ? "selected" : "" ?>>
                            Borrador
                        </option>

                        <option value="ENVIADA" <?= $estatus === "ENVIADA" ? "selected" : "" ?>>
                            Enviada
                        </option>

                        <option value="EN_SEGUIMIENTO" <?= $estatus === "EN_SEGUIMIENTO" ? "selected" : "" ?>>
                            En seguimiento
                        </option>

                    </select>

                </div>

                <div class="campo">

                    <label>Próximo seguimiento</label>

                    <input
                        type="date"
                        name="fecha_seguimiento"
                        value="<?= esc($fecha_seguimiento) ?>"
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

                            <th style="width:220px;">Producto / Servicio</th>
                            <th>Descripción</th>
                            <th style="width:90px;">Cantidad</th>
                            <th style="width:90px;">Unidad</th>
                            <th style="width:120px;">Precio</th>
                            <th style="width:110px;">Desc. $</th>
                            <th style="width:90px;">IVA %</th>
                            <th style="width:120px;">Importe</th>
                            <th style="width:60px;"></th>

                        </tr>

                    </thead>

                    <tbody id="detalleBody">

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
                                    value="1"
                                >

                            </td>

                            <td>

                                <input
                                    type="text"
                                    name="unidad[]"
                                    class="unidad"
                                    value="PZA"
                                >

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="precio_unitario[]"
                                    class="precio"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                >

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="descuento_detalle[]"
                                    class="descuento-detalle"
                                    min="0"
                                    step="0.01"
                                    value="0"
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
                                    value="16"
                                >

                            </td>

                            <td class="total-linea">
                                $0.00
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
                        value="<?= esc($descuento_general) ?>"
                    >

                </div>

                <div class="campo columna-completa">

                    <label>Observaciones</label>

                    <textarea name="observaciones"><?= esc($observaciones) ?></textarea>

                </div>

                <div class="campo columna-completa">

                    <label>Condiciones comerciales</label>

                    <textarea name="condiciones"><?= esc($condiciones) ?></textarea>

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
                    href="cotizaciones.php"
                    class="btn btn-secondary"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    name="guardar"
                    class="btn btn-success"
                >
                    Guardar cotización
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
            parseFloat(fila.querySelector(".descuento-detalle").value) || 0;

        const iva =
            parseFloat(fila.querySelector(".iva").value) || 0;

        let importeBruto = cantidad * precio;

        if (descuento > importeBruto) {
            descuento = importeBruto;
        }

        const importe = importeBruto - descuento;

        const ivaImporte = importe * (iva / 100);

        subtotal += importe;

        ivaTotal += ivaImporte;

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

            const importeBruto = cantidad * precio;

            if (descuento > importeBruto) {
                descuento = importeBruto;
            }

            const importe = importeBruto - descuento;

            const proporcion =
                subtotal > 0 ? importe / subtotal : 0;

            const descuentoProporcional =
                descuentoGeneral * proporcion;

            const base =
                importe - descuentoProporcional;

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
    const producto = fila.querySelector(".producto");

    producto.addEventListener("change", function() {

        const opcion =
            producto.options[producto.selectedIndex];

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

configurarFila(
    document.querySelector(".fila-detalle")
);

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