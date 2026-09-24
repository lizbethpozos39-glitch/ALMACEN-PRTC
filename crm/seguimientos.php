<?php

require_once __DIR__ . '/conexion.php';

/* =========================================================
   FUNCIONES
========================================================= */

function esc($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function fecha_mx($fecha)
{
    if (empty($fecha)) {
        return '-';
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return $fecha;
    }

    return date('d/m/Y', $timestamp);
}

function fecha_hora_mx($fecha)
{
    if (empty($fecha)) {
        return '-';
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return $fecha;
    }

    return date('d/m/Y H:i', $timestamp);
}

function clase_resultado($resultado)
{
    switch ($resultado) {

        case 'ACEPTADO':
            return 'resultado-aceptado';

        case 'RECHAZADO':
            return 'resultado-rechazado';

        case 'NEGOCIACION':
            return 'resultado-negociacion';

        case 'INTERESADO':
            return 'resultado-interesado';

        case 'EN_REVISION':
            return 'resultado-revision';

        case 'SIN_RESPUESTA':
            return 'resultado-sin-respuesta';

        case 'PENDIENTE':
        default:
            return 'resultado-pendiente';
    }
}

function texto_resultado($resultado)
{
    $textos = [
        'SIN_RESPUESTA' => 'Sin respuesta',
        'INTERESADO'    => 'Interesado',
        'EN_REVISION'   => 'En revisión',
        'NEGOCIACION'   => 'Negociación',
        'ACEPTADO'      => 'Aceptado',
        'RECHAZADO'     => 'Rechazado',
        'PENDIENTE'     => 'Pendiente',
        'OTRO'          => 'Otro'
    ];

    return $textos[$resultado] ?? $resultado;
}

function texto_contacto($tipo)
{
    $textos = [
        'LLAMADA'   => 'Llamada',
        'WHATSAPP'  => 'WhatsApp',
        'CORREO'    => 'Correo',
        'VISITA'    => 'Visita',
        'REUNION'   => 'Reunión',
        'OTRO'      => 'Otro'
    ];

    return $textos[$tipo] ?? $tipo;
}

/* =========================================================
   FILTROS
========================================================= */

$buscar = trim($_GET['buscar'] ?? '');
$tipo_contacto = trim($_GET['tipo_contacto'] ?? '');
$resultado = trim($_GET['resultado'] ?? '');
$filtro_fecha = trim($_GET['filtro_fecha'] ?? '');

/* =========================================================
   CONSULTA PRINCIPAL
========================================================= */

$sql = "
    SELECT
        s.id,
        s.cliente_id,
        s.cotizacion_id,
        s.fecha,
        s.tipo_contacto,
        s.resultado,
        s.comentarios,
        s.proximo_seguimiento,
        s.responsable,
        s.fecha_registro,

        c.nombre AS cliente_nombre,
        c.razon_social,
        c.telefono,
        c.correo,

        co.folio AS cotizacion_folio,
        co.total AS cotizacion_total,
        co.estatus AS cotizacion_estatus

    FROM seguimientos s

    INNER JOIN clientes c
        ON c.id = s.cliente_id

    LEFT JOIN cotizaciones co
        ON co.id = s.cotizacion_id

    WHERE 1=1
";

$parametros = [];
$tipos = "";

/* =========================================================
   BUSCADOR
========================================================= */

if ($buscar !== '') {

    $sql .= "
        AND (
            c.nombre LIKE ?
            OR c.razon_social LIKE ?
            OR s.responsable LIKE ?
            OR s.comentarios LIKE ?
            OR co.folio LIKE ?
        )
    ";

    $buscarLike = '%' . $buscar . '%';

    $parametros[] = $buscarLike;
    $parametros[] = $buscarLike;
    $parametros[] = $buscarLike;
    $parametros[] = $buscarLike;
    $parametros[] = $buscarLike;

    $tipos .= "sssss";
}

/* =========================================================
   FILTRO TIPO DE CONTACTO
========================================================= */

if ($tipo_contacto !== '') {

    $sql .= " AND s.tipo_contacto = ? ";

    $parametros[] = $tipo_contacto;
    $tipos .= "s";
}

/* =========================================================
   FILTRO RESULTADO
========================================================= */

if ($resultado !== '') {

    $sql .= " AND s.resultado = ? ";

    $parametros[] = $resultado;
    $tipos .= "s";
}

/* =========================================================
   FILTROS DE FECHA
========================================================= */

if ($filtro_fecha === 'HOY') {

    $sql .= " AND DATE(s.proximo_seguimiento) = CURDATE() ";

} elseif ($filtro_fecha === 'VENCIDOS') {

    $sql .= "
        AND s.proximo_seguimiento IS NOT NULL
        AND s.proximo_seguimiento < CURDATE()
        AND s.resultado NOT IN ('ACEPTADO','RECHAZADO')
    ";

} elseif ($filtro_fecha === 'PROXIMOS') {

    $sql .= "
        AND s.proximo_seguimiento IS NOT NULL
        AND s.proximo_seguimiento >= CURDATE()
        AND s.proximo_seguimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ";

}

/* =========================================================
   ORDEN
========================================================= */

$sql .= "
    ORDER BY
        CASE
            WHEN s.proximo_seguimiento IS NOT NULL
                 AND s.proximo_seguimiento < CURDATE()
                 AND s.resultado NOT IN ('ACEPTADO','RECHAZADO')
            THEN 0

            WHEN DATE(s.proximo_seguimiento) = CURDATE()
            THEN 1

            WHEN s.proximo_seguimiento IS NOT NULL
            THEN 2

            ELSE 3
        END,
        s.proximo_seguimiento ASC,
        s.fecha DESC
";

/* =========================================================
   EJECUTAR
========================================================= */

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();

$resultados = $stmt->get_result();

/* =========================================================
   INDICADORES
========================================================= */

/* Total seguimientos */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
");

$total_seguimientos = $res ? (int)$res->fetch_assoc()['total'] : 0;


/* Pendientes */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE resultado = 'PENDIENTE'
");

$pendientes = $res ? (int)$res->fetch_assoc()['total'] : 0;


/* Seguimientos para hoy */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE proximo_seguimiento IS NOT NULL
      AND DATE(proximo_seguimiento) = CURDATE()
      AND resultado NOT IN ('ACEPTADO','RECHAZADO')
");

$hoy = $res ? (int)$res->fetch_assoc()['total'] : 0;


/* Vencidos */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE proximo_seguimiento IS NOT NULL
      AND proximo_seguimiento < CURDATE()
      AND resultado NOT IN ('ACEPTADO','RECHAZADO')
");

$vencidos = $res ? (int)$res->fetch_assoc()['total'] : 0;


/* Próximos 7 días */

$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE proximo_seguimiento IS NOT NULL
      AND proximo_seguimiento >= CURDATE()
      AND proximo_seguimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND resultado NOT IN ('ACEPTADO','RECHAZADO')
");

$proximos = $res ? (int)$res->fetch_assoc()['total'] : 0;

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Seguimientos | Sistema Comercial</title>

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
            z-index: 1000;
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

        .header-actions {
            margin-top: 18px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-gray {
            background: #6b7280;
            color: white;
        }

        .btn-gray:hover {
            background: #4b5563;
        }

        .btn-green {
            background: #16a34a;
            color: white;
        }

        .btn-green:hover {
            background: #15803d;
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
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .card-number {
            font-size: 27px;
            font-weight: bold;
            margin-top: 8px;
        }

        .card-total .card-number {
            color: #2563eb;
        }

        .card-pendiente .card-number {
            color: #d97706;
        }

        .card-hoy .card-number {
            color: #7c3aed;
        }

        .card-vencido .card-number {
            color: #dc2626;
        }

        .card-proximo .card-number {
            color: #16a34a;
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
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 10px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #374151;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 13px;
        }

        /* =====================================================
           TABLA
        ===================================================== */

        .table-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }

        .table-header {
            padding: 17px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1150px;
        }

        th {
            background: #1f2937;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-size: 12px;
            white-space: nowrap;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
            vertical-align: top;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .cliente {
            font-weight: bold;
            color: #111827;
        }

        .subtexto {
            display: block;
            color: #6b7280;
            font-size: 11px;
            margin-top: 3px;
        }

        .folio {
            color: #2563eb;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .tipo {
            background: #e5e7eb;
            color: #374151;
        }

        .resultado-aceptado {
            background: #dcfce7;
            color: #166534;
        }

        .resultado-rechazado {
            background: #fee2e2;
            color: #991b1b;
        }

        .resultado-negociacion {
            background: #fef3c7;
            color: #92400e;
        }

        .resultado-interesado {
            background: #dbeafe;
            color: #1e40af;
        }

        .resultado-revision {
            background: #ede9fe;
            color: #5b21b6;
        }

        .resultado-sin-respuesta {
            background: #f3f4f6;
            color: #374151;
        }

        .resultado-pendiente {
            background: #ffedd5;
            color: #9a3412;
        }

        .fecha-vencida {
            color: #dc2626;
            font-weight: bold;
        }

        .fecha-hoy {
            color: #7c3aed;
            font-weight: bold;
        }

        .fecha-normal {
            color: #374151;
        }

        .acciones {
            white-space: nowrap;
        }

        .btn-small {
            display: inline-block;
            padding: 7px 9px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 11px;
            font-weight: bold;
            margin-right: 3px;
        }

        .btn-view {
            background: #e0e7ff;
            color: #3730a3;
        }

        .btn-cot {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .empty {
            text-align: center;
            padding: 45px 20px;
            color: #6b7280;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .cards {
                grid-template-columns: repeat(3, 1fr);
            }

            .filter-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        @media (max-width: 800px) {

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
        <h2>Sistema Comercial</h2>
        <small>Gestión comercial</small>
    </div>

    <div class="menu-title">Principal</div>

    <a href="index.php" class="menu-link">
        Dashboard
    </a>

    <div class="menu-title">Clientes</div>

    <a href="clientes.php" class="menu-link">
        Clientes
    </a>

    <div class="menu-title">Productos y servicios</div>

    <a href="productos.php" class="menu-link">
        Productos y servicios
    </a>

    <div class="menu-title">Cotizaciones</div>

    <a href="cotizaciones.php" class="menu-link">
        Cotizaciones
    </a>

    <a href="nueva_cotizacion.php" class="menu-link">
        Nueva cotización
    </a>

    <div class="menu-title">Seguimiento</div>

    <a href="seguimientos.php" class="menu-link active">
        Seguimientos
    </a>

    <a href="nuevo_seguimiento.php" class="menu-link">
        Nuevo seguimiento
    </a>

    <div class="menu-title">Reportes</div>

    <a href="reportes.php" class="menu-link">
        Reportes
    </a>

    <a href="auditoria.php" class="menu-link">
        Auditoría
    </a>

</div>


<!-- =========================================================
     CONTENIDO
========================================================= -->

<div class="content">

    <div class="page-header">

        <h1>Seguimiento comercial</h1>

        <p>
            Control de contactos, resultados y próximas acciones con clientes.
        </p>

        <div class="header-actions">

            <a href="nuevo_seguimiento.php" class="btn btn-primary">
                + Nuevo seguimiento
            </a>

        </div>

    </div>


    <!-- =====================================================
         INDICADORES
    ===================================================== -->

    <div class="cards">

        <div class="card card-total">
            <div class="card-title">
                Total seguimientos
            </div>

            <div class="card-number">
                <?= number_format($total_seguimientos) ?>
            </div>
        </div>


        <div class="card card-pendiente">
            <div class="card-title">
                Pendientes
            </div>

            <div class="card-number">
                <?= number_format($pendientes) ?>
            </div>
        </div>


        <div class="card card-hoy">
            <div class="card-title">
                Para hoy
            </div>

            <div class="card-number">
                <?= number_format($hoy) ?>
            </div>
        </div>


        <div class="card card-vencido">
            <div class="card-title">
                Vencidos
            </div>

            <div class="card-number">
                <?= number_format($vencidos) ?>
            </div>
        </div>


        <div class="card card-proximo">
            <div class="card-title">
                Próximos 7 días
            </div>

            <div class="card-number">
                <?= number_format($proximos) ?>
            </div>
        </div>

    </div>


    <!-- =====================================================
         FILTROS
    ===================================================== -->

    <div class="filter-box">

        <form method="GET">

            <div class="filter-grid">

                <div class="field">

                    <label>Buscar</label>

                    <input
                        type="text"
                        name="buscar"
                        value="<?= esc($buscar) ?>"
                        placeholder="Cliente, folio, responsable o comentario..."
                    >

                </div>


                <div class="field">

                    <label>Tipo de contacto</label>

                    <select name="tipo_contacto">

                        <option value="">Todos</option>

                        <option value="LLAMADA"
                            <?= $tipo_contacto === 'LLAMADA' ? 'selected' : '' ?>>
                            Llamada
                        </option>

                        <option value="WHATSAPP"
                            <?= $tipo_contacto === 'WHATSAPP' ? 'selected' : '' ?>>
                            WhatsApp
                        </option>

                        <option value="CORREO"
                            <?= $tipo_contacto === 'CORREO' ? 'selected' : '' ?>>
                            Correo
                        </option>

                        <option value="VISITA"
                            <?= $tipo_contacto === 'VISITA' ? 'selected' : '' ?>>
                            Visita
                        </option>

                        <option value="REUNION"
                            <?= $tipo_contacto === 'REUNION' ? 'selected' : '' ?>>
                            Reunión
                        </option>

                        <option value="OTRO"
                            <?= $tipo_contacto === 'OTRO' ? 'selected' : '' ?>>
                            Otro
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>Resultado</label>

                    <select name="resultado">

                        <option value="">Todos</option>

                        <option value="PENDIENTE"
                            <?= $resultado === 'PENDIENTE' ? 'selected' : '' ?>>
                            Pendiente
                        </option>

                        <option value="INTERESADO"
                            <?= $resultado === 'INTERESADO' ? 'selected' : '' ?>>
                            Interesado
                        </option>

                        <option value="EN_REVISION"
                            <?= $resultado === 'EN_REVISION' ? 'selected' : '' ?>>
                            En revisión
                        </option>

                        <option value="NEGOCIACION"
                            <?= $resultado === 'NEGOCIACION' ? 'selected' : '' ?>>
                            Negociación
                        </option>

                        <option value="ACEPTADO"
                            <?= $resultado === 'ACEPTADO' ? 'selected' : '' ?>>
                            Aceptado
                        </option>

                        <option value="RECHAZADO"
                            <?= $resultado === 'RECHAZADO' ? 'selected' : '' ?>>
                            Rechazado
                        </option>

                        <option value="SIN_RESPUESTA"
                            <?= $resultado === 'SIN_RESPUESTA' ? 'selected' : '' ?>>
                            Sin respuesta
                        </option>

                        <option value="OTRO"
                            <?= $resultado === 'OTRO' ? 'selected' : '' ?>>
                            Otro
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>Próximo seguimiento</label>

                    <select name="filtro_fecha">

                        <option value="">Todos</option>

                        <option value="HOY"
                            <?= $filtro_fecha === 'HOY' ? 'selected' : '' ?>>
                            Hoy
                        </option>

                        <option value="VENCIDOS"
                            <?= $filtro_fecha === 'VENCIDOS' ? 'selected' : '' ?>>
                            Vencidos
                        </option>

                        <option value="PROXIMOS"
                            <?= $filtro_fecha === 'PROXIMOS' ? 'selected' : '' ?>>
                            Próximos 7 días
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Filtrar
                </button>


                <a
                    href="seguimientos.php"
                    class="btn btn-gray"
                >
                    Limpiar
                </a>

            </div>

        </form>

    </div>


    <!-- =====================================================
         TABLA
    ===================================================== -->

    <div class="table-box">

        <div class="table-header">

            <h2>
                Historial de seguimientos
            </h2>

        </div>


        <div class="table-responsive">

            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>

                        <th>Cliente</th>

                        <th>Cotización</th>

                        <th>Contacto</th>

                        <th>Resultado</th>

                        <th>Comentarios</th>

                        <th>Próximo seguimiento</th>

                        <th>Responsable</th>

                        <th>Acciones</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($resultados->num_rows === 0): ?>

                    <tr>

                        <td colspan="9">

                            <div class="empty">

                                No se encontraron seguimientos con los filtros seleccionados.

                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php while ($fila = $resultados->fetch_assoc()): ?>

                        <?php

                        $proximo = $fila['proximo_seguimiento'];

                        $claseFecha = 'fecha-normal';

                        $textoFecha = fecha_mx($proximo);

                        if (!empty($proximo)) {

                            $timestampProximo = strtotime($proximo);

                            $hoyTimestamp = strtotime(date('Y-m-d'));

                            if (
                                $timestampProximo < $hoyTimestamp &&
                                !in_array(
                                    $fila['resultado'],
                                    ['ACEPTADO', 'RECHAZADO'],
                                    true
                                )
                            ) {
                                $claseFecha = 'fecha-vencida';
                                $textoFecha .= ' · VENCIDO';

                            } elseif (
                                date('Y-m-d', $timestampProximo) === date('Y-m-d')
                            ) {
                                $claseFecha = 'fecha-hoy';
                                $textoFecha .= ' · HOY';
                            }
                        }

                        ?>

                        <tr>

                            <!-- FECHA -->

                            <td>

                                <?= fecha_hora_mx($fila['fecha']) ?>

                            </td>


                            <!-- CLIENTE -->

                            <td>

                                <div class="cliente">

                                    <?= esc(
                                        $fila['razon_social']
                                        ?: $fila['cliente_nombre']
                                    ) ?>

                                </div>

                                <?php if (
                                    !empty($fila['razon_social']) &&
                                    $fila['razon_social'] !== $fila['cliente_nombre']
                                ): ?>

                                    <span class="subtexto">
                                        <?= esc($fila['cliente_nombre']) ?>
                                    </span>

                                <?php endif; ?>

                                <?php if (!empty($fila['telefono'])): ?>

                                    <span class="subtexto">
                                        Tel: <?= esc($fila['telefono']) ?>
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- COTIZACION -->

                            <td>

                                <?php if (!empty($fila['cotizacion_id'])): ?>

                                    <a
                                        href="ver_cotizacion.php?id=<?= (int)$fila['cotizacion_id'] ?>"
                                        class="folio"
                                    >
                                        <?= esc($fila['cotizacion_folio']) ?>
                                    </a>

                                    <?php if ($fila['cotizacion_total'] !== null): ?>

                                        <span class="subtexto">

                                            $
                                            <?= number_format(
                                                (float)$fila['cotizacion_total'],
                                                2
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="subtexto">
                                        Sin cotización
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- TIPO CONTACTO -->

                            <td>

                                <span class="badge tipo">

                                    <?= esc(
                                        texto_contacto(
                                            $fila['tipo_contacto']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- RESULTADO -->

                            <td>

                                <span
                                    class="badge <?= esc(
                                        clase_resultado(
                                            $fila['resultado']
                                        )
                                    ) ?>"
                                >

                                    <?= esc(
                                        texto_resultado(
                                            $fila['resultado']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- COMENTARIOS -->

                            <td>

                                <?php if (!empty($fila['comentarios'])): ?>

                                    <?= nl2br(
                                        esc(
                                            mb_strimwidth(
                                                $fila['comentarios'],
                                                0,
                                                100,
                                                '...'
                                            )
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span class="subtexto">
                                        Sin comentarios
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- PROXIMO -->

                            <td>

                                <span class="<?= $claseFecha ?>">

                                    <?= esc($textoFecha) ?>

                                </span>

                            </td>


                            <!-- RESPONSABLE -->

                            <td>

                                <?= !empty($fila['responsable'])
                                    ? esc($fila['responsable'])
                                    : '-' ?>

                            </td>


                            <!-- ACCIONES -->

                            <td class="acciones">

                                <?php if (!empty($fila['cotizacion_id'])): ?>

                                    <a
                                        href="ver_cotizacion.php?id=<?= (int)$fila['cotizacion_id'] ?>"
                                        class="btn-small btn-cot"
                                    >
                                        Cotización
                                    </a>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>