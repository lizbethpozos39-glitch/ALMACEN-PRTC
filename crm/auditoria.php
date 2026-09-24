<?php

require_once __DIR__ . '/conexion.php';

$mensaje = "";
$tipo_mensaje = "success";

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$usuario = trim($_GET['usuario'] ?? '');
$accion = trim($_GET['accion'] ?? '');
$modulo = trim($_GET['modulo'] ?? '');
$fecha_inicio = trim($_GET['fecha_inicio'] ?? '');
$fecha_fin = trim($_GET['fecha_fin'] ?? '');

/*
|--------------------------------------------------------------------------
| PAGINACIÓN
|--------------------------------------------------------------------------
*/

$por_pagina = 50;

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

if ($pagina < 1) {
    $pagina = 1;
}

$offset = ($pagina - 1) * $por_pagina;

/*
|--------------------------------------------------------------------------
| CONSTRUIR FILTROS
|--------------------------------------------------------------------------
*/

$where = [];
$parametros = [];
$tipos = '';

if ($usuario !== '') {
    $where[] = "a.usuario LIKE ?";
    $parametros[] = '%' . $usuario . '%';
    $tipos .= 's';
}

if ($accion !== '') {
    $where[] = "a.accion LIKE ?";
    $parametros[] = '%' . $accion . '%';
    $tipos .= 's';
}

if ($modulo !== '') {
    $where[] = "a.modulo LIKE ?";
    $parametros[] = '%' . $modulo . '%';
    $tipos .= 's';
}

if ($fecha_inicio !== '') {
    $where[] = "DATE(a.fecha) >= ?";
    $parametros[] = $fecha_inicio;
    $tipos .= 's';
}

