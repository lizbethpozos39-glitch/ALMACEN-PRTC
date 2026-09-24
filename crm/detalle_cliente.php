<?php

require_once __DIR__ . '/conexion.php';


/* =========================================================
   VALIDAR ID DEL CLIENTE
========================================================= */

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: clientes.php");
    exit;
}


/* =========================================================
   MENSAJES
========================================================= */

$mensaje = "";
$tipo_mensaje = "success";

if (isset($_GET["mensaje"])) {

    switch ($_GET["mensaje"]) {

        case "creado":
            $mensaje = "El cliente fue registrado correctamente.";
            break;

        case "actualizado":
            $mensaje = "Los datos del cliente fueron actualizados correctamente.";
            break;

        default:
            $mensaje = "";
            break;
    }
}


/* =========================================================
   OBTENER CLIENTE
========================================================= */

$sql_cliente = "
    SELECT
        id,
        tipo_cliente,
        nombre,
        razon_social,
        rfc,
        contacto,
        telefono,
        telefono2,
        correo,
        calle,
        numero,
        colonia,
        ciudad,
        estado,
        codigo_postal,
        notas,
        estatus,
        fecha_registro,
        fecha_actualizacion
    FROM clientes
    WHERE id = ?
    LIMIT 1
";

$stmt_cliente = $conexion->prepare($sql_cliente);

if (!$stmt_cliente) {
    die("Error al preparar la consulta del cliente: " . $conexion->error);
}

$stmt_cliente->bind_param("i", $id);
$stmt_cliente->execute();

$resultado_cliente = $stmt_cliente->get_result();

if ($resultado_cliente->num_rows === 0) {

    $stmt_cliente->close();

    header("Location: clientes.php");
    exit;
}

$cliente = $resultado_cliente->fetch_assoc();

$stmt_cliente->close();


/* =========================================================
   ESTADÍSTICAS DEL CLIENTE
========================================================= */

/*
 * Total de cotizaciones
 */

$sql_total_cotizaciones = "
    SELECT COUNT(*) AS total
    FROM cotizaciones
    WHERE cliente_id = ?
";

$stmt = $conexion->prepare($sql_total_cotizaciones);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();
$total_cotizaciones = (int) $resultado->fetch_assoc()["total"];

$stmt->close();


/*
 * Cotizaciones aceptadas
 */

$sql_aceptadas = "
    SELECT COUNT(*) AS total
    FROM cotizaciones
    WHERE cliente_id = ?
      AND estatus = 'ACEPTADA'
";

$stmt = $conexion->prepare($sql_aceptadas);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();
$total_aceptadas = (int) $resultado->fetch_assoc()["total"];

$stmt->close();


/*
 * Cotizaciones en seguimiento
 */

$sql_seguimiento = "
    SELECT COUNT(*) AS total
    FROM cotizaciones
    WHERE cliente_id = ?
      AND estatus = 'EN_SEGUIMIENTO'
";

$stmt = $conexion->prepare($sql_seguimiento);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();
$total_en_seguimiento = (int) $resultado->fetch_assoc()["total"];

$stmt->close();


/*
 * Total de seguimientos
 */

$sql_total_seguimientos = "
    SELECT COUNT(*) AS total
    FROM seguimientos
    WHERE cliente_id = ?
";

$stmt = $conexion->prepare($sql_total_seguimientos);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();
$total_seguimientos = (int) $resultado->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   COTIZACIONES DEL CLIENTE
========================================================= */

$sql_cotizaciones = "
    SELECT
        id,
        folio,
        fecha,
        vigencia,
        subtotal,
        descuento,
        iva,
        total,
        estatus,
        fecha_seguimiento
    FROM cotizaciones
    WHERE cliente_id = ?
    ORDER BY fecha DESC, id DESC
    LIMIT 10
";

$stmt_cotizaciones = $conexion->prepare($sql_cotizaciones);
$stmt_cotizaciones->bind_param("i", $id);
$stmt_cotizaciones->execute();

