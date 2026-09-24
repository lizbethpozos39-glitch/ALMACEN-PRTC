<?php

require_once __DIR__ . '/conexion.php';

/* =========================================================
   FUNCIONES
========================================================= */

function esc($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function moneda($valor)
{
    return '$' . number_format((float)$valor, 2);
}

function fecha_mx($fecha)
{
    if (empty($fecha)) {
        return '-';
    }

    $timestamp = strtotime($fecha);

    return $timestamp
        ? date('d/m/Y', $timestamp)
        : $fecha;
}

/* =========================================================
   FILTROS DE FECHA
========================================================= */

$fecha_inicio = trim($_GET['fecha_inicio'] ?? '');
$fecha_fin    = trim($_GET['fecha_fin'] ?? '');

if ($fecha_inicio === '') {
    $fecha_inicio = date('Y-m-01');
}

if ($fecha_fin === '') {
    $fecha_fin = date('Y-m-d');
}

/* Validación básica */

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d');
}

/* =========================================================
   RESUMEN GENERAL
========================================================= */

$total_cotizaciones = 0;
$monto_cotizaciones = 0;
$total_aceptadas = 0;
$monto_aceptadas = 0;
$total_rechazadas = 0;
$monto_rechazadas = 0;
$total_seguimiento = 0;
$monto_seguimiento = 0;

$stmt = $conexion->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(total), 0) AS monto
    FROM cotizaciones
    WHERE fecha BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$resumen = $stmt->get_result()->fetch_assoc();

$stmt->close();

$total_cotizaciones = (int)$resumen['total'];
$monto_cotizaciones = (float)$resumen['monto'];


/* Aceptadas */

$stmt = $conexion->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(total), 0) AS monto
    FROM cotizaciones
    WHERE fecha BETWEEN ? AND ?
      AND estatus = 'ACEPTADA'
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

$stmt->close();

$total_aceptadas = (int)$row['total'];
$monto_aceptadas = (float)$row['monto'];


/* Rechazadas */

$stmt = $conexion->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(total), 0) AS monto
    FROM cotizaciones
    WHERE fecha BETWEEN ? AND ?
      AND estatus = 'RECHAZADA'
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

$stmt->close();

$total_rechazadas = (int)$row['total'];
$monto_rechazadas = (float)$row['monto'];


/* En seguimiento */

$stmt = $conexion->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(total), 0) AS monto
    FROM cotizaciones
    WHERE fecha BETWEEN ? AND ?
      AND estatus = 'EN_SEGUIMIENTO'
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

$stmt->close();

$total_seguimiento = (int)$row['total'];
$monto_seguimiento = (float)$row['monto'];


/* =========================================================
   COTIZACIONES POR ESTATUS
========================================================= */

$estatus_data = [];

