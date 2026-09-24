<?php

require_once __DIR__ . '/conexion.php';

/* =========================================================
   FUNCIONES
========================================================= */

function esc($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   VARIABLES
========================================================= */

$errores = [];

$cliente_id = '';
$cotizacion_id = '';
$fecha = date('Y-m-d\TH:i');
$tipo_contacto = 'LLAMADA';
$resultado = 'PENDIENTE';
$comentarios = '';
$proximo_seguimiento = '';
$responsable = '';

/* =========================================================
   CLIENTE RECIBIDO POR GET
========================================================= */

if (isset($_GET['cliente_id']) && ctype_digit($_GET['cliente_id'])) {
    $cliente_id = (int)$_GET['cliente_id'];
}

/* =========================================================
   COTIZACIÓN RECIBIDA POR GET
========================================================= */

if (isset($_GET['cotizacion_id']) && ctype_digit($_GET['cotizacion_id'])) {
    $cotizacion_id = (int)$_GET['cotizacion_id'];
}

/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cliente_id = isset($_POST['cliente_id'])
        ? (int)$_POST['cliente_id']
        : 0;

    $cotizacion_id = !empty($_POST['cotizacion_id'])
        ? (int)$_POST['cotizacion_id']
        : null;

    $fecha = trim($_POST['fecha'] ?? '');

    $tipo_contacto = trim($_POST['tipo_contacto'] ?? '');

    $resultado = trim($_POST['resultado'] ?? '');

    $comentarios = trim($_POST['comentarios'] ?? '');

    $proximo_seguimiento = trim(
        $_POST['proximo_seguimiento'] ?? ''
    );

    $responsable = trim($_POST['responsable'] ?? '');

    /* =====================================================
       VALIDACIONES
    ===================================================== */

    if ($cliente_id <= 0) {
        $errores[] = 'Debe seleccionar un cliente.';
    }

    $tipos_validos = [
        'LLAMADA',
        'WHATSAPP',
        'CORREO',
        'VISITA',
        'REUNION',
        'OTRO'
    ];

    if (!in_array($tipo_contacto, $tipos_validos, true)) {
        $errores[] = 'El tipo de contacto seleccionado no es válido.';
    }

    $resultados_validos = [
        'SIN_RESPUESTA',
        'INTERESADO',
        'EN_REVISION',
        'NEGOCIACION',
        'ACEPTADO',
        'RECHAZADO',
        'PENDIENTE',
        'OTRO'
    ];

    if (!in_array($resultado, $resultados_validos, true)) {
        $errores[] = 'El resultado seleccionado no es válido.';
    }

    if ($fecha === '') {
        $errores[] = 'Debe indicar la fecha y hora del contacto.';
    }

    /* =====================================================
       VALIDAR CLIENTE
    ===================================================== */

    if ($cliente_id > 0) {

        $stmtCliente = $conexion->prepare("
            SELECT
                id,
                nombre,
                razon_social,
                estatus
            FROM clientes
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmtCliente) {
            $errores[] = 'No fue posible validar el cliente.';
        } else {

            $stmtCliente->bind_param(
                "i",
                $cliente_id
            );

            $stmtCliente->execute();

            $cliente = $stmtCliente->get_result()->fetch_assoc();

            $stmtCliente->close();

            if (!$cliente) {
                $errores[] = 'El cliente seleccionado no existe.';
            }
        }
    }

    /* =====================================================
       VALIDAR COTIZACIÓN
    ===================================================== */

    if ($cotizacion_id !== null && $cotizacion_id > 0) {

        $stmtCot = $conexion->prepare("
            SELECT
                id,
                cliente_id,
                folio,
                estatus
            FROM cotizaciones
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmtCot) {

            $errores[] =
                'No fue posible validar la cotización.';

        } else {

            $stmtCot->bind_param(
                "i",
                $cotizacion_id
            );

            $stmtCot->execute();

            $cotizacion = $stmtCot
                ->get_result()
                ->fetch_assoc();

            $stmtCot->close();

            if (!$cotizacion) {

                $errores[] =
                    'La cotización seleccionada no existe.';

            } elseif (
                (int)$cotizacion['cliente_id']
                !== $cliente_id
            ) {

                $errores[] =
                    'La cotización seleccionada no pertenece al cliente indicado.';

            }
        }
    }

    /* =====================================================
       GUARDAR
    ===================================================== */

    if (empty($errores)) {

        try {

            $conexion->begin_transaction();

            /*
             * Guardar seguimiento
             */

            $stmt = $conexion->prepare("
                INSERT INTO seguimientos (
                    cliente_id,
                    cotizacion_id,
                    fecha,
                    tipo_contacto,
                    resultado,
                    comentarios,
                    proximo_seguimiento,
                    responsable
                )
                VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    'No fue posible preparar el registro del seguimiento.'
                );
            }

            /*
             * MySQL DATETIME
             *
             * Convertimos:
             * 2026-09-22T15:30
             *
             * a:
             * 2026-09-22 15:30:00
             */

            $fecha_mysql = str_replace(
                'T',
                ' ',
                $fecha
            );

            if (strlen($fecha_mysql) === 16) {
                $fecha_mysql .= ':00';
            }

            $stmt->bind_param(
                "iissssss",
                $cliente_id,
                $cotizacion_id,
                $fecha_mysql,
                $tipo_contacto,
                $resultado,
                $comentarios,
                $proximo_seguimiento,
                $responsable
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    'No fue posible guardar el seguimiento.'
                );
            }

            $seguimiento_id = $conexion->insert_id;

            $stmt->close();

            /* =================================================
               ACTUALIZAR COTIZACIÓN
            ================================================= */

            if ($cotizacion_id !== null && $cotizacion_id > 0) {

                /*
                 * Si el resultado es aceptado:
                 * cotización -> ACEPTADA
                 */

                if ($resultado === 'ACEPTADO') {

                    $stmtCotUpdate = $conexion->prepare("
                        UPDATE cotizaciones
                        SET
                            estatus = 'ACEPTADA',
                            fecha_seguimiento = NULL
                        WHERE id = ?
                    ");

                    if (!$stmtCotUpdate) {
                        throw new Exception(
                            'No fue posible actualizar la cotización.'
                        );
                    }

                    $stmtCotUpdate->bind_param(
                        "i",
                        $cotizacion_id
                    );

                    if (!$stmtCotUpdate->execute()) {
                        throw new Exception(
                            'No fue posible actualizar el estado de la cotización.'
                        );
                    }

                    $stmtCotUpdate->close();

                /*
                 * Si se rechaza:
                 * cotización -> RECHAZADA
                 */

                } elseif ($resultado === 'RECHAZADO') {

                    $stmtCotUpdate = $conexion->prepare("
                        UPDATE cotizaciones
                        SET
                            estatus = 'RECHAZADA',
                            fecha_seguimiento = NULL
                        WHERE id = ?
                    ");

                    if (!$stmtCotUpdate) {
                        throw new Exception(
                            'No fue posible actualizar la cotización.'
                        );
                    }

                    $stmtCotUpdate->bind_param(
                        "i",
                        $cotizacion_id
                    );

                    if (!$stmtCotUpdate->execute()) {
                        throw new Exception(
                            'No fue posible actualizar el estado de la cotización.'
                        );
                    }

                    $stmtCotUpdate->close();

                /*
                 * Si sigue en seguimiento:
                 * cotización -> EN_SEGUIMIENTO
                 */

                } elseif (
                    in_array(
                        $resultado,
                        [
                            'INTERESADO',
                            'EN_REVISION',
                            'NEGOCIACION',
                            'PENDIENTE',
                            'SIN_RESPUESTA',
                            'OTRO'
                        ],
                        true
                    )
                ) {

                    $stmtCotUpdate = $conexion->prepare("
                        UPDATE cotizaciones
                        SET
                            estatus = 'EN_SEGUIMIENTO',
                            fecha_seguimiento = NULLIF(?, '')
                        WHERE id = ?
                    ");

                    if (!$stmtCotUpdate) {
                        throw new Exception(
                            'No fue posible actualizar la cotización.'
                        );
                    }

                    $stmtCotUpdate->bind_param(
                        "si",
                        $proximo_seguimiento,
                        $cotizacion_id
                    );

                    if (!$stmtCotUpdate->execute()) {
                        throw new Exception(
                            'No fue posible actualizar el seguimiento de la cotización.'
                        );
                    }

                    $stmtCotUpdate->close();
                }
            }

            /* =================================================
               AUDITORÍA
            ================================================= */

            $usuario_auditoria =
                $_SESSION['usuario']
                ?? $_SESSION['nombre_usuario']
                ?? $_SESSION['usuario_nombre']
                ?? 'Sistema Comercial';

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $descripcion_auditoria =
                'Se registró seguimiento comercial #' .
                $seguimiento_id .
                ' para el cliente ID ' .
                $cliente_id;

            $stmtAudit = $conexion->prepare("
                INSERT INTO auditoria (
                    usuario,
                    accion,
                    modulo,
                    registro_id,
                    descripcion,
                    ip
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if ($stmtAudit) {

                $accion = 'CREAR';
                $modulo = 'SEGUIMIENTOS';

                $stmtAudit->bind_param(
                    "sssiss",
                    $usuario_auditoria,
                    $accion,
                    $modulo,
                    $seguimiento_id,
                    $descripcion_auditoria,
                    $ip
                );

                $stmtAudit->execute();

                $stmtAudit->close();
            }

            /* =================================================
               CONFIRMAR
            ================================================= */

            $conexion->commit();

            header(
                'Location: seguimientos.php?guardado=1'
            );

            exit;

        } catch (Throwable $e) {

            $conexion->rollback();

            $errores[] = $e->getMessage();
        }
    }
}

/* =========================================================
   CLIENTES
========================================================= */

$clientes = [];

$resClientes = $conexion->query("
    SELECT
        id,
        nombre,
        razon_social
    FROM clientes
    WHERE estatus = 'ACTIVO'
    ORDER BY
        COALESCE(NULLIF(razon_social, ''), nombre) ASC
");

if ($resClientes) {

    while ($fila = $resClientes->fetch_assoc()) {
        $clientes[] = $fila;
    }
}

/* =========================================================
   COTIZACIONES
========================================================= */

$cotizaciones = [];

if ($cliente_id > 0) {

    $stmtCotizaciones = $conexion->prepare("
        SELECT
            id,
            folio,
            fecha,
            total,
            estatus
        FROM cotizaciones
        WHERE cliente_id = ?
        ORDER BY fecha DESC, id DESC
    ");

    if ($stmtCotizaciones) {

        $stmtCotizaciones->bind_param(
            "i",
            $cliente_id
        );

        $stmtCotizaciones->execute();

        $resCotizaciones =
            $stmtCotizaciones->get_result();

        while ($fila = $resCotizaciones->fetch_assoc()) {
            $cotizaciones[] = $fila;
        }

        $stmtCotizaciones->close();
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

    <title>Nuevo seguimiento | Sistema Comercial</title>

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
           FORMULARIO
        ===================================================== */

        .form-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            padding: 25px;
        }

        .section-title {
            margin: 0 0 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 17px;
            color: #1f2937;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: bold;
            color: #374151;
        }

        .required {
            color: #dc2626;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 11px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            background: white;
        }

        .field textarea {
            min-height: 120px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,.10);
        }

        .help {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            color: #6b7280;
        }

        /* =====================================================
           ALERTAS
        ===================================================== */

        .alert {
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-danger ul {
            margin: 5px 0 0 18px;
            padding: 0;
        }

        /* =====================================================
           BOTONES
        ===================================================== */

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-block;
            padding: 11px 17px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
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

        /* =====================================================
           INFO CLIENTE
        ===================================================== */

        .cliente-info {
            display: none;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 7px;
            padding: 12px;
            margin-top: 8px;
            font-size: 12px;
            color: #1e40af;
        }

        .cliente-info.visible {
            display: block;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field-full {
                grid-column: auto;
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

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                text-align: center;
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
        class="menu-link active"
    >
        Nuevo seguimiento
    </a>


    <div class="menu-title">
        Reportes
    </div>

    <a
        href="reportes.php"
        class="menu-link"
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
            Nuevo seguimiento
        </h1>

        <p>
            Registra una llamada, WhatsApp, correo, visita o cualquier otro contacto comercial.
        </p>

    </div>


    <?php if (!empty($errores)): ?>

        <div class="alert alert-danger">

            <strong>
                No fue posible guardar el seguimiento:
            </strong>

            <ul>

                <?php foreach ($errores as $error): ?>

                    <li>
                        <?= esc($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <div class="form-box">

        <h2 class="section-title">
            Información del seguimiento
        </h2>


        <form
            method="POST"
            action=""
        >

            <div class="form-grid">


                <!-- =================================================
                     CLIENTE
                ================================================= -->

                <div class="field">

                    <label>
                        Cliente
                        <span class="required">*</span>
                    </label>

                    <select
                        name="cliente_id"
                        id="cliente_id"
                        required
                    >

                        <option value="">
                            Seleccione un cliente
                        </option>

                        <?php foreach ($clientes as $cliente): ?>

                            <?php

                            $nombreCliente =
                                !empty($cliente['razon_social'])
                                ? $cliente['razon_social']
                                : $cliente['nombre'];

                            ?>

                            <option
                                value="<?= (int)$cliente['id'] ?>"
                                <?= (int)$cliente_id === (int)$cliente['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= esc($nombreCliente) ?>

                                <?php
                                if (
                                    !empty($cliente['razon_social']) &&
                                    $cliente['razon_social']
                                    !== $cliente['nombre']
                                ) {
                                    echo ' - ' .
                                        esc($cliente['nombre']);
                                }
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <span class="help">
                        Selecciona el cliente con el que se realizó el contacto.
                    </span>

                </div>


                <!-- =================================================
                     COTIZACIÓN
                ================================================= -->

                <div class="field">

                    <label>
                        Cotización relacionada
                    </label>

                    <select
                        name="cotizacion_id"
                        id="cotizacion_id"
                    >

                        <option value="">
                            Sin cotización
                        </option>

                        <?php foreach ($cotizaciones as $cotizacion): ?>

                            <option
                                value="<?= (int)$cotizacion['id'] ?>"
                                <?= (int)$cotizacion_id === (int)$cotizacion['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= esc($cotizacion['folio']) ?>

                                -
                                $
                                <?= number_format(
                                    (float)$cotizacion['total'],
                                    2
                                ) ?>

                                -
                                <?= esc($cotizacion['estatus']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <span class="help">
                        Primero selecciona un cliente para mostrar sus cotizaciones.
                    </span>

                </div>


                <!-- =================================================
                     FECHA
                ================================================= -->

                <div class="field">

                    <label>
                        Fecha y hora del contacto
                        <span class="required">*</span>
                    </label>

                    <input
                        type="datetime-local"
                        name="fecha"
                        value="<?= esc($fecha) ?>"
                        required
                    >

                </div>


                <!-- =================================================
                     TIPO CONTACTO
                ================================================= -->

                <div class="field">

                    <label>
                        Tipo de contacto
                        <span class="required">*</span>
                    </label>

                    <select
                        name="tipo_contacto"
                        required
                    >

                        <option
                            value="LLAMADA"
                            <?= $tipo_contacto === 'LLAMADA'
                                ? 'selected'
                                : '' ?>
                        >
                            Llamada
                        </option>

                        <option
                            value="WHATSAPP"
                            <?= $tipo_contacto === 'WHATSAPP'
                                ? 'selected'
                                : '' ?>
                        >
                            WhatsApp
                        </option>

                        <option
                            value="CORREO"
                            <?= $tipo_contacto === 'CORREO'
                                ? 'selected'
                                : '' ?>
                        >
                            Correo
                        </option>

                        <option
                            value="VISITA"
                            <?= $tipo_contacto === 'VISITA'
                                ? 'selected'
                                : '' ?>
                        >
                            Visita
                        </option>

                        <option
                            value="REUNION"
                            <?= $tipo_contacto === 'REUNION'
                                ? 'selected'
                                : '' ?>
                        >
                            Reunión
                        </option>

                        <option
                            value="OTRO"
                            <?= $tipo_contacto === 'OTRO'
                                ? 'selected'
                                : '' ?>
                        >
                            Otro
                        </option>

                    </select>

                </div>


                <!-- =================================================
                     RESULTADO
                ================================================= -->

                <div class="field">

                    <label>
                        Resultado
                        <span class="required">*</span>
                    </label>

                    <select
                        name="resultado"
                        id="resultado"
                        required
                    >

                        <option
                            value="PENDIENTE"
                            <?= $resultado === 'PENDIENTE'
                                ? 'selected'
                                : '' ?>
                        >
                            Pendiente
                        </option>

                        <option
                            value="INTERESADO"
                            <?= $resultado === 'INTERESADO'
                                ? 'selected'
                                : '' ?>
                        >
                            Interesado
                        </option>

                        <option
                            value="EN_REVISION"
                            <?= $resultado === 'EN_REVISION'
                                ? 'selected'
                                : '' ?>
                        >
                            En revisión
                        </option>

                        <option
                            value="NEGOCIACION"
                            <?= $resultado === 'NEGOCIACION'
                                ? 'selected'
                                : '' ?>
                        >
                            Negociación
                        </option>

                        <option
                            value="ACEPTADO"
                            <?= $resultado === 'ACEPTADO'
                                ? 'selected'
                                : '' ?>
                        >
                            Aceptado
                        </option>

                        <option
                            value="RECHAZADO"
                            <?= $resultado === 'RECHAZADO'
                                ? 'selected'
                                : '' ?>
                        >
                            Rechazado
                        </option>

                        <option
                            value="SIN_RESPUESTA"
                            <?= $resultado === 'SIN_RESPUESTA'
                                ? 'selected'
                                : '' ?>
                        >
                            Sin respuesta
                        </option>

                        <option
                            value="OTRO"
                            <?= $resultado === 'OTRO'
                                ? 'selected'
                                : '' ?>
                        >
                            Otro
                        </option>

                    </select>

                </div>


                <!-- =================================================
                     RESPONSABLE
                ================================================= -->

                <div class="field">

                    <label>
                        Responsable
                    </label>

                    <input
                        type="text"
                        name="responsable"
                        value="<?= esc($responsable) ?>"
                        maxlength="150"
                        placeholder="Nombre de la persona responsable"
                    >

                </div>


                <!-- =================================================
                     PROXIMO SEGUIMIENTO
                ================================================= -->

                <div class="field">

                    <label>
                        Próximo seguimiento
                    </label>

                    <input
                        type="date"
                        name="proximo_seguimiento"
                        value="<?= esc($proximo_seguimiento) ?>"
                    >

                    <span class="help">
                        Fecha en la que deberá realizarse el siguiente contacto.
                    </span>

                </div>


                <!-- =================================================
                     COMENTARIOS
                ================================================= -->

                <div class="field field-full">

                    <label>
                        Comentarios
                    </label>

                    <textarea
                        name="comentarios"
                        maxlength="5000"
                        placeholder="Describe qué se habló con el cliente, acuerdos, necesidades, pendientes, precios, objeciones, etc."
                    ><?= esc($comentarios) ?></textarea>

                </div>


            </div>


            <!-- =================================================
                 BOTONES
            ================================================= -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Guardar seguimiento
                </button>

                <a
                    href="seguimientos.php"
                    class="btn btn-gray"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

const clienteSelect =
    document.getElementById('cliente_id');

const cotizacionSelect =
    document.getElementById('cotizacion_id');


clienteSelect.addEventListener('change', function () {

    const clienteId = this.value;

    cotizacionSelect.innerHTML =
        '<option value="">Cargando cotizaciones...</option>';

    if (!clienteId) {

        cotizacionSelect.innerHTML =
            '<option value="">Sin cotización</option>';

        return;
    }

    /*
     * Recarga la página conservando el cliente
     * para obtener sus cotizaciones.
     */

    const url =
        'nuevo_seguimiento.php?cliente_id='
        + encodeURIComponent(clienteId);

    window.location.href = url;

});

</script>

</body>

</html>