$cotizaciones = $stmt_cotizaciones->get_result();


/* =========================================================
   SEGUIMIENTOS DEL CLIENTE
========================================================= */

$sql_seguimientos = "
    SELECT
        s.id,
        s.fecha,
        s.tipo_contacto,
        s.resultado,
        s.comentarios,
        s.proximo_seguimiento,
        s.responsable,
        c.folio
    FROM seguimientos s
    LEFT JOIN cotizaciones c
        ON c.id = s.cotizacion_id
    WHERE s.cliente_id = ?
    ORDER BY s.fecha DESC, s.id DESC
    LIMIT 10
";

$stmt_seguimientos = $conexion->prepare($sql_seguimientos);
$stmt_seguimientos->bind_param("i", $id);
$stmt_seguimientos->execute();

$seguimientos = $stmt_seguimientos->get_result();


/* =========================================================
   FUNCIONES AUXILIARES
========================================================= */

function esc($valor)
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


function formato_fecha($fecha)
{
    if (!$fecha) {
        return "-";
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return $fecha;
    }

    return date("d/m/Y", $timestamp);
}


function formato_fecha_hora($fecha)
{
    if (!$fecha) {
        return "-";
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return $fecha;
    }

    return date("d/m/Y H:i", $timestamp);
}


function formato_moneda($cantidad)
{
    return "$" . number_format(
        (float) $cantidad,
        2,
        ".",
        ","
    );
}


function texto_estatus($estatus)
{
    $estados = [

        "BORRADOR"       => "Borrador",
        "ENVIADA"        => "Enviada",
        "EN_SEGUIMIENTO" => "En seguimiento",
        "ACEPTADA"       => "Aceptada",
        "RECHAZADA"      => "Rechazada",
        "VENCIDA"        => "Vencida"
    ];

    return $estados[$estatus] ?? $estatus;
}


function clase_estatus($estatus)
{
    switch ($estatus) {

        case "ACEPTADA":
            return "estado-aceptada";

        case "EN_SEGUIMIENTO":
            return "estado-seguimiento";

        case "ENVIADA":
            return "estado-enviada";

        case "RECHAZADA":
            return "estado-rechazada";

        case "VENCIDA":
            return "estado-vencida";

        case "BORRADOR":
        default:
            return "estado-borrador";
    }
}


function texto_tipo_contacto($tipo)
{
    $tipos = [

        "LLAMADA"  => "Llamada",
        "WHATSAPP" => "WhatsApp",
        "CORREO"   => "Correo",
        "VISITA"   => "Visita",
        "REUNION"  => "Reunión",
        "OTRO"     => "Otro"
    ];

    return $tipos[$tipo] ?? $tipo;
}


