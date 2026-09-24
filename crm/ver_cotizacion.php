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

function textoEstatus($estatus)
{
    $estados = [
        "BORRADOR" => "Borrador",
        "ENVIADA" => "Enviada",
        "EN_SEGUIMIENTO" => "En seguimiento",
        "ACEPTADA" => "Aceptada",
        "RECHAZADA" => "Rechazada",
        "VENCIDA" => "Vencida"
    ];

    return $estados[$estatus] ?? $estatus;
}

function claseEstatus($estatus)
{
    switch ($estatus) {

        case "BORRADOR":
            return "borrador";

        case "ENVIADA":
            return "enviada";

        case "EN_SEGUIMIENTO":
            return "seguimiento";

        case "ACEPTADA":
            return "aceptada";

        case "RECHAZADA":
            return "rechazada";

        case "VENCIDA":
            return "vencida";

        default:
            return "";
    }
}

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    die("Cotización no válida.");
}

/*
|--------------------------------------------------------------------------
| COTIZACIÓN
|--------------------------------------------------------------------------
*/

$stmt = $conexion->prepare("
    SELECT
        c.*,
        cl.nombre AS cliente_nombre,
        cl.tipo_cliente,
        cl.razon_social,
        cl.rfc,
        cl.contacto,
        cl.telefono,
        cl.telefono2,
        cl.correo,
        cl.calle,
        cl.numero,
        cl.colonia,
        cl.ciudad,
        cl.estado,
        cl.codigo_postal
    FROM cotizaciones c
    INNER JOIN clientes cl
        ON cl.id = c.cliente_id
    WHERE c.id = ?
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
| DETALLES
|--------------------------------------------------------------------------
*/

$detalles = [];

$stmt = $conexion->prepare("
    SELECT
        d.*,
        p.codigo
    FROM cotizacion_detalles d
    LEFT JOIN productos p
        ON p.id = d.producto_id
    WHERE d.cotizacion_id = ?
    ORDER BY d.orden ASC, d.id ASC
");

$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $detalles[] = $fila;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title><?= esc($cotizacion["folio"]) ?></title>

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

.acciones-superiores {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.botones {
    display: flex;
    gap: 8px;
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

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-warning {
    background: #d97706;
    color: white;
}

.documento {
    max-width: 1100px;
    margin: auto;
    background: white;
    padding: 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

.encabezado-documento {
    display: flex;
    justify-content: space-between;
    border-bottom: 2px solid #111827;
    padding-bottom: 20px;
    margin-bottom: 25px;
}

.titulo h1 {
    margin: 0 0 8px;
    font-size: 30px;
}

.folio {
    font-size: 18px;
    font-weight: bold;
}

.info-dos-columnas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.info h3 {
    margin-top: 0;
    font-size: 14px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 7px;
}

.info p {
    margin: 5px 0;
    font-size: 14px;
}

.estado {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
}

.borrador {
    background: #e5e7eb;
    color: #374151;
}

.enviada {
    background: #dbeafe;
    color: #1d4ed8;
}

.seguimiento {
    background: #fef3c7;
    color: #92400e;
}

.aceptada {
    background: #dcfce7;
    color: #166534;
}

.rechazada {
    background: #fee2e2;
    color: #991b1b;
}

.vencida {
    background: #f3e8ff;
    color: #7e22ce;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th,
td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
}

th {
    background: #f3f4f6;
    text-align: left;
}

.derecha {
    text-align: right;
}

.resumen {
    width: 350px;
    margin-left: auto;
    margin-top: 20px;
}

.resumen-fila {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}

.resumen-total {
    border-top: 2px solid #111827;
    padding-top: 12px;
    font-size: 21px;
    font-weight: bold;
}

.bloque-texto {
    margin-top: 30px;
}

.bloque-texto h3 {
    font-size: 14px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 7px;
}

.bloque-texto p {
    white-space: pre-line;
    font-size: 13px;
    line-height: 1.6;
}

@media print {

    body {
        background: white;
    }

    .sidebar,
    .acciones-superiores {
        display: none !important;
    }

    .contenido {
        margin: 0;
        padding: 0;
    }

    .documento {
        box-shadow: none;
        max-width: none;
        padding: 20px;
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

    .documento {
        padding: 20px;
    }

    .info-dos-columnas {
        grid-template-columns: 1fr;
    }

    .resumen {
        width: 100%;
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

    <div class="acciones-superiores">

        <div>

            <a
                href="cotizaciones.php"
                class="btn btn-secondary"
            >
                ← Cotizaciones
            </a>

        </div>

        <div class="botones">

            <a
                href="editar_cotizacion.php?id=<?= $id ?>"
                class="btn btn-primary"
            >
                Editar
            </a>

            <a
                href="pdf_cotizacion.php?id=<?= $id ?>"
                target="_blank"
                class="btn btn-success"
            >
                Descargar PDF
            </a>

            <button
                onclick="window.print()"
                class="btn btn-secondary"
            >
                Imprimir
            </button>

        </div>

    </div>

    <div class="documento">

        <div class="encabezado-documento">

            <div class="titulo">

                <h1>COTIZACIÓN</h1>

                <div class="folio">
                    <?= esc($cotizacion["folio"]) ?>
                </div>

            </div>

            <div>

                <span class="estado <?= claseEstatus($cotizacion["estatus"]) ?>">
                    <?= esc(textoEstatus($cotizacion["estatus"])) ?>
                </span>

            </div>

        </div>

        <div class="info-dos-columnas">

            <div class="info">

                <h3>DATOS DEL CLIENTE</h3>

                <p>
                    <strong>
                        <?= esc($cotizacion["cliente_nombre"]) ?>
                    </strong>
                </p>

                <?php if (!empty($cotizacion["razon_social"])): ?>

                    <p>
                        <?= esc($cotizacion["razon_social"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["rfc"])): ?>

                    <p>
                        RFC: <?= esc($cotizacion["rfc"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["contacto"])): ?>

                    <p>
                        Contacto: <?= esc($cotizacion["contacto"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["telefono"])): ?>

                    <p>
                        Teléfono: <?= esc($cotizacion["telefono"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["correo"])): ?>

                    <p>
                        Correo: <?= esc($cotizacion["correo"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["calle"])): ?>

                    <p>
                        <?= esc($cotizacion["calle"]) ?>
                        <?= esc($cotizacion["numero"]) ?>
                        <?= !empty($cotizacion["colonia"]) ? ", " . esc($cotizacion["colonia"]) : "" ?>
                    </p>

                    <p>
                        <?= esc($cotizacion["ciudad"]) ?>
                        <?= !empty($cotizacion["estado"]) ? ", " . esc($cotizacion["estado"]) : "" ?>
                        <?= !empty($cotizacion["codigo_postal"]) ? " C.P. " . esc($cotizacion["codigo_postal"]) : "" ?>
                    </p>

                <?php endif; ?>

            </div>

            <div class="info">

                <h3>DATOS DE LA COTIZACIÓN</h3>

                <p>
                    <strong>Folio:</strong>
                    <?= esc($cotizacion["folio"]) ?>
                </p>

                <p>
                    <strong>Fecha:</strong>
                    <?= esc($cotizacion["fecha"]) ?>
                </p>

                <p>
                    <strong>Vigencia:</strong>
                    <?= !empty($cotizacion["vigencia"])
                        ? esc($cotizacion["vigencia"])
                        : "Sin especificar"
                    ?>
                </p>

                <p>
                    <strong>Responsable:</strong>
                    <?= !empty($cotizacion["responsable"])
                        ? esc($cotizacion["responsable"])
                        : "Sin especificar"
                    ?>
                </p>

                <?php if (!empty($cotizacion["fecha_seguimiento"])): ?>

                    <p>
                        <strong>Seguimiento:</strong>
                        <?= esc($cotizacion["fecha_seguimiento"]) ?>
                    </p>

                <?php endif; ?>

            </div>

        </div>

        <table>

            <thead>

                <tr>

                    <th>#</th>
                    <th>Descripción</th>
                    <th>Unidad</th>
                    <th class="derecha">Cantidad</th>
                    <th class="derecha">Precio unitario</th>
                    <th class="derecha">Descuento</th>
                    <th class="derecha">IVA</th>
                    <th class="derecha">Importe</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($detalles as $indice => $detalle): ?>

                <tr>

                    <td>
                        <?= $indice + 1 ?>
                    </td>

                    <td>

                        <?= esc($detalle["descripcion"]) ?>

                        <?php if (!empty($detalle["codigo"])): ?>

                            <br>

                            <small>
                                Código:
                                <?= esc($detalle["codigo"]) ?>
                            </small>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= esc($detalle["unidad"]) ?>
                    </td>

                    <td class="derecha">
                        <?= number_format(
                            (float)$detalle["cantidad"],
                            2,
                            '.',
                            ','
                        ) ?>
                    </td>

                    <td class="derecha">
                        <?= moneda($detalle["precio_unitario"]) ?>
                    </td>

                    <td class="derecha">
                        <?= moneda($detalle["descuento"]) ?>
                    </td>

                    <td class="derecha">
                        <?= number_format(
                            (float)$detalle["iva_porcentaje"],
                            2
                        ) ?>%
                    </td>

                    <td class="derecha">
                        <strong>
                            <?= moneda($detalle["importe"]) ?>
                        </strong>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

        <div class="resumen">

            <div class="resumen-fila">

                <span>Subtotal:</span>

                <strong>
                    <?= moneda($cotizacion["subtotal"]) ?>
                </strong>

            </div>

            <div class="resumen-fila">

                <span>Descuento:</span>

                <strong>
                    <?= moneda($cotizacion["descuento"]) ?>
                </strong>

            </div>

            <div class="resumen-fila">

                <span>IVA:</span>

                <strong>
                    <?= moneda($cotizacion["iva"]) ?>
                </strong>

            </div>

            <div class="resumen-fila resumen-total">

                <span>Total:</span>

                <span>
                    <?= moneda($cotizacion["total"]) ?>
                </span>

            </div>

        </div>

        <?php if (!empty($cotizacion["observaciones"])): ?>

            <div class="bloque-texto">

                <h3>OBSERVACIONES</h3>

                <p>
                    <?= esc($cotizacion["observaciones"]) ?>
                </p>

            </div>

        <?php endif; ?>

        <?php if (!empty($cotizacion["condiciones"])): ?>

            <div class="bloque-texto">

                <h3>CONDICIONES COMERCIALES</h3>

                <p>
                    <?= esc($cotizacion["condiciones"]) ?>
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>