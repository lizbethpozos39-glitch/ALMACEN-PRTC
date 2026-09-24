<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();

$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());

/*
|--------------------------------------------------------------------------
| FUNCIONES AUXILIARES
|--------------------------------------------------------------------------
*/

function obtener_un_valor($conexion, $sql)
{
    $resultado = $conexion->query($sql);

    if (!$resultado) {
        return 0;
    }

    $fila = $resultado->fetch_row();

    return $fila[0] ?? 0;
}

function formatear_fecha($fecha)
{
    if (empty($fecha)) {
        return 'Sin fecha';
    }

    return date('d/m/Y', strtotime($fecha));
}

function formatear_fecha_hora($fecha)
{
    if (empty($fecha)) {
        return 'Sin fecha';
    }

    return date('d/m/Y H:i', strtotime($fecha));
}

/*
|--------------------------------------------------------------------------
| INDICADORES
|--------------------------------------------------------------------------
*/

$total_materiales = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) FROM marketing_materiales"
);

$materiales_pendientes = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) 
     FROM marketing_materiales
     WHERE estado = 'PENDIENTE'"
);

$materiales_autorizados = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) 
     FROM marketing_materiales
     WHERE estado = 'AUTORIZADO'"
);

$materiales_rechazados = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) 
     FROM marketing_materiales
     WHERE estado = 'RECHAZADO'"
);

$campanas_activas = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) 
     FROM marketing_campanas
     WHERE estado = 'ACTIVA'"
);

$campanas_pendientes = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) 
     FROM marketing_campanas
     WHERE estado IN ('BORRADOR','PENDIENTE')"
);

$campanas_finalizadas = obtener_un_valor(
    $conexion,
    "SELECT COUNT(*) 
     FROM marketing_campanas
     WHERE estado = 'FINALIZADA'"
);

/*
|--------------------------------------------------------------------------
| MATERIALES PENDIENTES
|--------------------------------------------------------------------------
*/

$materiales_pendientes_lista = [];

$sqlPendientes = "
    SELECT
        m.id,
        m.nombre,
        m.estado,
        m.fecha_subida,
        c.nombre AS categoria,
        ca.nombre AS campana,
        e.nombre AS empleado_subio
    FROM marketing_materiales m

    LEFT JOIN marketing_categorias c
        ON c.id = m.categoria_id

    LEFT JOIN marketing_campanas ca
        ON ca.id = m.campana_id

    LEFT JOIN usuarios u
        ON u.id = m.usuario_subio

    LEFT JOIN empleados e
        ON e.id = u.empleado_id

    WHERE m.estado = 'PENDIENTE'

    ORDER BY m.fecha_subida ASC

    LIMIT 8
";

$resultadoPendientes = $conexion->query($sqlPendientes);

