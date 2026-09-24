<?php

require_once __DIR__ . '/conexion.php';

/*
|--------------------------------------------------------------------------
| DOMPDF
|--------------------------------------------------------------------------
|
| Se espera que Dompdf esté instalado mediante Composer:
|
| composer require dompdf/dompdf
|
*/

$autoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoload)) {

    die("
        <h2>Dompdf no está instalado</h2>

        <p>
            Para generar los PDF debes instalar Dompdf en la carpeta
            <strong>comercial</strong>.
        </p>

        <p>
            Desde CMD ejecuta:
        </p>

        <pre>cd C:\\xampp\\htdocs\\comercial
composer require dompdf/dompdf</pre>

        <p>
            Después vuelve a intentar generar el PDF.
        </p>
    ");
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

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

/*
|--------------------------------------------------------------------------
| HTML DEL PDF
|--------------------------------------------------------------------------
*/

ob_start();

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<style>

@page {
    margin: 35px 40px;
}

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    color: #222;
    font-size: 10px;
}

.encabezado {
    width: 100%;
    border-bottom: 2px solid #222;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.encabezado table {
    width: 100%;
    border-collapse: collapse;
}

.encabezado td {
    vertical-align: top;
}

.titulo {
    font-size: 23px;
    font-weight: bold;
}

.folio {
    font-size: 14px;
    margin-top: 5px;
}

.fecha {
    text-align: right;
}

.bloques {
    width: 100%;
    margin-bottom: 20px;
}

.bloques table {
    width: 100%;
    border-collapse: collapse;
}

.bloques td {
    width: 50%;
    vertical-align: top;
    padding-right: 20px;
}

.titulo-bloque {
    font-weight: bold;
    border-bottom: 1px solid #aaa;
    padding-bottom: 5px;
    margin-bottom: 7px;
}

p {
    margin: 3px 0;
}

.detalles {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.detalles th {
    background: #eeeeee;
    border: 1px solid #cccccc;
    padding: 7px;
    font-size: 9px;
}

.detalles td {
    border: 1px solid #dddddd;
    padding: 7px;
    font-size: 9px;
}

.derecha {
    text-align: right;
}

.centro {
    text-align: center;
}

.resumen {
    width: 40%;
    margin-left: 60%;
    margin-top: 15px;
    border-collapse: collapse;
}

.resumen td {
    padding: 6px;
}

.total {
    border-top: 2px solid #222;
    font-size: 13px;
    font-weight: bold;
}

.texto {
    margin-top: 25px;
}

.texto-titulo {
    font-weight: bold;
    border-bottom: 1px solid #aaa;
    padding-bottom: 5px;
    margin-bottom: 5px;
}

.pie {
    margin-top: 35px;
    border-top: 1px solid #aaa;
    padding-top: 8px;
    text-align: center;
    font-size: 8px;
    color: #666;
}

</style>

</head>

<body>

<div class="encabezado">

    <table>

        <tr>

            <td>

                <div class="titulo">
                    COTIZACIÓN
                </div>

                <div class="folio">
                    <?= esc($cotizacion["folio"]) ?>
                </div>

            </td>

            <td class="fecha">

                <strong>Fecha:</strong>
                <?= esc($cotizacion["fecha"]) ?>

                <br>

                <strong>Vigencia:</strong>

                <?= !empty($cotizacion["vigencia"])
                    ? esc($cotizacion["vigencia"])
                    : "Sin especificar"
                ?>

                <br>

                <strong>Estatus:</strong>
                <?= esc(textoEstatus($cotizacion["estatus"])) ?>

            </td>

        </tr>

    </table>

</div>

<div class="bloques">

    <table>

        <tr>

            <td>

                <div class="titulo-bloque">
                    DATOS DEL CLIENTE
                </div>

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
                        RFC:
                        <?= esc($cotizacion["rfc"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["contacto"])): ?>

                    <p>
                        Contacto:
                        <?= esc($cotizacion["contacto"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["telefono"])): ?>

                    <p>
                        Tel:
                        <?= esc($cotizacion["telefono"]) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($cotizacion["correo"])): ?>

                    <p>
                        Correo:
                        <?= esc($cotizacion["correo"]) ?>
                    </p>

                <?php endif; ?>

            </td>

            <td>

                <div class="titulo-bloque">
                    DATOS COMERCIALES
                </div>

                <p>

                    <strong>
                        Responsable:
                    </strong>

                    <?= !empty($cotizacion["responsable"])
                        ? esc($cotizacion["responsable"])
                        : "Sin especificar"
                    ?>

                </p>

                <?php if (!empty($cotizacion["fecha_seguimiento"])): ?>

                    <p>

                        <strong>
                            Seguimiento:
                        </strong>

                        <?= esc($cotizacion["fecha_seguimiento"]) ?>

                    </p>

                <?php endif; ?>

            </td>

        </tr>

    </table>

</div>

<table class="detalles">

    <thead>

        <tr>

            <th style="width:5%;">#</th>

            <th style="width:31%;">
                Descripción
            </th>

            <th style="width:9%;">
                Unidad
            </th>

            <th style="width:9%;" class="derecha">
                Cant.
            </th>

            <th style="width:14%;" class="derecha">
                Precio
            </th>

            <th style="width:12%;" class="derecha">
                Desc.
            </th>

            <th style="width:8%;" class="derecha">
                IVA
            </th>

            <th style="width:14%;" class="derecha">
                Importe
            </th>

        </tr>

    </thead>

    <tbody>

    <?php foreach ($detalles as $indice => $detalle): ?>

        <tr>

            <td class="centro">
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

            <td class="centro">
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
                <?= moneda($detalle["importe"]) ?>
            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<table class="resumen">

    <tr>

        <td>
            Subtotal:
        </td>

        <td class="derecha">
            <?= moneda($cotizacion["subtotal"]) ?>
        </td>

    </tr>

    <tr>

        <td>
            Descuento:
        </td>

        <td class="derecha">
            <?= moneda($cotizacion["descuento"]) ?>
        </td>

    </tr>

    <tr>

        <td>
            IVA:
        </td>

        <td class="derecha">
            <?= moneda($cotizacion["iva"]) ?>
        </td>

    </tr>

    <tr class="total">

        <td>
            TOTAL:
        </td>

        <td class="derecha">
            <?= moneda($cotizacion["total"]) ?>
        </td>

    </tr>

</table>

<?php if (!empty($cotizacion["observaciones"])): ?>

    <div class="texto">

        <div class="texto-titulo">
            OBSERVACIONES
        </div>

        <div>
            <?= nl2br(esc($cotizacion["observaciones"])) ?>
        </div>

    </div>

<?php endif; ?>

<?php if (!empty($cotizacion["condiciones"])): ?>

    <div class="texto">

        <div class="texto-titulo">
            CONDICIONES COMERCIALES
        </div>

        <div>
            <?= nl2br(esc($cotizacion["condiciones"])) ?>
        </div>

    </div>

<?php endif; ?>

<div class="pie">

    Cotización <?= esc($cotizacion["folio"]) ?>

</div>

</body>

</html>

<?php

$html = ob_get_clean();

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DOMPDF
|--------------------------------------------------------------------------
*/

$options = new Options();

$options->set(
    'isRemoteEnabled',
    true
);

$options->set(
    'defaultFont',
    'DejaVu Sans'
);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper(
    'letter',
    'portrait'
);

$dompdf->render();

/*
|--------------------------------------------------------------------------
| DESCARGAR PDF
|--------------------------------------------------------------------------
*/

$nombre_archivo =
    preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        $cotizacion["folio"]
    ) .
    ".pdf";

$dompdf->stream(
    $nombre_archivo,
    [
        "Attachment" => true
    ]
);

exit;