if ($fecha_fin !== '') {
    $where[] = "DATE(a.fecha) <= ?";
    $parametros[] = $fecha_fin;
    $tipos .= 's';
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| TOTAL DE REGISTROS
|--------------------------------------------------------------------------
*/

$total_registros = 0;

$sql_total = "
    SELECT COUNT(*) AS total
    FROM auditoria a
    $where_sql
";

$stmt_total = $conexion->prepare($sql_total);

if ($stmt_total) {

    if (!empty($parametros)) {
        $stmt_total->bind_param($tipos, ...$parametros);
    }

    $stmt_total->execute();

    $resultado_total = $stmt_total->get_result();

    if ($fila_total = $resultado_total->fetch_assoc()) {
        $total_registros = (int)$fila_total['total'];
    }

    $stmt_total->close();
}

$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

if ($pagina > $total_paginas) {
    $pagina = $total_paginas;
    $offset = ($pagina - 1) * $por_pagina;
}

/*
|--------------------------------------------------------------------------
| CONSULTAR AUDITORÍA
|--------------------------------------------------------------------------
*/

$registros = [];

$sql = "
    SELECT
        a.id,
        a.usuario,
        a.accion,
        a.modulo,
        a.registro_id,
        a.descripcion,
        a.ip,
        a.fecha
    FROM auditoria a
    $where_sql
    ORDER BY a.fecha DESC, a.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conexion->prepare($sql);

if ($stmt) {

    $parametros_datos = $parametros;
    $tipos_datos = $tipos . 'ii';

    $parametros_datos[] = $por_pagina;
    $parametros_datos[] = $offset;

    $stmt->bind_param($tipos_datos, ...$parametros_datos);

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $registros[] = $fila;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| ACCIONES / MÓDULOS PARA RESUMEN
|--------------------------------------------------------------------------
*/

$total_acciones = 0;
$total_usuarios = 0;
$total_modulos = 0;

/* Total acciones */
$res = $conexion->query("
    SELECT COUNT(*) AS total
    FROM auditoria
");

if ($res && $fila = $res->fetch_assoc()) {
    $total_acciones = (int)$fila['total'];
}

/* Usuarios diferentes */
$res = $conexion->query("
    SELECT COUNT(DISTINCT usuario) AS total
    FROM auditoria
    WHERE usuario IS NOT NULL
      AND usuario <> ''
");

if ($res && $fila = $res->fetch_assoc()) {
    $total_usuarios = (int)$fila['total'];
}

/* Módulos diferentes */
$res = $conexion->query("
    SELECT COUNT(DISTINCT modulo) AS total
    FROM auditoria
    WHERE modulo IS NOT NULL
      AND modulo <> ''
");

if ($res && $fila = $res->fetch_assoc()) {
    $total_modulos = (int)$fila['total'];
}

/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA ESCAPAR HTML
|--------------------------------------------------------------------------
*/

function e($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA COLOR DE ACCIÓN
|--------------------------------------------------------------------------
*/

function claseAccion($accion)
{
    $accion = strtoupper((string)$accion);

    if (
        strpos($accion, 'ELIMIN') !== false ||
        strpos($accion, 'BORRAR') !== false ||
        strpos($accion, 'RECHAZ') !== false
    ) {
        return 'accion-roja';
    }

    if (
        strpos($accion, 'CREAR') !== false ||
        strpos($accion, 'NUEVO') !== false ||
        strpos($accion, 'INSERT') !== false ||
        strpos($accion, 'REGISTR') !== false ||
        strpos($accion, 'AUTORIZ') !== false
    ) {
        return 'accion-verde';
    }

    if (
        strpos($accion, 'EDIT') !== false ||
        strpos($accion, 'ACTUALIZ') !== false ||
        strpos($accion, 'MODIFIC') !== false
    ) {
        return 'accion-azul';
    }

    if (
        strpos($accion, 'LOGIN') !== false ||
        strpos($accion, 'ACCESO') !== false ||
        strpos($accion, 'CONSULT') !== false ||
        strpos($accion, 'VER') !== false
    ) {
        return 'accion-gris';
    }

    return 'accion-default';
}

/*
|--------------------------------------------------------------------------
| URL BASE PARA PAGINACIÓN
|--------------------------------------------------------------------------
*/

$query_paginacion = $_GET;

unset($query_paginacion['pagina']);

$query_string = http_build_query($query_paginacion);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Auditoría | Sistema Comercial</title>

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
            overflow-y: auto;
            z-index: 1000;
        }

        .logo {
            padding: 22px 20px;
            font-size: 20px;
            font-weight: bold;
            border-bottom: 1px solid #374151;
        }

        .logo span {
            display: block;
            font-size: 12px;
            color: #9ca3af;
            margin-top: 5px;
            font-weight: normal;
        }

        .menu-titulo {
            padding: 18px 20px 8px;
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            font-weight: bold;
        }

        .menu a {
            display: block;
            padding: 12px 20px;
            color: #d1d5db;
            text-decoration: none;
            font-size: 14px;
            border-left: 3px solid transparent;
        }

        .menu a:hover {
            background: #374151;
            color: white;
            border-left-color: #3b82f6;
        }

        .menu a.activo {
            background: #374151;
            color: white;
            border-left-color: #3b82f6;
        }

        /* =====================================================
           CONTENIDO
        ===================================================== */

        .contenido {
            margin-left: 240px;
            min-height: 100vh;
            padding: 30px;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
        }

        .encabezado h1 {
            margin: 0;
            font-size: 28px;
        }

        .encabezado p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* =====================================================
           TARJETAS
        ===================================================== */

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .tarjeta {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            border: 1px solid #e5e7eb;
        }

        .tarjeta-titulo {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .tarjeta-numero {
            font-size: 28px;
            font-weight: bold;
        }

        /* =====================================================
           FILTROS
        ===================================================== */

        .panel {
            background: white;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 22px;
        }

        .panel-titulo {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .filtros {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .campo label {
            font-size: 12px;
            font-weight: bold;
            color: #4b5563;
        }

        .campo input,
        .campo select {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            background: white;
        }

        .campo input:focus,
        .campo select:focus {
            outline: none;
            border-color: #2563eb;
        }

        /* =====================================================
           BOTONES
        ===================================================== */

        .boton {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            font-family: Arial, Helvetica, sans-serif;
        }

        .boton-azul {
            background: #2563eb;
            color: white;
        }

        .boton-azul:hover {
            background: #1d4ed8;
        }

        .boton-gris {
            background: #6b7280;
            color: white;
        }

        .boton-gris:hover {
            background: #4b5563;
        }

        /* =====================================================
           TABLA
        ===================================================== */

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            text-align: left;
            padding: 12px 10px;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
            vertical-align: top;
        }

        tr:hover td {
            background: #f9fafb;
        }

        .id {
            color: #6b7280;
            font-weight: bold;
        }

        .usuario {
            font-weight: bold;
        }

        .modulo {
            color: #374151;
        }

        .descripcion {
            max-width: 380px;
            line-height: 1.4;
        }

        .ip {
            color: #6b7280;
            font-family: Consolas, monospace;
            font-size: 12px;
        }

        .fecha {
            white-space: nowrap;
            color: #4b5563;
        }

        /* =====================================================
           ETIQUETAS DE ACCIÓN
        ===================================================== */

        .accion {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .accion-roja {
            background: #fee2e2;
            color: #991b1b;
        }

        .accion-verde {
            background: #dcfce7;
            color: #166534;
        }

        .accion-azul {
            background: #dbeafe;
            color: #1e40af;
        }

        .accion-gris {
            background: #e5e7eb;
            color: #374151;
        }

        .accion-default {
            background: #f3f4f6;
            color: #374151;
        }

        /* =====================================================
           PAGINACIÓN
        ===================================================== */

        .paginacion {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 22px;
            flex-wrap: wrap;
        }

        .pagina {
            display: inline-block;
            min-width: 36px;
            text-align: center;
            padding: 9px 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
        }

        .pagina:hover {
            background: #f3f4f6;
        }

        .pagina.activa {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
            font-weight: bold;
        }

        .pagina-deshabilitada {
            color: #9ca3af;
            background: #f3f4f6;
            cursor: not-allowed;
        }

        .sin-registros {
            text-align: center;
            padding: 45px 20px;
            color: #6b7280;
        }

        .resultado-info {
            margin-top: 15px;
            color: #6b7280;
            font-size: 13px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .filtros {
                grid-template-columns: repeat(3, 1fr);
            }

            .tarjetas {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 850px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .contenido {
                margin-left: 0;
                padding: 20px;
            }

            .tarjetas {
                grid-template-columns: 1fr;
            }

            .filtros {
                grid-template-columns: 1fr;
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
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
        <span>Control y seguimiento comercial</span>
    </div>

    <div class="menu">

        <div class="menu-titulo">
            Principal
        </div>

        <a href="index.php">
            Inicio
        </a>

        <div class="menu-titulo">
            Clientes
        </div>

        <a href="clientes.php">
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
            Nuevo producto / servicio
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

        <a href="nuevo_seguimiento.php">
            Nuevo seguimiento
        </a>

        <div class="menu-titulo">
            Información
        </div>

        <a href="reportes.php">
            Reportes
        </a>

        <a href="auditoria.php" class="activo">
            Auditoría
        </a>

    </div>

</aside>


<!-- =========================================================
     CONTENIDO
========================================================= -->

<main class="contenido">

    <div class="encabezado">

        <div>

            <h1>Auditoría del sistema</h1>

            <p>
                Consulta de actividades y movimientos realizados en el sistema comercial.
            </p>

        </div>

        <a href="index.php" class="boton boton-gris">
            Regresar al inicio
        </a>

    </div>


    <!-- =====================================================
         TARJETAS
    ====================================================== -->

    <div class="tarjetas">

        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Total de acciones
            </div>

            <div class="tarjeta-numero">
                <?= number_format($total_acciones) ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Usuarios registrados
            </div>

            <div class="tarjeta-numero">
                <?= number_format($total_usuarios) ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Módulos utilizados
            </div>

            <div class="tarjeta-numero">
                <?= number_format($total_modulos) ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         FILTROS
    ====================================================== -->

    <div class="panel">

        <div class="panel-titulo">
            Filtros de auditoría
        </div>

        <form method="GET">

            <div class="filtros">

                <div class="campo">

                    <label>
                        Usuario
                    </label>

                    <input
                        type="text"
                        name="usuario"
                        value="<?= e($usuario) ?>"
                        placeholder="Ej. Lizbeth"
                    >

                </div>


                <div class="campo">

                    <label>
                        Acción
                    </label>

                    <input
                        type="text"
                        name="accion"
                        value="<?= e($accion) ?>"
                        placeholder="Ej. CREAR"
                    >

                </div>


                <div class="campo">

                    <label>
                        Módulo
                    </label>

                    <input
                        type="text"
                        name="modulo"
                        value="<?= e($modulo) ?>"
                        placeholder="Ej. Cotizaciones"
                    >

                </div>


                <div class="campo">

                    <label>
                        Fecha inicial
                    </label>

                    <input
                        type="date"
                        name="fecha_inicio"
                        value="<?= e($fecha_inicio) ?>"
                    >

                </div>


                <div class="campo">

                    <label>
                        Fecha final
                    </label>

                    <input
                        type="date"
                        name="fecha_fin"
                        value="<?= e($fecha_fin) ?>"
                    >

                </div>


                <div>

                    <button
                        type="submit"
                        class="boton boton-azul"
                    >
                        Filtrar
                    </button>

                </div>

            </div>

        </form>

        <?php if (
            $usuario !== '' ||
            $accion !== '' ||
            $modulo !== '' ||
            $fecha_inicio !== '' ||
            $fecha_fin !== ''
        ): ?>

            <div style="margin-top:12px;">

                <a
                    href="auditoria.php"
                    class="boton boton-gris"
                >
                    Limpiar filtros
                </a>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         TABLA
    ====================================================== -->

    <div class="panel">

        <div class="panel-titulo">
            Registro de actividades
        </div>

        <?php if (!empty($registros)): ?>

            <div class="tabla-contenedor">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Fecha</th>

                            <th>Usuario</th>

                            <th>Acción</th>

                            <th>Módulo</th>

                            <th>Registro</th>

                            <th>Descripción</th>

                            <th>IP</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($registros as $registro): ?>

                        <tr>

                            <td class="id">
                                #<?= e($registro['id']) ?>
                            </td>

                            <td class="fecha">
                                <?= e($registro['fecha']) ?>
                            </td>

                            <td class="usuario">
                                <?= e($registro['usuario'] ?: 'Sistema') ?>
                            </td>

                            <td>

                                <span class="accion <?= e(claseAccion($registro['accion'])) ?>">
                                    <?= e($registro['accion']) ?>
                                </span>

                            </td>

                            <td class="modulo">
                                <?= e($registro['modulo'] ?: '-') ?>
                            </td>

                            <td>

                                <?php if ($registro['registro_id'] !== null): ?>

                                    #<?= e($registro['registro_id']) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>

                            <td class="descripcion">

                                <?= nl2br(e($registro['descripcion'] ?: '-')) ?>

                            </td>

                            <td class="ip">

                                <?= e($registro['ip'] ?: '-') ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <div class="resultado-info">

                Mostrando
                <strong>
                    <?= number_format(count($registros)) ?>
                </strong>

                de
                <strong>
                    <?= number_format($total_registros) ?>
                </strong>

                registros.

                Página
                <strong>
                    <?= $pagina ?>
                </strong>

                de
                <strong>
                    <?= $total_paginas ?>
                </strong>.

            </div>


            <!-- =================================================
                 PAGINACIÓN
            ================================================== -->

            <?php if ($total_paginas > 1): ?>

                <div class="paginacion">

                    <?php

                    $url_anterior = 'auditoria.php?' .
                        ($query_string ? $query_string . '&' : '') .
                        'pagina=' . max(1, $pagina - 1);

                    ?>

                    <?php if ($pagina > 1): ?>

                        <a
                            href="<?= e($url_anterior) ?>"
                            class="pagina"
                        >
                            ←
                        </a>

                    <?php else: ?>

                        <span class="pagina pagina-deshabilitada">
                            ←
                        </span>

                    <?php endif; ?>


                    <?php

                    $inicio_paginas = max(1, $pagina - 2);
                    $fin_paginas = min($total_paginas, $pagina + 2);

                    for ($i = $inicio_paginas; $i <= $fin_paginas; $i++):

                        $url_pagina = 'auditoria.php?' .
                            ($query_string ? $query_string . '&' : '') .
                            'pagina=' . $i;

                    ?>

                        <a
                            href="<?= e($url_pagina) ?>"
                            class="pagina <?= $i == $pagina ? 'activa' : '' ?>"
                        >
                            <?= $i ?>
                        </a>

                    <?php endfor; ?>


                    <?php

                    $url_siguiente = 'auditoria.php?' .
                        ($query_string ? $query_string . '&' : '') .
                        'pagina=' . min($total_paginas, $pagina + 1);

                    ?>

                    <?php if ($pagina < $total_paginas): ?>

                        <a
                            href="<?= e($url_siguiente) ?>"
                            class="pagina"
                        >
                            →
                        </a>

                    <?php else: ?>

                        <span class="pagina pagina-deshabilitada">
                            →
                        </span>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


        <?php else: ?>

            <div class="sin-registros">

                <strong>
                    No se encontraron registros.
                </strong>

                <br><br>

                No existen movimientos de auditoría que coincidan con los filtros seleccionados.

            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>