if ($resultadoPendientes) {

    while ($fila = $resultadoPendientes->fetch_assoc()) {
        $materiales_pendientes_lista[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| MATERIALES RECIENTES
|--------------------------------------------------------------------------
*/

$materiales_recientes = [];

$sqlRecientes = "
    SELECT
        m.id,
        m.nombre,
        m.estado,
        m.fecha_subida,
        c.nombre AS categoria,
        ca.nombre AS campana
    FROM marketing_materiales m

    LEFT JOIN marketing_categorias c
        ON c.id = m.categoria_id

    LEFT JOIN marketing_campanas ca
        ON ca.id = m.campana_id

    ORDER BY m.fecha_subida DESC

    LIMIT 8
";

$resultadoRecientes = $conexion->query($sqlRecientes);

if ($resultadoRecientes) {

    while ($fila = $resultadoRecientes->fetch_assoc()) {
        $materiales_recientes[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| CAMPAÑAS ACTIVAS
|--------------------------------------------------------------------------
*/

$campanas_activas_lista = [];

$sqlCampanasActivas = "
    SELECT
        c.id,
        c.nombre,
        c.descripcion,
        c.fecha_inicio,
        c.fecha_fin,
        c.estado,
        COUNT(m.id) AS total_materiales
    FROM marketing_campanas c

    LEFT JOIN marketing_materiales m
        ON m.campana_id = c.id

    WHERE c.estado = 'ACTIVA'

    GROUP BY
        c.id,
        c.nombre,
        c.descripcion,
        c.fecha_inicio,
        c.fecha_fin,
        c.estado

    ORDER BY c.fecha_fin ASC

    LIMIT 8
";

$resultadoCampanasActivas = $conexion->query($sqlCampanasActivas);

if ($resultadoCampanasActivas) {

    while ($fila = $resultadoCampanasActivas->fetch_assoc()) {
        $campanas_activas_lista[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| PRÓXIMAS CAMPAÑAS
|--------------------------------------------------------------------------
*/

$campanas_proximas = [];

$sqlProximas = "
    SELECT
        id,
        nombre,
        fecha_inicio,
        fecha_fin,
        estado
    FROM marketing_campanas

    WHERE fecha_inicio IS NOT NULL
      AND fecha_inicio >= CURDATE()
      AND estado NOT IN ('FINALIZADA','CANCELADA')

    ORDER BY fecha_inicio ASC

    LIMIT 6
";

$resultadoProximas = $conexion->query($sqlProximas);

if ($resultadoProximas) {

    while ($fila = $resultadoProximas->fetch_assoc()) {
        $campanas_proximas[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| CAMPAÑAS PRÓXIMAS A FINALIZAR
|--------------------------------------------------------------------------
*/

$campanas_por_finalizar = [];

$sqlFinalizar = "
    SELECT
        id,
        nombre,
        fecha_inicio,
        fecha_fin,
        estado,
        DATEDIFF(fecha_fin, CURDATE()) AS dias_restantes
    FROM marketing_campanas

    WHERE fecha_fin IS NOT NULL
      AND fecha_fin >= CURDATE()
      AND fecha_fin <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)
      AND estado = 'ACTIVA'

    ORDER BY fecha_fin ASC

    LIMIT 6
";

$resultadoFinalizar = $conexion->query($sqlFinalizar);

if ($resultadoFinalizar) {

    while ($fila = $resultadoFinalizar->fetch_assoc()) {
        $campanas_por_finalizar[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| ÚLTIMAS AUTORIZACIONES
|--------------------------------------------------------------------------
*/

$ultimas_autorizaciones = [];

$sqlAutorizaciones = "
    SELECT
        a.id,
        a.material_id,
        a.accion,
        a.comentario,
        a.fecha_accion,
        m.nombre AS material,
        e.nombre AS empleado
    FROM marketing_autorizaciones a

    INNER JOIN marketing_materiales m
        ON m.id = a.material_id

    LEFT JOIN usuarios u
        ON u.id = a.usuario_id

    LEFT JOIN empleados e
        ON e.id = u.empleado_id

    ORDER BY a.fecha_accion DESC

    LIMIT 8
";

$resultadoAutorizaciones = $conexion->query($sqlAutorizaciones);

if ($resultadoAutorizaciones) {

    while ($fila = $resultadoAutorizaciones->fetch_assoc()) {
        $ultimas_autorizaciones[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| AUDITORÍA RECIENTE
|--------------------------------------------------------------------------
*/

$auditoria_reciente = [];

$sqlAuditoria = "
    SELECT
        a.id,
        a.accion,
        a.modulo,
        a.referencia_id,
        a.descripcion,
        a.fecha,
        e.nombre AS empleado
    FROM marketing_auditoria a

    LEFT JOIN empleados e
        ON e.id = a.empleado_id

    ORDER BY a.fecha DESC

    LIMIT 10
";

$resultadoAuditoria = $conexion->query($sqlAuditoria);

if ($resultadoAuditoria) {

    while ($fila = $resultadoAuditoria->fetch_assoc()) {
        $auditoria_reciente[] = $fila;
    }

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Marketing</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        /*
        |--------------------------------------------------------------------------
        | CONTENEDOR
        |--------------------------------------------------------------------------
        */

        .contenedor {
            width: 94%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 25px 0 40px;
        }

        /*
        |--------------------------------------------------------------------------
        | ENCABEZADO
        |--------------------------------------------------------------------------
        */

        .encabezado {
            background: #ffffff;
            border-radius: 14px;
            padding: 22px 25px;
            margin-bottom: 22px;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .titulo h1 {
            margin: 0 0 6px;
            font-size: 28px;
            color: #172033;
        }

        .titulo p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .usuario {
            text-align: right;
        }

        .usuario strong {
            display: block;
            font-size: 15px;
            color: #172033;
        }

        .usuario span {
            display: inline-block;
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 12px;
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | TARJETAS DE INDICADORES
        |--------------------------------------------------------------------------
        */

        .indicadores {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            margin-bottom: 22px;
        }

        .indicador {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);
            border-left: 5px solid #64748b;
            min-height: 120px;
        }

        .indicador:hover {
            transform: translateY(-2px);
            transition: .2s;
        }

        .indicador .icono {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .indicador .numero {
            font-size: 29px;
            font-weight: bold;
            color: #111827;
        }

        .indicador .texto {
            margin-top: 3px;
            color: #6b7280;
            font-size: 12px;
            font-weight: bold;
        }

        .indicador.pendiente {
            border-left-color: #f59e0b;
        }

        .indicador.autorizado {
            border-left-color: #16a34a;
        }

        .indicador.rechazado {
            border-left-color: #dc2626;
        }

        .indicador.activa {
            border-left-color: #2563eb;
        }

        .indicador.campana {
            border-left-color: #7c3aed;
        }

        .indicador.finalizada {
            border-left-color: #64748b;
        }

        /*
        |--------------------------------------------------------------------------
        | ACCIONES RAPIDAS
        |--------------------------------------------------------------------------
        */

        .acciones {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 22px;
        }

        .accion {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px;
            text-decoration: none;
            color: #1f2937;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);
            border: 1px solid #e5e7eb;
            transition: .2s;
        }

        .accion:hover {
            transform: translateY(-2px);
            border-color: #c7d2fe;
            box-shadow: 0 7px 18px rgba(0,0,0,.09);
        }

        .accion .accion-icono {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .accion strong {
            display: block;
            font-size: 15px;
        }

        .accion span {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }

        /*
        |--------------------------------------------------------------------------
        | GRID PRINCIPAL
        |--------------------------------------------------------------------------
        */

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);
            overflow: hidden;
        }

        .panel-completo {
            grid-column: 1 / -1;
        }

        .panel-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 17px;
            color: #172033;
        }

        .panel-header a {
            font-size: 12px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .panel-body {
            padding: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLAS
        |--------------------------------------------------------------------------
        */

        .tabla {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla th {
            text-align: left;
            padding: 12px 15px;
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            background: #f8fafc;
        }

        .tabla td {
            padding: 13px 15px;
            border-top: 1px solid #edf0f4;
            font-size: 13px;
            vertical-align: middle;
        }

        .tabla tr:hover td {
            background: #fafafa;
        }

        .nombre {
            font-weight: bold;
            color: #1f2937;
        }

        .secundario {
            display: block;
            margin-top: 3px;
            color: #9ca3af;
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | BADGES
        |--------------------------------------------------------------------------
        */

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            white-space: nowrap;
        }

        .badge-pendiente {
            background: #fff7ed;
            color: #c2410c;
        }

        .badge-autorizado {
            background: #f0fdf4;
            color: #15803d;
        }

        .badge-rechazado {
            background: #fef2f2;
            color: #b91c1c;
        }

        .badge-activa {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .badge-borrador {
            background: #f3f4f6;
            color: #4b5563;
        }

        .badge-finalizada {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-autorizacion {
            background: #f0fdf4;
            color: #15803d;
        }

        .badge-rechazo {
            background: #fef2f2;
            color: #b91c1c;
        }

        /*
        |--------------------------------------------------------------------------
        | ALERTA DE CAMPAÑAS
        |--------------------------------------------------------------------------
        */

        .alerta-campana {
            padding: 14px 16px;
            border-bottom: 1px solid #edf0f4;
        }

        .alerta-campana:last-child {
            border-bottom: 0;
        }

        .alerta-titulo {
            font-weight: bold;
            font-size: 13px;
        }

        .alerta-info {
            margin-top: 5px;
            color: #6b7280;
            font-size: 12px;
        }

        .dias {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 20px;
            background: #fff7ed;
            color: #c2410c;
            font-size: 10px;
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | AUDITORÍA
        |--------------------------------------------------------------------------
        */

        .auditoria-item {
            padding: 14px 18px;
            border-bottom: 1px solid #edf0f4;
        }

        .auditoria-item:last-child {
            border-bottom: 0;
        }

        .auditoria-accion {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }

        .auditoria-descripcion {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }

        .auditoria-meta {
            margin-top: 6px;
            font-size: 10px;
            color: #9ca3af;
        }

        /*
        |--------------------------------------------------------------------------
        | ESTADO VACÍO
        |--------------------------------------------------------------------------
        */

        .vacio {
            padding: 35px 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 25px;
            text-align: center;
            color: #9ca3af;
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1250px) {

            .indicadores {
                grid-template-columns: repeat(4, 1fr);
            }

        }

        @media (max-width: 900px) {

            .grid {
                grid-template-columns: 1fr;
            }

            .panel-completo {
                grid-column: auto;
            }

            .acciones {
                grid-template-columns: repeat(2, 1fr);
            }

            .indicadores {
                grid-template-columns: repeat(2, 1fr);
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }

            .usuario {
                text-align: left;
            }

        }

        @media (max-width: 600px) {

            .contenedor {
                width: 95%;
                padding-top: 15px;
            }

            .indicadores,
            .acciones {
                grid-template-columns: 1fr;
            }

            .titulo h1 {
                font-size: 23px;
            }

            .tabla {
                min-width: 700px;
            }

            .panel-body {
                overflow-x: auto;
            }

        }

    </style>

</head>

<body>

<div class="contenedor">

    <!-- =====================================================
         ENCABEZADO
    ====================================================== -->

    <div class="encabezado">

        <div class="titulo">

            <h1>📊 Dashboard de Marketing</h1>

            <p>
                Resumen general del contenido, campañas y autorizaciones.
            </p>

        </div>

        <div class="usuario">

            <strong>
                <?php echo htmlspecialchars($nombre_usuario); ?>
            </strong>

            <span>
                <?php echo htmlspecialchars($rol_usuario); ?>
            </span>

        </div>

    </div>


    <!-- =====================================================
         INDICADORES
    ====================================================== -->

    <div class="indicadores">

        <div class="indicador">

            <div class="icono">📁</div>

            <div class="numero">
                <?php echo $total_materiales; ?>
            </div>

            <div class="texto">
                TOTAL MATERIALES
            </div>

        </div>


        <div class="indicador pendiente">

            <div class="icono">⏳</div>

            <div class="numero">
                <?php echo $materiales_pendientes; ?>
            </div>

            <div class="texto">
                PENDIENTES
            </div>

        </div>


        <div class="indicador autorizado">

            <div class="icono">✅</div>

            <div class="numero">
                <?php echo $materiales_autorizados; ?>
            </div>

            <div class="texto">
                AUTORIZADOS
            </div>

        </div>


        <div class="indicador rechazado">

            <div class="icono">❌</div>

            <div class="numero">
                <?php echo $materiales_rechazados; ?>
            </div>

            <div class="texto">
                RECHAZADOS
            </div>

        </div>


        <div class="indicador activa">

            <div class="icono">📢</div>

            <div class="numero">
                <?php echo $campanas_activas; ?>
            </div>

            <div class="texto">
                CAMPAÑAS ACTIVAS
            </div>

        </div>


        <div class="indicador campana">

            <div class="icono">📝</div>

            <div class="numero">
                <?php echo $campanas_pendientes; ?>
            </div>

            <div class="texto">
                CAMPAÑAS PENDIENTES
            </div>

        </div>


        <div class="indicador finalizada">

            <div class="icono">🏁</div>

            <div class="numero">
                <?php echo $campanas_finalizadas; ?>
            </div>

            <div class="texto">
                FINALIZADAS
            </div>

        </div>

    </div>


    <!-- =====================================================
         ACCIONES RÁPIDAS
    ====================================================== -->

    <div class="acciones">

        <a href="materiales.php" class="accion">

            <div class="accion-icono">📁</div>

            <strong>
                Materiales
            </strong>

            <span>
                Administrar flyers y archivos
            </span>

        </a>


        <a href="campanas.php" class="accion">

            <div class="accion-icono">📢</div>

            <strong>
                Campañas
            </strong>

            <span>
                Crear y administrar campañas
            </span>

        </a>


        <?php if ($rol_usuario === 'ADMIN' || $rol_usuario === 'GERENCIA'): ?>

            <a href="autorizaciones.php" class="accion">

                <div class="accion-icono">🔐</div>

                <strong>
                    Autorizaciones
                </strong>

                <span>
                    Revisar materiales pendientes
                </span>

            </a>

        <?php endif; ?>


        <?php if ($rol_usuario === 'ADMIN'): ?>

            <a href="categorias.php" class="accion">

                <div class="accion-icono">🏷️</div>

                <strong>
                    Categorías
                </strong>

                <span>
                    Administrar clasificación
                </span>

            </a>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
    ====================================================== -->

    <div class="grid">


        <!-- =================================================
             MATERIALES PENDIENTES
        ================================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    ⏳ Materiales pendientes
                </h2>

                <a href="autorizaciones.php">
                    Ver todos →
                </a>

            </div>

            <div class="panel-body">

                <?php if (empty($materiales_pendientes_lista)): ?>

                    <div class="vacio">
                        ✓ No hay materiales pendientes de autorización.
                    </div>

                <?php else: ?>

                    <table class="tabla">

                        <thead>

                            <tr>

                                <th>Material</th>

                                <th>Categoría</th>

                                <th>Subido por</th>

                                <th>Fecha</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($materiales_pendientes_lista as $material): ?>

                            <tr>

                                <td>

                                    <span class="nombre">

                                        <?php
                                        echo htmlspecialchars(
                                            $material['nombre']
                                        );
                                        ?>

                                    </span>

                                    <?php if (!empty($material['campana'])): ?>

                                        <span class="secundario">

                                            📢
                                            <?php
                                            echo htmlspecialchars(
                                                $material['campana']
                                            );
                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $material['categoria'] ?? 'Sin categoría'
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $material['empleado_subio'] ?? 'Usuario'
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo formatear_fecha_hora(
                                        $material['fecha_subida']
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             CAMPAÑAS PRÓXIMAS A FINALIZAR
        ================================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    ⏰ Campañas próximas a finalizar
                </h2>

                <a href="campanas.php">
                    Ver campañas →
                </a>

            </div>

            <div class="panel-body">

                <?php if (empty($campanas_por_finalizar)): ?>

                    <div class="vacio">
                        ✓ No hay campañas próximas a finalizar.
                    </div>

                <?php else: ?>

                    <?php foreach ($campanas_por_finalizar as $campana): ?>

                        <div class="alerta-campana">

                            <div class="alerta-titulo">

                                <?php
                                echo htmlspecialchars(
                                    $campana['nombre']
                                );
                                ?>

                            </div>

                            <div class="alerta-info">

                                Finaliza:
                                <strong>
                                    <?php
                                    echo formatear_fecha(
                                        $campana['fecha_fin']
                                    );
                                    ?>
                                </strong>

                            </div>

                            <span class="dias">

                                <?php

                                $dias = (int)$campana['dias_restantes'];

                                if ($dias === 0) {
                                    echo 'Finaliza hoy';
                                } elseif ($dias === 1) {
                                    echo 'Falta 1 día';
                                } else {
                                    echo 'Faltan ' . $dias . ' días';
                                }

                                ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             MATERIALES RECIENTES
        ================================================== -->

        <div class="panel panel-completo">

            <div class="panel-header">

                <h2>
                    📁 Materiales recientes
                </h2>

                <a href="materiales.php">
                    Administrar materiales →
                </a>

            </div>

            <div class="panel-body">

                <?php if (empty($materiales_recientes)): ?>

                    <div class="vacio">
                        No hay materiales registrados.
                    </div>

                <?php else: ?>

                    <table class="tabla">

                        <thead>

                            <tr>

                                <th>Material</th>

                                <th>Categoría</th>

                                <th>Campaña</th>

                                <th>Estado</th>

                                <th>Fecha</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($materiales_recientes as $material): ?>

                            <tr>

                                <td>

                                    <span class="nombre">

                                        <?php
                                        echo htmlspecialchars(
                                            $material['nombre']
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $material['categoria'] ?? 'Sin categoría'
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $material['campana'] ?? 'Sin campaña'
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php

                                    $estado = $material['estado'];

                                    $clase_estado = 'badge-borrador';

                                    if ($estado === 'PENDIENTE') {
                                        $clase_estado = 'badge-pendiente';
                                    }

                                    if ($estado === 'AUTORIZADO') {
                                        $clase_estado = 'badge-autorizado';
                                    }

                                    if ($estado === 'RECHAZADO') {
                                        $clase_estado = 'badge-rechazado';
                                    }

                                    ?>

                                    <span class="badge <?php echo $clase_estado; ?>">

                                        <?php
                                        echo htmlspecialchars($estado);
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php
                                    echo formatear_fecha_hora(
                                        $material['fecha_subida']
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             CAMPAÑAS ACTIVAS
        ================================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    📢 Campañas activas
                </h2>

                <a href="campanas.php">
                    Administrar →
                </a>

            </div>

            <div class="panel-body">

                <?php if (empty($campanas_activas_lista)): ?>

                    <div class="vacio">
                        No hay campañas activas actualmente.
                    </div>

                <?php else: ?>

                    <table class="tabla">

                        <thead>

                            <tr>

                                <th>Campaña</th>

                                <th>Materiales</th>

                                <th>Finaliza</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($campanas_activas_lista as $campana): ?>

                            <tr>

                                <td>

                                    <span class="nombre">

                                        <?php
                                        echo htmlspecialchars(
                                            $campana['nombre']
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <span class="badge badge-activa">

                                        <?php
                                        echo (int)$campana['total_materiales'];
                                        ?>
                                        materiales

                                    </span>

                                </td>

                                <td>

                                    <?php
                                    echo formatear_fecha(
                                        $campana['fecha_fin']
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             PRÓXIMAS CAMPAÑAS
        ================================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    📅 Próximas campañas
                </h2>

                <a href="campanas.php">
                    Ver calendario →
                </a>

            </div>

            <div class="panel-body">

                <?php if (empty($campanas_proximas)): ?>

                    <div class="vacio">
                        No hay campañas próximas programadas.
                    </div>

                <?php else: ?>

                    <table class="tabla">

                        <thead>

                            <tr>

                                <th>Campaña</th>

                                <th>Inicio</th>

                                <th>Fin</th>

                                <th>Estado</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($campanas_proximas as $campana): ?>

                            <tr>

                                <td>

                                    <span class="nombre">

                                        <?php
                                        echo htmlspecialchars(
                                            $campana['nombre']
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php
                                    echo formatear_fecha(
                                        $campana['fecha_inicio']
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo formatear_fecha(
                                        $campana['fecha_fin']
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php

                                    $clase = 'badge-borrador';

                                    if ($campana['estado'] === 'ACTIVA') {
                                        $clase = 'badge-activa';
                                    }

                                    if ($campana['estado'] === 'PENDIENTE') {
                                        $clase = 'badge-pendiente';
                                    }

                                    ?>

                                    <span class="badge <?php echo $clase; ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $campana['estado']
                                        );
                                        ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             AUTORIZACIONES
        ================================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    🔐 Últimas autorizaciones
                </h2>

                <?php if ($rol_usuario === 'ADMIN' || $rol_usuario === 'GERENCIA'): ?>

                    <a href="autorizaciones.php">
                        Revisar →
                    </a>

                <?php endif; ?>

            </div>

            <div class="panel-body">

                <?php if (empty($ultimas_autorizaciones)): ?>

                    <div class="vacio">
                        No hay autorizaciones registradas.
                    </div>

                <?php else: ?>

                    <table class="tabla">

                        <thead>

                            <tr>

                                <th>Material</th>

                                <th>Acción</th>

                                <th>Usuario</th>

                                <th>Fecha</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($ultimas_autorizaciones as $autorizacion): ?>

                            <tr>

                                <td>

                                    <span class="nombre">

                                        <?php
                                        echo htmlspecialchars(
                                            $autorizacion['material']
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if ($autorizacion['accion'] === 'AUTORIZADO'): ?>

                                        <span class="badge badge-autorizacion">
                                            ✓ AUTORIZADO
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-rechazo">
                                            ✕ RECHAZADO
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $autorizacion['empleado'] ?? 'Usuario'
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo formatear_fecha_hora(
                                        $autorizacion['fecha_accion']
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             AUDITORÍA
        ================================================== -->

        <div class="panel">

            <div class="panel-header">

                <h2>
                    🕵️ Actividad reciente
                </h2>

            </div>

            <div class="panel-body">

                <?php if (empty($auditoria_reciente)): ?>

                    <div class="vacio">
                        No hay movimientos registrados.
                    </div>

                <?php else: ?>

                    <?php foreach ($auditoria_reciente as $auditoria): ?>

                        <div class="auditoria-item">

                            <div class="auditoria-accion">

                                <?php
                                echo htmlspecialchars(
                                    $auditoria['accion']
                                );
                                ?>

                                <span style="font-weight:normal;color:#9ca3af;">

                                    ·

                                    <?php
                                    echo htmlspecialchars(
                                        $auditoria['modulo']
                                    );
                                    ?>

                                </span>

                            </div>

                            <div class="auditoria-descripcion">

                                <?php
                                echo htmlspecialchars(
                                    $auditoria['descripcion']
                                );
                                ?>

                            </div>

                            <div class="auditoria-meta">

                                👤
                                <?php
                                echo htmlspecialchars(
                                    $auditoria['empleado'] ?? 'Usuario'
                                );
                                ?>

                                &nbsp;&nbsp;|&nbsp;&nbsp;

                                🕒
                                <?php
                                echo formatear_fecha_hora(
                                    $auditoria['fecha']
                                );
                                ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div class="footer">

        Sistema de Marketing · Control de campañas y materiales

    </div>

</div>

</body>

</html>