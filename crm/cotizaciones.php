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

/*
|--------------------------------------------------------------------------
| ACCIONES
|--------------------------------------------------------------------------
*/

$mensaje = "";
$tipo_mensaje = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $id = (int)($_POST["id"] ?? 0);

    if ($id > 0 && in_array($accion, ["enviar", "seguimiento", "aceptar", "rechazar", "vencida", "borrador"], true)) {

        $estatus = "";

        switch ($accion) {
            case "enviar":
                $estatus = "ENVIADA";
                break;

            case "seguimiento":
                $estatus = "EN_SEGUIMIENTO";
                break;

            case "aceptar":
                $estatus = "ACEPTADA";
                break;

            case "rechazar":
                $estatus = "RECHAZADA";
                break;

            case "vencida":
                $estatus = "VENCIDA";
                break;

            case "borrador":
                $estatus = "BORRADOR";
                break;
        }

        $stmt = $conexion->prepare("
            UPDATE cotizaciones
            SET estatus = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $estatus, $id);

        if ($stmt->execute()) {
            $mensaje = "El estatus de la cotización fue actualizado correctamente.";
        } else {
            $mensaje = "No fue posible actualizar el estatus.";
            $tipo_mensaje = "error";
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET["buscar"] ?? "");
$estatus_filtro = $_GET["estatus"] ?? "";
$fecha_desde = $_GET["fecha_desde"] ?? "";
$fecha_hasta = $_GET["fecha_hasta"] ?? "";

$sql = "
    SELECT
        c.id,
        c.folio,
        c.cliente_id,
        c.responsable,
        c.fecha,
        c.vigencia,
        c.subtotal,
        c.descuento,
        c.iva,
        c.total,
        c.estatus,
        c.observaciones,
        c.fecha_seguimiento,
        cl.nombre AS cliente_nombre,
        cl.razon_social
    FROM cotizaciones c
    INNER JOIN clientes cl ON cl.id = c.cliente_id
    WHERE 1=1
";

$parametros = [];
$tipos = "";

if ($buscar !== "") {

    $sql .= "
        AND (
            c.folio LIKE ?
            OR cl.nombre LIKE ?
            OR cl.razon_social LIKE ?
            OR cl.rfc LIKE ?
        )
    ";

    $busqueda = "%" . $buscar . "%";

    $parametros[] = $busqueda;
    $parametros[] = $busqueda;
    $parametros[] = $busqueda;
    $parametros[] = $busqueda;

    $tipos .= "ssss";
}

if ($estatus_filtro !== "") {

    $sql .= " AND c.estatus = ? ";

    $parametros[] = $estatus_filtro;
    $tipos .= "s";
}

if ($fecha_desde !== "") {

    $sql .= " AND c.fecha >= ? ";

    $parametros[] = $fecha_desde;
    $tipos .= "s";
}

if ($fecha_hasta !== "") {

    $sql .= " AND c.fecha <= ? ";

    $parametros[] = $fecha_hasta;
    $tipos .= "s";
}

$sql .= " ORDER BY c.id DESC";

$stmt = $conexion->prepare($sql);

if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();

$resultado = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$contadores = [
    "total" => 0,
    "borrador" => 0,
    "enviada" => 0,
    "seguimiento" => 0,
    "aceptada" => 0,
    "rechazada" => 0,
    "vencida" => 0
];

$resumen = $conexion->query("
    SELECT
        COUNT(*) AS total,
        SUM(estatus = 'BORRADOR') AS borrador,
        SUM(estatus = 'ENVIADA') AS enviada,
        SUM(estatus = 'EN_SEGUIMIENTO') AS seguimiento,
        SUM(estatus = 'ACEPTADA') AS aceptada,
        SUM(estatus = 'RECHAZADA') AS rechazada,
        SUM(estatus = 'VENCIDA') AS vencida
    FROM cotizaciones
");

if ($resumen) {
    $fila = $resumen->fetch_assoc();

    foreach ($contadores as $key => $valor) {
        $contadores[$key] = (int)($fila[$key] ?? 0);
    }
}

function claseEstatus($estatus)
{
    switch ($estatus) {
        case "BORRADOR":
            return "estado borrador";

        case "ENVIADA":
            return "estado enviada";

        case "EN_SEGUIMIENTO":
            return "estado seguimiento";

        case "ACEPTADA":
            return "estado aceptada";

        case "RECHAZADA":
            return "estado rechazada";

        case "VENCIDA":
            return "estado vencida";

        default:
            return "estado";
    }
}

function textoEstatus($estatus)
{
    switch ($estatus) {
        case "BORRADOR":
            return "Borrador";

        case "ENVIADA":
            return "Enviada";

        case "EN_SEGUIMIENTO":
            return "En seguimiento";

        case "ACEPTADA":
            return "Aceptada";

        case "RECHAZADA":
            return "Rechazada";

        case "VENCIDA":
            return "Vencida";

        default:
            return $estatus;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cotizaciones</title>

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

/* SIDEBAR */

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
    font-size: 20px;
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

/* CONTENIDO */

.contenido {
    margin-left: 240px;
    padding: 30px;
}

.encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.encabezado h1 {
    margin: 0;
    font-size: 28px;
}

.subtitulo {
    color: #6b7280;
    margin-top: 6px;
}

/* BOTONES */

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

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-success {
    background: #16a34a;
    color: white;
}

.btn-warning {
    background: #d97706;
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

.btn-small {
    padding: 7px 10px;
    font-size: 12px;
}

/* TARJETAS */

.tarjetas {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
    margin-bottom: 25px;
}

.tarjeta {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 7px rgba(0,0,0,.06);
}

.tarjeta .numero {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.tarjeta .texto {
    font-size: 12px;
    color: #6b7280;
}

/* FILTROS */

.panel {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 7px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.filtros {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}

.campo label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 6px;
}

.campo input,
.campo select {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

/* TABLA */

.tabla-contenedor {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 7px rgba(0,0,0,.06);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px 10px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 13px;
    vertical-align: middle;
}

th {
    background: #f9fafb;
    font-size: 12px;
    color: #374151;
}

tr:hover {
    background: #f9fafb;
}

.texto-derecha {
    text-align: right;
}

.texto-centro {
    text-align: center;
}

/* ESTATUS */

.estado {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
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

/* ACCIONES */

.acciones {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* MENSAJE */

.mensaje {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 14px;
}

.mensaje.success {
    background: #dcfce7;
    color: #166534;
}

.mensaje.error {
    background: #fee2e2;
    color: #991b1b;
}

@media(max-width: 1200px) {

    .tarjetas {
        grid-template-columns: repeat(4, 1fr);
    }

    .filtros {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width: 800px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 15px;
    }

    .encabezado {
        flex-direction: column;
        align-items: flex-start;
    }

    .tarjetas {
        grid-template-columns: repeat(2, 1fr);
    }

    .filtros {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body>

<div class="sidebar">

    <h2>Comercial</h2>

    <div class="seccion">Principal</div>

    <a href="index.php">
        Inicio
    </a>

    <div class="seccion">Clientes</div>

    <a href="clientes.php">
        Clientes
    </a>

    <div class="seccion">Productos y servicios</div>

    <a href="productos.php">
        Productos / Servicios
    </a>

    <div class="seccion">Ventas</div>

    <a href="cotizaciones.php" class="activo">
        Cotizaciones
    </a>

    <div class="seccion">Seguimiento</div>

    <a href="seguimientos.php">
        Seguimientos
    </a>

    <div class="seccion">Administración</div>

    <a href="reportes.php">
        Reportes
    </a>

    <a href="auditoria.php">
        Auditoría
    </a>

</div>

<div class="contenido">

    <div class="encabezado">

        <div>
            <h1>Cotizaciones</h1>
            <div class="subtitulo">
                Administración y seguimiento de propuestas comerciales
            </div>
        </div>

        <a href="nueva_cotizacion.php" class="btn btn-primary">
            + Nueva cotización
        </a>

    </div>

    <?php if ($mensaje !== ""): ?>

        <div class="mensaje <?= esc($tipo_mensaje) ?>">
            <?= esc($mensaje) ?>
        </div>

    <?php endif; ?>

    <div class="tarjetas">

        <div class="tarjeta">
            <div class="numero"><?= $contadores["total"] ?></div>
            <div class="texto">Total</div>
        </div>

        <div class="tarjeta">
            <div class="numero"><?= $contadores["borrador"] ?></div>
            <div class="texto">Borradores</div>
        </div>

        <div class="tarjeta">
            <div class="numero"><?= $contadores["enviada"] ?></div>
            <div class="texto">Enviadas</div>
        </div>

        <div class="tarjeta">
            <div class="numero"><?= $contadores["seguimiento"] ?></div>
            <div class="texto">Seguimiento</div>
        </div>

        <div class="tarjeta">
            <div class="numero"><?= $contadores["aceptada"] ?></div>
            <div class="texto">Aceptadas</div>
        </div>

        <div class="tarjeta">
            <div class="numero"><?= $contadores["rechazada"] ?></div>
            <div class="texto">Rechazadas</div>
        </div>

        <div class="tarjeta">
            <div class="numero"><?= $contadores["vencida"] ?></div>
            <div class="texto">Vencidas</div>
        </div>

    </div>

    <div class="panel">

        <form method="GET">

            <div class="filtros">

                <div class="campo">

                    <label>Buscar</label>

                    <input
                        type="text"
                        name="buscar"
                        value="<?= esc($buscar) ?>"
                        placeholder="Folio, cliente, razón social o RFC"
                    >

                </div>

                <div class="campo">

                    <label>Estatus</label>

                    <select name="estatus">

                        <option value="">Todos</option>

                        <option value="BORRADOR" <?= $estatus_filtro === "BORRADOR" ? "selected" : "" ?>>
                            Borrador
                        </option>

                        <option value="ENVIADA" <?= $estatus_filtro === "ENVIADA" ? "selected" : "" ?>>
                            Enviada
                        </option>

                        <option value="EN_SEGUIMIENTO" <?= $estatus_filtro === "EN_SEGUIMIENTO" ? "selected" : "" ?>>
                            En seguimiento
                        </option>

                        <option value="ACEPTADA" <?= $estatus_filtro === "ACEPTADA" ? "selected" : "" ?>>
                            Aceptada
                        </option>

                        <option value="RECHAZADA" <?= $estatus_filtro === "RECHAZADA" ? "selected" : "" ?>>
                            Rechazada
                        </option>

                        <option value="VENCIDA" <?= $estatus_filtro === "VENCIDA" ? "selected" : "" ?>>
                            Vencida
                        </option>

                    </select>

                </div>

                <div class="campo">

                    <label>Desde</label>

                    <input
                        type="date"
                        name="fecha_desde"
                        value="<?= esc($fecha_desde) ?>"
                    >

                </div>

                <div class="campo">

                    <label>Hasta</label>

                    <input
                        type="date"
                        name="fecha_hasta"
                        value="<?= esc($fecha_hasta) ?>"
                    >

                </div>

                <div>

                    <button type="submit" class="btn btn-primary">
                        Filtrar
                    </button>

                </div>

            </div>

        </form>

    </div>

    <div class="tabla-contenedor">

        <table>

            <thead>

                <tr>

                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Vigencia</th>
                    <th>Responsable</th>
                    <th class="texto-derecha">Subtotal</th>
                    <th class="texto-derecha">Total</th>
                    <th>Estatus</th>
                    <th>Acciones</th>

                </tr>

            </thead>

            <tbody>

            <?php if ($resultado->num_rows === 0): ?>

                <tr>

                    <td colspan="9" class="texto-centro">
                        No se encontraron cotizaciones.
                    </td>

                </tr>

            <?php else: ?>

                <?php while ($cot = $resultado->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <strong><?= esc($cot["folio"]) ?></strong>
                        </td>

                        <td>

                            <?= esc($cot["cliente_nombre"]) ?>

                            <?php if (!empty($cot["razon_social"])): ?>

                                <br>
                                <small>
                                    <?= esc($cot["razon_social"]) ?>
                                </small>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= esc($cot["fecha"]) ?>
                        </td>

                        <td>
                            <?= !empty($cot["vigencia"]) ? esc($cot["vigencia"]) : "-" ?>
                        </td>

                        <td>
                            <?= !empty($cot["responsable"]) ? esc($cot["responsable"]) : "-" ?>
                        </td>

                        <td class="texto-derecha">
                            <?= moneda($cot["subtotal"]) ?>
                        </td>

                        <td class="texto-derecha">
                            <strong>
                                <?= moneda($cot["total"]) ?>
                            </strong>
                        </td>

                        <td>

                            <span class="<?= claseEstatus($cot["estatus"]) ?>">
                                <?= esc(textoEstatus($cot["estatus"])) ?>
                            </span>

                        </td>

                        <td>

                            <div class="acciones">

                                <a
                                    href="ver_cotizacion.php?id=<?= (int)$cot["id"] ?>"
                                    class="btn btn-secondary btn-small"
                                >
                                    Ver
                                </a>

                                <a
                                    href="editar_cotizacion.php?id=<?= (int)$cot["id"] ?>"
                                    class="btn btn-primary btn-small"
                                >
                                    Editar
                                </a>

                                <a
                                    href="pdf_cotizacion.php?id=<?= (int)$cot["id"] ?>"
                                    target="_blank"
                                    class="btn btn-success btn-small"
                                >
                                    PDF
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>