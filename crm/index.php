<?php

// ==========================================================
// CONEXIÓN
// ==========================================================

require_once __DIR__ . '/conexion.php';


// ==========================================================
// CONTADORES
// ==========================================================

$total_clientes = 0;
$total_productos = 0;
$total_cotizaciones = 0;
$seguimientos_pendientes = 0;
$cotizaciones_aceptadas = 0;
$cotizaciones_en_seguimiento = 0;


// ----------------------------------------------------------
// CLIENTES ACTIVOS
// ----------------------------------------------------------

$sql = "SELECT COUNT(*) AS total
        FROM clientes
        WHERE estatus = 'ACTIVO'";

$resultado = $conexion->query($sql);

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_clientes = (int)$fila['total'];
}


// ----------------------------------------------------------
// PRODUCTOS / SERVICIOS ACTIVOS
// ----------------------------------------------------------

$sql = "SELECT COUNT(*) AS total
        FROM productos
        WHERE activo = 1";

$resultado = $conexion->query($sql);

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_productos = (int)$fila['total'];
}


// ----------------------------------------------------------
// COTIZACIONES
// ----------------------------------------------------------

$sql = "SELECT COUNT(*) AS total
        FROM cotizaciones";

$resultado = $conexion->query($sql);

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_cotizaciones = (int)$fila['total'];
}


// ----------------------------------------------------------
// COTIZACIONES ACEPTADAS
// ----------------------------------------------------------

$sql = "SELECT COUNT(*) AS total
        FROM cotizaciones
        WHERE estatus = 'ACEPTADA'";

$resultado = $conexion->query($sql);

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $cotizaciones_aceptadas = (int)$fila['total'];
}


// ----------------------------------------------------------
// COTIZACIONES EN SEGUIMIENTO
// ----------------------------------------------------------

$sql = "SELECT COUNT(*) AS total
        FROM cotizaciones
        WHERE estatus = 'EN_SEGUIMIENTO'";

$resultado = $conexion->query($sql);

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $cotizaciones_en_seguimiento = (int)$fila['total'];
}


// ----------------------------------------------------------
// SEGUIMIENTOS PENDIENTES
// ----------------------------------------------------------

$sql = "SELECT COUNT(*) AS total
        FROM seguimientos
        WHERE proximo_seguimiento IS NOT NULL
        AND proximo_seguimiento <= CURDATE()";

$resultado = $conexion->query($sql);

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $seguimientos_pendientes = (int)$fila['total'];
}


// ==========================================================
// COTIZACIONES RECIENTES
// ==========================================================

$cotizaciones_recientes = [];

$sql = "
    SELECT
        c.id,
        c.folio,
        c.fecha,
        c.total,
        c.estatus,
        cl.nombre AS cliente
    FROM cotizaciones c
    INNER JOIN clientes cl
        ON cl.id = c.cliente_id
    ORDER BY c.id DESC
    LIMIT 5
";

$resultado = $conexion->query($sql);

if ($resultado) {

    while ($fila = $resultado->fetch_assoc()) {
        $cotizaciones_recientes[] = $fila;
    }
}


// ==========================================================
// SEGUIMIENTOS PRÓXIMOS
// ==========================================================

$seguimientos = [];

$sql = "
    SELECT
        s.id,
        s.proximo_seguimiento,
        s.resultado,
        s.responsable,
        cl.nombre AS cliente,
        c.folio
    FROM seguimientos s

    INNER JOIN clientes cl
        ON cl.id = s.cliente_id

    LEFT JOIN cotizaciones c
        ON c.id = s.cotizacion_id

    WHERE s.proximo_seguimiento IS NOT NULL

    ORDER BY s.proximo_seguimiento ASC

    LIMIT 5
";

$resultado = $conexion->query($sql);

if ($resultado) {

    while ($fila = $resultado->fetch_assoc()) {
        $seguimientos[] = $fila;
    }
}