function texto_resultado($resultado)
{
    $resultados = [

        "SIN_RESPUESTA" => "Sin respuesta",
        "INTERESADO"    => "Interesado",
        "EN_REVISION"   => "En revisión",
        "NEGOCIACION"   => "Negociación",
        "ACEPTADO"      => "Aceptado",
        "RECHAZADO"     => "Rechazado",
        "PENDIENTE"     => "Pendiente",
        "OTRO"          => "Otro"
    ];

    return $resultados[$resultado] ?? $resultado;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Detalle del cliente - Sistema Comercial
    </title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #222;
        }


        /* =====================================================
           MENÚ LATERAL
        ===================================================== */

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
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .sublogo {
            font-size: 12px;
            color: #cbd5e1;
            margin-bottom: 25px;
        }

        .menu-titulo {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            margin: 20px 8px 8px;
            font-weight: bold;
        }

        .sidebar a {
            display: block;
            text-decoration: none;
            color: #e5e7eb;
            padding: 11px 12px;
            margin-bottom: 4px;
            border-radius: 6px;
            font-size: 14px;
        }

        .sidebar a:hover {
            background: #374151;
            color: white;
        }

        .sidebar a.activo {
            background: #2563eb;
            color: white;
        }


        /* =====================================================
           CONTENIDO
        ===================================================== */

        .contenido {
            margin-left: 240px;
            padding: 25px;
            min-height: 100vh;
        }


        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .titulo-cliente {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .titulo-cliente h1 {
            margin: 0;
            font-size: 27px;
            color: #1f2937;
        }

        .subtitulo {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
        }


        /* =====================================================
           BOTONES
        ===================================================== */

        .acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-azul {
            background: #2563eb;
            color: white;
        }

        .btn-azul:hover {
            background: #1d4ed8;
        }

        .btn-verde {
            background: #16a34a;
            color: white;
        }

        .btn-verde:hover {
            background: #15803d;
        }

        .btn-gris {
            background: #6b7280;
            color: white;
        }

        .btn-gris:hover {
            background: #4b5563;
        }


        /* =====================================================
           MENSAJE
        ===================================================== */

        .mensaje {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: bold;
        }


        /* =====================================================
           ESTADO CLIENTE
        ===================================================== */

        .estado-cliente {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .activo {
            background: #dcfce7;
            color: #166534;
        }

        .inactivo {
            background: #fee2e2;
            color: #991b1b;
        }


        /* =====================================================
           TARJETAS
        ===================================================== */

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .tarjeta {
            background: white;
            border-radius: 9px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }

        .tarjeta-titulo {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .tarjeta-numero {
            font-size: 27px;
            font-weight: bold;
            color: #1f2937;
        }


        /* =====================================================
           BLOQUES
        ===================================================== */

        .bloque {
            background: white;
            border-radius: 9px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .bloque-cabecera {
            padding: 17px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .bloque-cabecera h2 {
            margin: 0;
            font-size: 17px;
            color: #1f2937;
        }

        .bloque-contenido {
            padding: 20px;
        }


        /* =====================================================
           DATOS CLIENTE
        ===================================================== */

        .datos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .dato {
            min-width: 0;
        }

        .dato-completo {
            grid-column: 1 / -1;
        }

        .dato-label {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .dato-valor {
            color: #1f2937;
            font-size: 14px;
            min-height: 20px;
            word-break: break-word;
        }


        /* =====================================================
           TABLAS
        ===================================================== */

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            text-align: left;
            background: #f9fafb;
            color: #4b5563;
            font-size: 11px;
            text-transform: uppercase;
            padding: 11px 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            color: #374151;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafafa;
        }

        .tabla-vacia {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 14px;
        }


        /* =====================================================
           ESTADOS COTIZACIÓN
        ===================================================== */

        .estado {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .estado-borrador {
            background: #f3f4f6;
            color: #374151;
        }

        .estado-enviada {
            background: #dbeafe;
            color: #1e40af;
        }

        .estado-seguimiento {
            background: #fef3c7;
            color: #92400e;
        }

        .estado-aceptada {
            background: #dcfce7;
            color: #166534;
        }

        .estado-rechazada {
            background: #fee2e2;
            color: #991b1b;
        }

        .estado-vencida {
            background: #fce7f3;
            color: #9d174d;
        }


        /* =====================================================
           SEGUIMIENTOS
        ===================================================== */

        .resultado {
            font-weight: bold;
        }

        .resultado-aceptado {
            color: #15803d;
        }

        .resultado-rechazado {
            color: #b91c1c;
        }

        .resultado-pendiente {
            color: #b45309;
        }


        /* =====================================================
           NOTAS
        ===================================================== */

        .notas {
            white-space: pre-wrap;
            line-height: 1.5;
            color: #374151;
            font-size: 14px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .datos-grid {
                grid-template-columns: repeat(2, 1fr);
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
                padding: 15px;
            }

            .encabezado {
                flex-direction: column;
            }

            .tarjetas {
                grid-template-columns: 1fr;
            }

            .datos-grid {
                grid-template-columns: 1fr;
            }

            .dato-completo {
                grid-column: auto;
            }

            .acciones {
                width: 100%;
            }

            .acciones .btn {
                flex: 1;
                text-align: center;
            }
        }

    </style>

</head>

<body>


<!-- =========================================================
     MENÚ LATERAL
========================================================= -->

<aside class="sidebar">

    <div class="logo">
        Sistema Comercial
    </div>

    <div class="sublogo">
        Gestión comercial
    </div>


    <div class="menu-titulo">
        Principal
    </div>

    <a href="index.php">
        Inicio
    </a>


    <div class="menu-titulo">
        Clientes
    </div>

    <a href="clientes.php" class="activo">
        Clientes
    </a>

    <a href="nuevo_cliente.php">
        Nuevo cliente
    </a>


    <div class="menu-titulo">
        Productos y servicios
    </div>

    <a href="productos.php">
        Productos y servicios
    </a>

    <a href="nuevo_producto.php">
        Nuevo producto
    </a>


    <div class="menu-titulo">
        Cotizaciones
    </div>

    <a href="cotizaciones.php">
        Cotizaciones
    </a>

    <a href="nueva_cotizacion.php">
        Nueva cotización
    </a>


    <div class="menu-titulo">
        Seguimiento
    </div>

    <a href="seguimientos.php">
        Seguimientos
    </a>


    <div class="menu-titulo">
        Reportes
    </div>

    <a href="reportes.php">
        Reportes
    </a>

    <a href="auditoria.php">
        Auditoría
    </a>

</aside>


<!-- =========================================================
     CONTENIDO
========================================================= -->

<main class="contenido">


    <!-- =====================================================
         ENCABEZADO
    ====================================================== -->

    <div class="encabezado">

        <div>

            <div class="titulo-cliente">

                <h1>
                    <?= esc($cliente["nombre"]) ?>
                </h1>

                <?php if ($cliente["estatus"] === "ACTIVO"): ?>

                    <span class="estado-cliente activo">
                        ACTIVO
                    </span>

                <?php else: ?>

                    <span class="estado-cliente inactivo">
                        INACTIVO
                    </span>

                <?php endif; ?>

            </div>

            <div class="subtitulo">

                <?= esc($cliente["tipo_cliente"]) ?>

                <?php if (!empty($cliente["rfc"])): ?>

                    · RFC:
                    <?= esc($cliente["rfc"]) ?>

                <?php endif; ?>

                · Cliente #<?= $id ?>

            </div>

        </div>


        <div class="acciones">

            <a
                href="clientes.php"
                class="btn btn-gris"
            >
                ← Clientes
            </a>

            <a
                href="editar_cliente.php?id=<?= $id ?>"
                class="btn btn-azul"
            >
                Editar cliente
            </a>

            <a
                href="nueva_cotizacion.php?cliente_id=<?= $id ?>"
                class="btn btn-verde"
            >
                + Nueva cotización
            </a>

        </div>

    </div>


    <?php if ($mensaje !== ""): ?>

        <div class="mensaje">

            <?= esc($mensaje) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div class="tarjetas">


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Cotizaciones
            </div>

            <div class="tarjeta-numero">
                <?= $total_cotizaciones ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Aceptadas
            </div>

            <div class="tarjeta-numero">
                <?= $total_aceptadas ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                En seguimiento
            </div>

            <div class="tarjeta-numero">
                <?= $total_en_seguimiento ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Seguimientos
            </div>

            <div class="tarjeta-numero">
                <?= $total_seguimientos ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         INFORMACIÓN GENERAL
    ====================================================== -->

    <div class="bloque">

        <div class="bloque-cabecera">

            <h2>
                Información del cliente
            </h2>

            <a
                href="editar_cliente.php?id=<?= $id ?>"
                class="btn btn-azul"
            >
                Editar información
            </a>

        </div>


        <div class="bloque-contenido">

            <div class="datos-grid">


                <div class="dato">

                    <div class="dato-label">
                        Tipo de cliente
                    </div>

                    <div class="dato-valor">
                        <?= esc($cliente["tipo_cliente"]) ?>
                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Nombre
                    </div>

                    <div class="dato-valor">
                        <?= esc($cliente["nombre"]) ?>
                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Razón social
                    </div>

                    <div class="dato-valor">

                        <?= $cliente["razon_social"]
                            ? esc($cliente["razon_social"])
                            : "-"
                        ?>

                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        RFC
                    </div>

                    <div class="dato-valor">

                        <?= $cliente["rfc"]
                            ? esc($cliente["rfc"])
                            : "-"
                        ?>

                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Contacto
                    </div>

                    <div class="dato-valor">

                        <?= $cliente["contacto"]
                            ? esc($cliente["contacto"])
                            : "-"
                        ?>

                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Teléfono
                    </div>

                    <div class="dato-valor">

                        <?= $cliente["telefono"]
                            ? esc($cliente["telefono"])
                            : "-"
                        ?>

                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Teléfono adicional
                    </div>

                    <div class="dato-valor">

                        <?= $cliente["telefono2"]
                            ? esc($cliente["telefono2"])
                            : "-"
                        ?>

                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Correo
                    </div>

                    <div class="dato-valor">

                        <?php if (!empty($cliente["correo"])): ?>

                            <a
                                href="mailto:<?= esc($cliente["correo"]) ?>"
                                style="color:#2563eb;text-decoration:none;"
                            >
                                <?= esc($cliente["correo"]) ?>
                            </a>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </div>

                </div>


                <div class="dato dato-completo">

                    <div class="dato-label">
                        Domicilio
                    </div>

                    <div class="dato-valor">

                        <?php

                        $domicilio = [];

                        if ($cliente["calle"]) {
                            $domicilio[] = $cliente["calle"];
                        }

                        if ($cliente["numero"]) {
                            $domicilio[] = "No. " . $cliente["numero"];
                        }

                        if ($cliente["colonia"]) {
                            $domicilio[] = "Col. " . $cliente["colonia"];
                        }

                        if ($cliente["ciudad"]) {
                            $domicilio[] = $cliente["ciudad"];
                        }

                        if ($cliente["estado"]) {
                            $domicilio[] = $cliente["estado"];
                        }

                        if ($cliente["codigo_postal"]) {
                            $domicilio[] = "C.P. " . $cliente["codigo_postal"];
                        }

                        echo !empty($domicilio)
                            ? esc(implode(", ", $domicilio))
                            : "-";

                        ?>

                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Fecha de registro
                    </div>

                    <div class="dato-valor">
                        <?= formato_fecha_hora($cliente["fecha_registro"]) ?>
                    </div>

                </div>


                <div class="dato">

                    <div class="dato-label">
                        Última actualización
                    </div>

                    <div class="dato-valor">
                        <?= formato_fecha_hora($cliente["fecha_actualizacion"]) ?>
                    </div>

                </div>


                <div class="dato dato-completo">

                    <div class="dato-label">
                        Notas
                    </div>

                    <div class="dato-valor notas">

                        <?= $cliente["notas"]
                            ? esc($cliente["notas"])
                            : "Sin notas registradas."
                        ?>

                    </div>

                </div>


            </div>

        </div>

    </div>


    <!-- =====================================================
         COTIZACIONES
    ====================================================== -->

    <div class="bloque">

        <div class="bloque-cabecera">

            <h2>
                Cotizaciones del cliente
            </h2>

            <a
                href="nueva_cotizacion.php?cliente_id=<?= $id ?>"
                class="btn btn-verde"
            >
                + Nueva cotización
            </a>

        </div>


        <div class="tabla-contenedor">

            <?php if ($cotizaciones->num_rows > 0): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Folio
                            </th>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Vigencia
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                Estatus
                            </th>

                            <th>
                                Próximo seguimiento
                            </th>

                            <th>
                                Acción
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php while ($cot = $cotizaciones->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= esc($cot["folio"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= formato_fecha($cot["fecha"]) ?>
                                </td>

                                <td>
                                    <?= formato_fecha($cot["vigencia"]) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= formato_moneda($cot["total"]) ?>
                                    </strong>
                                </td>

                                <td>

                                    <span
                                        class="estado <?= clase_estatus($cot["estatus"]) ?>"
                                    >
                                        <?= esc(texto_estatus($cot["estatus"])) ?>
                                    </span>

                                </td>

                                <td>

                                    <?= formato_fecha(
                                        $cot["fecha_seguimiento"]
                                    ) ?>

                                </td>

                                <td>

                                    <a
                                        href="ver_cotizacion.php?id=<?= (int) $cot["id"] ?>"
                                        class="btn btn-azul"
                                    >
                                        Ver
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="tabla-vacia">

                    Este cliente todavía no tiene cotizaciones registradas.

                    <br><br>

                    <a
                        href="nueva_cotizacion.php?cliente_id=<?= $id ?>"
                        class="btn btn-verde"
                    >
                        Crear primera cotización
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         SEGUIMIENTOS
    ====================================================== -->

    <div class="bloque">

        <div class="bloque-cabecera">

            <h2>
                Seguimiento comercial
            </h2>

            <a
                href="nuevo_seguimiento.php?cliente_id=<?= $id ?>"
                class="btn btn-azul"
            >
                + Registrar seguimiento
            </a>

        </div>


        <div class="tabla-contenedor">

            <?php if ($seguimientos->num_rows > 0): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Cotización
                            </th>

                            <th>
                                Resultado
                            </th>

                            <th>
                                Comentarios
                            </th>

                            <th>
                                Próximo seguimiento
                            </th>

                            <th>
                                Responsable
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php while ($seg = $seguimientos->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= formato_fecha_hora($seg["fecha"]) ?>
                                </td>

                                <td>
                                    <?= esc(
                                        texto_tipo_contacto(
                                            $seg["tipo_contacto"]
                                        )
                                    ) ?>
                                </td>

                                <td>

                                    <?php if (!empty($seg["folio"])): ?>

                                        <a
                                            href="ver_cotizacion.php?id=<?= (int) $seg["id"] ?>"
                                            style="color:#2563eb;text-decoration:none;"
                                        >
                                            <?= esc($seg["folio"]) ?>
                                        </a>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php

                                    $clase_resultado = "";

                                    if ($seg["resultado"] === "ACEPTADO") {
                                        $clase_resultado = "resultado-aceptado";
                                    }

                                    if ($seg["resultado"] === "RECHAZADO") {
                                        $clase_resultado = "resultado-rechazado";
                                    }

                                    if ($seg["resultado"] === "PENDIENTE") {
                                        $clase_resultado = "resultado-pendiente";
                                    }

                                    ?>

                                    <span
                                        class="resultado <?= $clase_resultado ?>"
                                    >
                                        <?= esc(
                                            texto_resultado(
                                                $seg["resultado"]
                                            )
                                        ) ?>
                                    </span>

                                </td>

                                <td style="max-width:350px;">

                                    <?= $seg["comentarios"]
                                        ? esc($seg["comentarios"])
                                        : "-"
                                    ?>

                                </td>

                                <td>

                                    <?= formato_fecha(
                                        $seg["proximo_seguimiento"]
                                    ) ?>

                                </td>

                                <td>

                                    <?= $seg["responsable"]
                                        ? esc($seg["responsable"])
                                        : "-"
                                    ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="tabla-vacia">

                    No existen seguimientos registrados para este cliente.

                    <br><br>

                    <a
                        href="nuevo_seguimiento.php?cliente_id=<?= $id ?>"
                        class="btn btn-azul"
                    >
                        Registrar primer seguimiento
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>


</main>


</body>
</html>

<?php

/* =========================================================
   CERRAR RESULTADOS
========================================================= */

$stmt_cotizaciones->close();
$stmt_seguimientos->close();

?>