$res = $conexion->query("
    SELECT
        estatus,
        COUNT(*) AS cantidad,
        COALESCE(SUM(total), 0) AS monto
    FROM cotizaciones
    WHERE fecha BETWEEN
        '" . $conexion->real_escape_string($fecha_inicio) . "'
        AND
        '" . $conexion->real_escape_string($fecha_fin) . "'
    GROUP BY estatus
    ORDER BY cantidad DESC
");

if ($res) {

    while ($row = $res->fetch_assoc()) {
        $estatus_data[] = $row;
    }
}


/* =========================================================
   COTIZACIONES POR MES
========================================================= */

$mensual = [];

$res = $conexion->query("
    SELECT
        DATE_FORMAT(fecha, '%Y-%m') AS mes,
        COUNT(*) AS cantidad,
        COALESCE(SUM(total), 0) AS monto
    FROM cotizaciones
    WHERE fecha BETWEEN
        '" . $conexion->real_escape_string($fecha_inicio) . "'
        AND
        '" . $conexion->real_escape_string($fecha_fin) . "'
    GROUP BY DATE_FORMAT(fecha, '%Y-%m')
    ORDER BY mes ASC
");

if ($res) {

    while ($row = $res->fetch_assoc()) {
        $mensual[] = $row;
    }
}


/* =========================================================
   SEGUIMIENTOS POR RESULTADO
========================================================= */

$seguimientos_resultado = [];

$res = $conexion->query("
    SELECT
        resultado,
        COUNT(*) AS cantidad
    FROM seguimientos
    WHERE DATE(fecha) BETWEEN
        '" . $conexion->real_escape_string($fecha_inicio) . "'
        AND
        '" . $conexion->real_escape_string($fecha_fin) . "'
    GROUP BY resultado
    ORDER BY cantidad DESC
");

if ($res) {

    while ($row = $res->fetch_assoc()) {
        $seguimientos_resultado[] = $row;
    }
}


/* =========================================================
   SEGUIMIENTOS GENERALES
========================================================= */

$stmt = $conexion->prepare("
    SELECT
        COUNT(*) AS total
    FROM seguimientos
    WHERE DATE(fecha) BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$total_seguimientos =
    (int)$stmt->get_result()->fetch_assoc()['total'];

$stmt->close();


/* =========================================================
   SEGUIMIENTOS PENDIENTES
========================================================= */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE resultado = 'PENDIENTE'
");

$seguimientos_pendientes =
    $res ? (int)$res->fetch_assoc()['total'] : 0;


/* =========================================================
   SEGUIMIENTOS PARA HOY
========================================================= */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE proximo_seguimiento IS NOT NULL
      AND DATE(proximo_seguimiento) = CURDATE()
      AND resultado NOT IN ('ACEPTADO','RECHAZADO')
");

$seguimientos_hoy =
    $res ? (int)$res->fetch_assoc()['total'] : 0;


/* =========================================================
   SEGUIMIENTOS VENCIDOS
========================================================= */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE proximo_seguimiento IS NOT NULL
      AND proximo_seguimiento < CURDATE()
      AND resultado NOT IN ('ACEPTADO','RECHAZADO')
");

$seguimientos_vencidos =
    $res ? (int)$res->fetch_assoc()['total'] : 0;


/* =========================================================
   CLIENTES CON MÁS COTIZACIONES
========================================================= */

$clientes_top = [];

$stmt = $conexion->prepare("
    SELECT
        c.id,
        COALESCE(
            NULLIF(c.razon_social, ''),
            c.nombre
        ) AS cliente,
        COUNT(co.id) AS cotizaciones,
        COALESCE(SUM(co.total), 0) AS monto
    FROM clientes c
    INNER JOIN cotizaciones co
        ON co.cliente_id = c.id
    WHERE co.fecha BETWEEN ? AND ?
    GROUP BY
        c.id,
        c.razon_social,
        c.nombre
    ORDER BY cotizaciones DESC, monto DESC
    LIMIT 10
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $clientes_top[] = $row;
}

$stmt->close();


/* =========================================================
   PRODUCTOS MÁS COTIZADOS
========================================================= */

$productos_top = [];

$stmt = $conexion->prepare("
    SELECT
        cd.descripcion,
        SUM(cd.cantidad) AS cantidad,
        COUNT(DISTINCT cd.cotizacion_id) AS cotizaciones,
        COALESCE(SUM(cd.importe), 0) AS importe
    FROM cotizacion_detalles cd

    INNER JOIN cotizaciones co
        ON co.id = cd.cotizacion_id

    WHERE co.fecha BETWEEN ? AND ?

    GROUP BY cd.descripcion

    ORDER BY cantidad DESC, importe DESC

    LIMIT 10
");

$stmt->bind_param(
    "ss",
    $fecha_inicio,
    $fecha_fin
);

$stmt->execute();

$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $productos_top[] = $row;
}

$stmt->close();


/* =========================================================
   CONVERSIÓN
========================================================= */

$conversion = 0;

if ($total_cotizaciones > 0) {

    $conversion =
        ($total_aceptadas / $total_cotizaciones) * 100;
}


/* =========================================================
   ETIQUETAS
========================================================= */

function texto_estatus($estatus)
{
    $lista = [
        'BORRADOR'       => 'Borrador',
        'ENVIADA'        => 'Enviada',
        'EN_SEGUIMIENTO' => 'En seguimiento',
        'ACEPTADA'       => 'Aceptada',
        'RECHAZADA'      => 'Rechazada',
        'VENCIDA'        => 'Vencida'
    ];

    return $lista[$estatus] ?? $estatus;
}

function texto_resultado($resultado)
{
    $lista = [
        'SIN_RESPUESTA' => 'Sin respuesta',
        'INTERESADO'    => 'Interesado',
        'EN_REVISION'   => 'En revisión',
        'NEGOCIACION'   => 'Negociación',
        'ACEPTADO'      => 'Aceptado',
        'RECHAZADO'     => 'Rechazado',
        'PENDIENTE'     => 'Pendiente',
        'OTRO'          => 'Otro'
    ];

    return $lista[$resultado] ?? $resultado;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reportes | Sistema Comercial</title>

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

        /* =====================================================
           SIDEBAR
        ===================================================== */

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

        .sidebar-header {
            padding: 22px 18px;
            text-align: center;
            border-bottom: 1px solid #374151;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 19px;
        }

        .sidebar-header small {
            display: block;
            margin-top: 6px;
            color: #cbd5e1;
            font-size: 12px;
        }

        .menu-title {
            padding: 18px 18px 7px;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: bold;
        }

        .menu-link {
            display: block;
            padding: 12px 18px;
            color: #e5e7eb;
            text-decoration: none;
            font-size: 14px;
            border-left: 4px solid transparent;
        }

        .menu-link:hover {
            background: #374151;
            color: white;
        }

        .menu-link.active {
            background: #2563eb;
            border-left-color: #93c5fd;
            color: white;
        }

        /* =====================================================
           CONTENIDO
        ===================================================== */

        .content {
            margin-left: 240px;
            padding: 25px;
        }

        .page-header {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0 0 6px;
            font-size: 25px;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* =====================================================
           FILTROS
        ===================================================== */

        .filter-box {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #374151;
        }

        .field input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
        }

        /* =====================================================
           BOTONES
        ===================================================== */

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-gray {
            background: #6b7280;
            color: white;
        }

        /* =====================================================
           INDICADORES
        ===================================================== */

        .cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }

        .card-title {
            color: #6b7280;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .card-number {
            margin-top: 8px;
            font-size: 25px;
            font-weight: bold;
        }

        .blue {
            color: #2563eb;
        }

        .green {
            color: #16a34a;
        }

        .red {
            color: #dc2626;
        }

        .orange {
            color: #d97706;
        }

        .purple {
            color: #7c3aed;
        }

        /* =====================================================
           CONTENEDORES
        ===================================================== */

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e5e7eb;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .panel-body {
            padding: 18px;
        }

        /* =====================================================
           TABLAS
        ===================================================== */

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #1f2937;
            color: white;
            text-align: left;
            padding: 10px;
            font-size: 11px;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .right {
            text-align: right;
        }

        /* =====================================================
           BARRAS
        ===================================================== */

        .bar-row {
            margin-bottom: 15px;
        }

        .bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .bar-bg {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: #2563eb;
            border-radius: 10px;
        }

        /* =====================================================
           CONVERSION
        ===================================================== */

        .conversion-box {
            text-align: center;
            padding: 20px;
        }

        .conversion-number {
            font-size: 42px;
            font-weight: bold;
            color: #16a34a;
        }

        .conversion-text {
            color: #6b7280;
            font-size: 13px;
            margin-top: 5px;
        }

        /* =====================================================
           ESTATUS
        ===================================================== */

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-orange {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge-gray {
            background: #f3f4f6;
            color: #374151;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #6b7280;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .cards {
                grid-template-columns: repeat(3, 1fr);
            }

        }

        @media (max-width: 900px) {

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .content {
                margin-left: 0;
                padding: 15px;
            }

            .cards {
                grid-template-columns: 1fr 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 500px) {

            .cards {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">

    <div class="sidebar-header">

        <h2>
            Sistema Comercial
        </h2>

        <small>
            Gestión comercial
        </small>

    </div>


    <div class="menu-title">
        Principal
    </div>

    <a
        href="index.php"
        class="menu-link"
    >
        Dashboard
    </a>


    <div class="menu-title">
        Clientes
    </div>

    <a
        href="clientes.php"
        class="menu-link"
    >
        Clientes
    </a>


    <div class="menu-title">
        Productos y servicios
    </div>

    <a
        href="productos.php"
        class="menu-link"
    >
        Productos y servicios
    </a>


    <div class="menu-title">
        Cotizaciones
    </div>

    <a
        href="cotizaciones.php"
        class="menu-link"
    >
        Cotizaciones
    </a>

    <a
        href="nueva_cotizacion.php"
        class="menu-link"
    >
        Nueva cotización
    </a>


    <div class="menu-title">
        Seguimiento
    </div>

    <a
        href="seguimientos.php"
        class="menu-link"
    >
        Seguimientos
    </a>

    <a
        href="nuevo_seguimiento.php"
        class="menu-link"
    >
        Nuevo seguimiento
    </a>


    <div class="menu-title">
        Reportes
    </div>

    <a
        href="reportes.php"
        class="menu-link active"
    >
        Reportes
    </a>

    <a
        href="auditoria.php"
        class="menu-link"
    >
        Auditoría
    </a>

</div>


<!-- =========================================================
     CONTENIDO
========================================================= -->

<div class="content">


    <div class="page-header">

        <h1>
            Reportes comerciales
        </h1>

        <p>
            Consulta el comportamiento de cotizaciones, clientes y seguimientos.
        </p>

    </div>


    <!-- =====================================================
         FILTRO DE FECHAS
    ===================================================== -->

    <div class="filter-box">

        <form method="GET">

            <div class="filter-grid">

                <div class="field">

                    <label>
                        Fecha inicial
                    </label>

                    <input
                        type="date"
                        name="fecha_inicio"
                        value="<?= esc($fecha_inicio) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Fecha final
                    </label>

                    <input
                        type="date"
                        name="fecha_fin"
                        value="<?= esc($fecha_fin) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Generar reporte
                </button>


                <a
                    href="reportes.php"
                    class="btn btn-gray"
                >
                    Periodo actual
                </a>

            </div>

        </form>

    </div>


    <!-- =====================================================
         INDICADORES
    ===================================================== -->

    <div class="cards">

        <div class="card">

            <div class="card-title">
                Cotizaciones
            </div>

            <div class="card-number blue">
                <?= number_format($total_cotizaciones) ?>
            </div>

            <small>
                <?= moneda($monto_cotizaciones) ?>
            </small>

        </div>


        <div class="card">

            <div class="card-title">
                Aceptadas
            </div>

            <div class="card-number green">
                <?= number_format($total_aceptadas) ?>
            </div>

            <small>
                <?= moneda($monto_aceptadas) ?>
            </small>

        </div>


        <div class="card">

            <div class="card-title">
                Rechazadas
            </div>

            <div class="card-number red">
                <?= number_format($total_rechazadas) ?>
            </div>

            <small>
                <?= moneda($monto_rechazadas) ?>
            </small>

        </div>


        <div class="card">

            <div class="card-title">
                En seguimiento
            </div>

            <div class="card-number orange">
                <?= number_format($total_seguimiento) ?>
            </div>

            <small>
                <?= moneda($monto_seguimiento) ?>
            </small>

        </div>


        <div class="card">

            <div class="card-title">
                Seguimientos
            </div>

            <div class="card-number purple">
                <?= number_format($total_seguimientos) ?>
            </div>

            <small>
                Actividad registrada
            </small>

        </div>

    </div>


    <!-- =====================================================
         SEGUIMIENTOS PENDIENTES
    ===================================================== -->

    <div class="cards">

        <div class="card">

            <div class="card-title">
                Seguimientos pendientes
            </div>

            <div class="card-number orange">
                <?= number_format($seguimientos_pendientes) ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Seguimientos para hoy
            </div>

            <div class="card-number purple">
                <?= number_format($seguimientos_hoy) ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Seguimientos vencidos
            </div>

            <div class="card-number red">
                <?= number_format($seguimientos_vencidos) ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Conversión
            </div>

            <div class="card-number green">
                <?= number_format($conversion, 1) ?>%
            </div>

        </div>

    </div>


    <!-- =====================================================
         ESTATUS + CONVERSIÓN
    ===================================================== -->

    <div class="grid-2">


        <div class="panel">

            <div class="panel-header">

                <h2>
                    Cotizaciones por estatus
                </h2>

            </div>

            <div class="panel-body">

                <?php if (empty($estatus_data)): ?>

                    <div class="empty">
                        No hay información para el periodo seleccionado.
                    </div>

                <?php else: ?>

                    <?php

                    $maxEstatus = 1;

                    foreach ($estatus_data as $item) {

                        if ((int)$item['cantidad'] > $maxEstatus) {
                            $maxEstatus = (int)$item['cantidad'];
                        }
                    }

                    ?>

                    <?php foreach ($estatus_data as $item): ?>

                        <?php

                        $porcentaje =
                            ((int)$item['cantidad'] / $maxEstatus) * 100;

                        ?>

                        <div class="bar-row">

                            <div class="bar-label">

                                <span>
                                    <?= esc(
                                        texto_estatus(
                                            $item['estatus']
                                        )
                                    ) ?>
                                </span>

                                <strong>
                                    <?= number_format(
                                        (int)$item['cantidad']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="bar-bg">

                                <div
                                    class="bar"
                                    style="width: <?= $porcentaje ?>%;"
                                ></div>

                            </div>

                            <div style="
                                margin-top:4px;
                                font-size:11px;
                                color:#6b7280;
                            ">

                                <?= moneda($item['monto']) ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <div class="panel">

            <div class="panel-header">

                <h2>
                    Conversión comercial
                </h2>

            </div>

            <div class="panel-body">

                <div class="conversion-box">

                    <div class="conversion-number">
                        <?= number_format($conversion, 1) ?>%
                    </div>

                    <div class="conversion-text">

                        Cotizaciones aceptadas respecto al total
                        del periodo seleccionado.

                    </div>

                    <hr style="
                        margin:20px 0;
                        border:0;
                        border-top:1px solid #e5e7eb;
                    ">

                    <div style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:15px;
                    ">

                        <div>

                            <strong>
                                <?= number_format($total_aceptadas) ?>
                            </strong>

                            <div class="conversion-text">
                                Aceptadas
                            </div>

                        </div>

                        <div>

                            <strong>
                                <?= number_format($total_cotizaciones) ?>
                            </strong>

                            <div class="conversion-text">
                                Cotizaciones
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         COTIZACIONES POR MES
    ===================================================== -->

    <div class="panel" style="margin-bottom:20px;">

        <div class="panel-header">

            <h2>
                Cotizaciones por mes
            </h2>

        </div>

        <div class="table-responsive">

            <?php if (empty($mensual)): ?>

                <div class="empty">
                    No hay cotizaciones para el periodo seleccionado.
                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Mes
                            </th>

                            <th>
                                Cotizaciones
                            </th>

                            <th class="right">
                                Monto
                            </th>

                            <th class="right">
                                Promedio
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($mensual as $mes): ?>

                        <tr>

                            <td>
                                <?= esc($mes['mes']) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (int)$mes['cantidad']
                                ) ?>
                            </td>

                            <td class="right">
                                <?= moneda($mes['monto']) ?>
                            </td>

                            <td class="right">

                                <?php

                                $promedio =
                                    (float)$mes['cantidad'] > 0
                                    ? (float)$mes['monto']
                                      / (int)$mes['cantidad']
                                    : 0;

                                ?>

                                <?= moneda($promedio) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         SEGUIMIENTOS + CLIENTES
    ===================================================== -->

    <div class="grid-2">


        <div class="panel">

            <div class="panel-header">

                <h2>
                    Seguimientos por resultado
                </h2>

            </div>

            <div class="table-responsive">

                <?php if (empty($seguimientos_resultado)): ?>

                    <div class="empty">
                        No hay seguimientos para el periodo seleccionado.
                    </div>

                <?php else: ?>

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Resultado
                                </th>

                                <th>
                                    Cantidad
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach (
                            $seguimientos_resultado
                            as $item
                        ): ?>

                            <tr>

                                <td>

                                    <span class="badge badge-blue">

                                        <?= esc(
                                            texto_resultado(
                                                $item['resultado']
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= number_format(
                                        (int)$item['cantidad']
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <div class="panel">

            <div class="panel-header">

                <h2>
                    Clientes con más cotizaciones
                </h2>

            </div>

            <div class="table-responsive">

                <?php if (empty($clientes_top)): ?>

                    <div class="empty">
                        No hay información disponible.
                    </div>

                <?php else: ?>

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Cliente
                                </th>

                                <th>
                                    Cotizaciones
                                </th>

                                <th class="right">
                                    Monto
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($clientes_top as $item): ?>

                            <tr>

                                <td>
                                    <?= esc($item['cliente']) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (int)$item['cotizaciones']
                                    ) ?>
                                </td>

                                <td class="right">
                                    <?= moneda($item['monto']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         PRODUCTOS MÁS COTIZADOS
    ===================================================== -->

    <div class="panel">

        <div class="panel-header">

            <h2>
                Productos y servicios más cotizados
            </h2>

        </div>

        <div class="table-responsive">

            <?php if (empty($productos_top)): ?>

                <div class="empty">
                    No hay productos o servicios cotizados en el periodo seleccionado.
                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Producto / servicio
                            </th>

                            <th>
                                Cantidad
                            </th>

                            <th>
                                Cotizaciones
                            </th>

                            <th class="right">
                                Importe
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($productos_top as $item): ?>

                        <tr>

                            <td>
                                <?= esc($item['descripcion']) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$item['cantidad'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (int)$item['cotizaciones']
                                ) ?>
                            </td>

                            <td class="right">
                                <?= moneda($item['importe']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>