// ==========================================================
// FUNCIONES
// ==========================================================

function formato_moneda($valor)
{
    return '$' . number_format(
        (float)$valor,
        2,
        '.',
        ','
    );
}


function estado_clase($estatus)
{
    switch ($estatus) {

        case 'ACEPTADA':
            return 'estado-aceptada';

        case 'RECHAZADA':
            return 'estado-rechazada';

        case 'ENVIADA':
            return 'estado-enviada';

        case 'EN_SEGUIMIENTO':
            return 'estado-seguimiento';

        case 'VENCIDA':
            return 'estado-vencida';

        default:
            return 'estado-borrador';
    }
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

    <title>Panel Comercial</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #333;
        }


        /* ==================================================
           MENU LATERAL
        ================================================== */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;

            width: 240px;
            height: 100vh;

            background: #1f2937;
            color: white;

            padding: 20px 15px;

            overflow-y: auto;
        }


        .logo {
            text-align: center;
            margin-bottom: 25px;
        }


        .logo h2 {
            margin: 0;
            font-size: 21px;
        }


        .logo span {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #cbd5e1;
        }


        .menu-titulo {
            font-size: 11px;
            color: #94a3b8;

            margin: 20px 10px 8px;

            text-transform: uppercase;
        }


        .menu a {
            display: block;

            color: #e5e7eb;
            text-decoration: none;

            padding: 11px 12px;

            margin-bottom: 4px;

            border-radius: 6px;

            font-size: 14px;

            transition: background 0.2s;
        }


        .menu a:hover {
            background: #374151;
        }


        .menu a.activo {
            background: #2563eb;
            color: white;
        }


        /* ==================================================
           CONTENIDO
        ================================================== */

        .contenido {
            margin-left: 240px;
            padding: 25px;
        }


        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 25px;
        }


        .encabezado h1 {
            margin: 0;
            font-size: 26px;
            color: #1f2937;
        }


        .encabezado p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        .fecha {
            color: #6b7280;
            font-size: 13px;
        }


        /* ==================================================
           TARJETAS
        ================================================== */

        .tarjetas {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(180px, 1fr));

            gap: 18px;

            margin-bottom: 25px;
        }


        .tarjeta {
            background: white;

            border-radius: 10px;

            padding: 20px;

            box-shadow:
                0 2px 8px rgba(0,0,0,0.06);

            border-left: 5px solid #2563eb;
        }


        .tarjeta.clientes {
            border-left-color: #2563eb;
        }


        .tarjeta.productos {
            border-left-color: #059669;
        }


        .tarjeta.cotizaciones {
            border-left-color: #7c3aed;
        }


        .tarjeta.seguimientos {
            border-left-color: #dc2626;
        }


        .tarjeta-titulo {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 10px;
        }


        .tarjeta-numero {
            font-size: 30px;
            font-weight: bold;
            color: #111827;
        }


        .tarjeta-descripcion {
            margin-top: 8px;
            font-size: 12px;
            color: #9ca3af;
        }


        /* ==================================================
           ACCIONES RÁPIDAS
        ================================================== */

        .acciones {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 25px;
        }


        .accion {
            background: white;

            padding: 18px;

            border-radius: 8px;

            text-decoration: none;

            color: #1f2937;

            box-shadow:
                0 2px 7px rgba(0,0,0,0.05);

            border: 1px solid #e5e7eb;

            transition: all 0.2s;
        }


        .accion:hover {
            border-color: #2563eb;

            transform: translateY(-2px);
        }


        .accion-icono {
            font-size: 25px;
            margin-bottom: 8px;
        }


        .accion-titulo {
            font-weight: bold;
            font-size: 14px;
        }


        .accion-descripcion {
            margin-top: 5px;

            font-size: 12px;

            color: #6b7280;
        }


        /* ==================================================
           COLUMNAS
        ================================================== */

        .columnas {
            display: grid;

            grid-template-columns:
                1.5fr 1fr;

            gap: 20px;
        }


        .panel {
            background: white;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0,0,0,0.06);

            overflow: hidden;
        }


        .panel-header {
            padding: 17px 20px;

            border-bottom: 1px solid #e5e7eb;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .panel-header h2 {
            margin: 0;

            font-size: 17px;

            color: #1f2937;
        }


        .panel-header a {
            font-size: 12px;

            color: #2563eb;

            text-decoration: none;
        }


        .tabla-contenedor {
            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;
        }


        th {
            background: #f8fafc;

            color: #64748b;

            font-size: 11px;

            text-transform: uppercase;

            text-align: left;

            padding: 12px;
        }


        td {
            padding: 12px;

            border-top: 1px solid #f1f5f9;

            font-size: 13px;
        }


        .cliente {
            font-weight: bold;
        }


        .folio {
            color: #2563eb;

            font-weight: bold;
        }


        .total {
            font-weight: bold;
        }


        /* ==================================================
           ESTADOS
        ================================================== */

        .estado {
            display: inline-block;

            padding: 5px 8px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: bold;
        }


        .estado-aceptada {
            background: #dcfce7;
            color: #166534;
        }


        .estado-rechazada {
            background: #fee2e2;
            color: #991b1b;
        }


        .estado-enviada {
            background: #dbeafe;
            color: #1e40af;
        }


        .estado-seguimiento {
            background: #fef3c7;
            color: #92400e;
        }


        .estado-vencida {
            background: #f3f4f6;
            color: #374151;
        }


        .estado-borrador {
            background: #e5e7eb;
            color: #374151;
        }


        .sin-datos {
            text-align: center;

            padding: 30px;

            color: #9ca3af;

            font-size: 13px;
        }


        /* ==================================================
           SEGUIMIENTOS
        ================================================== */

        .seguimiento-item {
            padding: 15px 20px;

            border-bottom: 1px solid #f1f5f9;
        }


        .seguimiento-item:last-child {
            border-bottom: none;
        }


        .seguimiento-cliente {
            font-weight: bold;

            font-size: 14px;
        }


        .seguimiento-folio {
            font-size: 11px;

            color: #2563eb;

            margin-top: 3px;
        }


        .seguimiento-fecha {
            margin-top: 7px;

            font-size: 12px;

            color: #64748b;
        }


        .seguimiento-vencido {
            color: #dc2626;

            font-weight: bold;
        }


        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 1100px) {

            .tarjetas {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .acciones {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .columnas {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 700px) {

            .sidebar {
                position: relative;

                width: 100%;

                height: auto;
            }

            .contenido {
                margin-left: 0;
            }

            .tarjetas,
            .acciones {
                grid-template-columns: 1fr;
            }

            .encabezado {
                display: block;
            }

            .fecha {
                margin-top: 10px;
            }
        }

    </style>

</head>


<body>


<!-- ======================================================
     MENU LATERAL
====================================================== -->

<aside class="sidebar">

    <div class="logo">

        <h2>GRUPO PROTEC</h2>

        <span>
            Sistema Comercial
        </span>

    </div>


    <nav class="menu">

        <div class="menu-titulo">
            Principal
        </div>

        <a href="index.php" class="activo">
            🏠 &nbsp; Panel principal
        </a>


        <div class="menu-titulo">
            Clientes
        </div>

        <a href="clientes.php">
            👥 &nbsp; Clientes
        </a>


        <div class="menu-titulo">
            Comercial
        </div>

        <a href="cotizaciones.php">
            📄 &nbsp; Cotizaciones
        </a>

        <a href="seguimientos.php">
            📞 &nbsp; Seguimiento comercial
        </a>


        <div class="menu-titulo">
            Catálogo
        </div>

        <a href="productos.php">
            📦 &nbsp; Productos / Servicios
        </a>


        <div class="menu-titulo">
            Reportes
        </div>

        <a href="reportes.php">
            📊 &nbsp; Reportes
        </a>


        <div class="menu-titulo">
            Sistema
        </div>

        <a href="auditoria.php">
            🔎 &nbsp; Auditoría
        </a>

    </nav>

</aside>


<!-- ======================================================
     CONTENIDO PRINCIPAL
====================================================== -->

<main class="contenido">


    <!-- ENCABEZADO -->

    <div class="encabezado">

        <div>

            <h1>
                Panel Comercial
            </h1>

            <p>
                Resumen general de la actividad comercial
            </p>

        </div>


        <div class="fecha">

            <?php echo date('d/m/Y'); ?>

        </div>

    </div>


    <!-- ==================================================
         TARJETAS
    ================================================== -->

    <section class="tarjetas">


        <div class="tarjeta clientes">

            <div class="tarjeta-titulo">
                CLIENTES ACTIVOS
            </div>

            <div class="tarjeta-numero">
                <?php echo $total_clientes; ?>
            </div>

            <div class="tarjeta-descripcion">
                Clientes registrados
            </div>

        </div>


        <div class="tarjeta productos">

            <div class="tarjeta-titulo">
                PRODUCTOS / SERVICIOS
            </div>

            <div class="tarjeta-numero">
                <?php echo $total_productos; ?>
            </div>

            <div class="tarjeta-descripcion">
                Elementos activos
            </div>

        </div>


        <div class="tarjeta cotizaciones">

            <div class="tarjeta-titulo">
                COTIZACIONES
            </div>

            <div class="tarjeta-numero">
                <?php echo $total_cotizaciones; ?>
            </div>

            <div class="tarjeta-descripcion">
                Total registrado
            </div>

        </div>


        <div class="tarjeta seguimientos">

            <div class="tarjeta-titulo">
                SEGUIMIENTOS PENDIENTES
            </div>

            <div class="tarjeta-numero">
                <?php echo $seguimientos_pendientes; ?>
            </div>

            <div class="tarjeta-descripcion">
                Requieren atención
            </div>

        </div>


    </section>


    <!-- ==================================================
         ACCIONES RÁPIDAS
    ================================================== -->

    <section class="acciones">


        <a href="nuevo_cliente.php" class="accion">

            <div class="accion-icono">
                👤
            </div>

            <div class="accion-titulo">
                Nuevo cliente
            </div>

            <div class="accion-descripcion">
                Registrar un nuevo cliente
            </div>

        </a>


        <a href="nueva_cotizacion.php" class="accion">

            <div class="accion-icono">
                📄
            </div>

            <div class="accion-titulo">
                Nueva cotización
            </div>

            <div class="accion-descripcion">
                Crear una cotización
            </div>

        </a>


        <a href="nuevo_seguimiento.php" class="accion">

            <div class="accion-icono">
                📞
            </div>

            <div class="accion-titulo">
                Registrar seguimiento
            </div>

            <div class="accion-descripcion">
                Registrar contacto con cliente
            </div>

        </a>


        <a href="productos.php" class="accion">

            <div class="accion-icono">
                📦
            </div>

            <div class="accion-titulo">
                Catálogo
            </div>

            <div class="accion-descripcion">
                Productos y servicios
            </div>

        </a>


    </section>


    <!-- ==================================================
         COLUMNAS
    ================================================== -->

    <section class="columnas">


        <!-- ==============================================
             COTIZACIONES RECIENTES
        =============================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    Cotizaciones recientes
                </h2>

                <a href="cotizaciones.php">
                    Ver todas
                </a>

            </div>


            <div class="tabla-contenedor">

                <?php if (count($cotizaciones_recientes) > 0): ?>

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Folio
                                </th>

                                <th>
                                    Cliente
                                </th>

                                <th>
                                    Fecha
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Estado
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($cotizaciones_recientes as $cotizacion): ?>

                                <tr>

                                    <td>

                                        <span class="folio">

                                            <?php
                                            echo htmlspecialchars(
                                                $cotizacion['folio']
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="cliente">

                                            <?php
                                            echo htmlspecialchars(
                                                $cotizacion['cliente']
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            'd/m/Y',
                                            strtotime(
                                                $cotizacion['fecha']
                                            )
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <span class="total">

                                            <?php
                                            echo formato_moneda(
                                                $cotizacion['total']
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span
                                            class="estado <?php echo estado_clase(
                                                $cotizacion['estatus']
                                            ); ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $cotizacion['estatus']
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>


                <?php else: ?>

                    <div class="sin-datos">

                        No hay cotizaciones registradas todavía.

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- ==============================================
             SEGUIMIENTOS
        =============================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    Próximos seguimientos
                </h2>

                <a href="seguimientos.php">
                    Ver todos
                </a>

            </div>


            <?php if (count($seguimientos) > 0): ?>


                <?php foreach ($seguimientos as $seguimiento): ?>


                    <?php

                    $fechaSeguimiento =
                        strtotime(
                            $seguimiento['proximo_seguimiento']
                        );

                    $hoy =
                        strtotime(
                            date('Y-m-d')
                        );

                    $claseFecha = '';

                    if ($fechaSeguimiento < $hoy) {
                        $claseFecha = 'seguimiento-vencido';
                    }

                    ?>


                    <div class="seguimiento-item">


                        <div class="seguimiento-cliente">

                            <?php
                            echo htmlspecialchars(
                                $seguimiento['cliente']
                            );
                            ?>

                        </div>


                        <?php if (!empty($seguimiento['folio'])): ?>

                            <div class="seguimiento-folio">

                                <?php
                                echo htmlspecialchars(
                                    $seguimiento['folio']
                                );
                                ?>

                            </div>

                        <?php endif; ?>


                        <div
                            class="seguimiento-fecha <?php echo $claseFecha; ?>"
                        >

                            Seguimiento:

                            <?php
                            echo date(
                                'd/m/Y',
                                $fechaSeguimiento
                            );
                            ?>

                        </div>


                        <?php if (!empty($seguimiento['responsable'])): ?>

                            <div
                                style="
                                    margin-top:5px;
                                    font-size:11px;
                                    color:#94a3b8;
                                "
                            >

                                Responsable:
                                <?php
                                echo htmlspecialchars(
                                    $seguimiento['responsable']
                                );
                                ?>

                            </div>

                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>

                <div class="sin-datos">

                    No hay seguimientos registrados.

                </div>

            <?php endif; ?>


        </div>


    </section>


    <!-- ==================================================
         RESUMEN COMERCIAL
    ================================================== -->

    <section
        class="panel"
        style="margin-top:20px;"
    >

        <div class="panel-header">

            <h2>
                Resumen comercial
            </h2>

        </div>


        <div
            style="
                display:grid;
                grid-template-columns:
                    repeat(2, 1fr);
                gap:20px;
                padding:20px;
            "
        >


            <div>

                <div
                    style="
                        font-size:12px;
                        color:#64748b;
                    "
                >
                    Cotizaciones aceptadas
                </div>

                <div
                    style="
                        font-size:26px;
                        font-weight:bold;
                        color:#166534;
                        margin-top:5px;
                    "
                >

                    <?php
                    echo $cotizaciones_aceptadas;
                    ?>

                </div>

            </div>


            <div>

                <div
                    style="
                        font-size:12px;
                        color:#64748b;
                    "
                >
                    Cotizaciones en seguimiento
                </div>

                <div
                    style="
                        font-size:26px;
                        font-weight:bold;
                        color:#92400e;
                        margin-top:5px;
                    "
                >

                    <?php
                    echo $cotizaciones_en_seguimiento;
                    ?>

                </div>

            </div>


        </div>

    </section>


</main>


</body>

</html>
