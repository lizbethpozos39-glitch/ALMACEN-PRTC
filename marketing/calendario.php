<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());


/*
|--------------------------------------------------------------------------
| MES Y AÑO ACTUAL
|--------------------------------------------------------------------------
*/

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');


/*
|--------------------------------------------------------------------------
| VALIDAR MES / AÑO
|--------------------------------------------------------------------------
*/

if ($mes < 1 || $mes > 12) {
    $mes = (int)date('m');
}

if ($anio < 2000 || $anio > 2100) {
    $anio = (int)date('Y');
}


/*
|--------------------------------------------------------------------------
| PRIMER Y ÚLTIMO DÍA DEL MES
|--------------------------------------------------------------------------
*/

$primer_dia = sprintf(
    '%04d-%02d-01',
    $anio,
    $mes
);

$ultimo_dia = date(
    'Y-m-t',
    strtotime($primer_dia)
);


/*
|--------------------------------------------------------------------------
| NOMBRE DEL MES
|--------------------------------------------------------------------------
*/

$nombres_meses = [
    1  => 'Enero',
    2  => 'Febrero',
    3  => 'Marzo',
    4  => 'Abril',
    5  => 'Mayo',
    6  => 'Junio',
    7  => 'Julio',
    8  => 'Agosto',
    9  => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

$nombre_mes = $nombres_meses[$mes];


/*
|--------------------------------------------------------------------------
| MES ANTERIOR
|--------------------------------------------------------------------------
*/

$fecha_anterior = new DateTime($primer_dia);
$fecha_anterior->modify('-1 month');

$mes_anterior  = (int)$fecha_anterior->format('m');
$anio_anterior = (int)$fecha_anterior->format('Y');


/*
|--------------------------------------------------------------------------
| MES SIGUIENTE
|--------------------------------------------------------------------------
*/

$fecha_siguiente = new DateTime($primer_dia);
$fecha_siguiente->modify('+1 month');

$mes_siguiente  = (int)$fecha_siguiente->format('m');
$anio_siguiente = (int)$fecha_siguiente->format('Y');


/*
|--------------------------------------------------------------------------
| CAMPAÑAS
|--------------------------------------------------------------------------
|
| Una campaña aparece en todos los días que cubre su periodo.
|
*/

$campanas = [];

$sql = "
    SELECT
        c.id,
        c.nombre,
        c.descripcion,
        c.objetivo,
        c.fecha_inicio,
        c.fecha_fin,
        c.estado,
        c.responsable_id,
        COUNT(m.id) AS total_materiales
    FROM marketing_campanas c

    LEFT JOIN marketing_materiales m
        ON m.campana_id = c.id

    WHERE
        c.fecha_inicio IS NOT NULL
        AND c.fecha_fin IS NOT NULL
        AND c.fecha_inicio <= ?
        AND c.fecha_fin >= ?

    GROUP BY
        c.id,
        c.nombre,
        c.descripcion,
        c.objetivo,
        c.fecha_inicio,
        c.fecha_fin,
        c.estado,
        c.responsable_id

    ORDER BY
        c.fecha_inicio ASC,
        c.nombre ASC
";

$stmt = $conexion->prepare($sql);

if ($stmt) {

    $stmt->bind_param(
        "ss",
        $ultimo_dia,
        $primer_dia
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $campanas[] = $fila;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| AGRUPAR CAMPAÑAS POR DÍA
|--------------------------------------------------------------------------
*/

$campanas_por_dia = [];


foreach ($campanas as $campana) {

    $inicio = new DateTime($campana['fecha_inicio']);
    $fin    = new DateTime($campana['fecha_fin']);

    $inicio_mes = new DateTime($primer_dia);
    $fin_mes    = new DateTime($ultimo_dia);

    /*
    | Ajustar inicio al primer día visible del mes
    */

    if ($inicio < $inicio_mes) {
        $inicio = clone $inicio_mes;
    }

    /*
    | Ajustar final al último día del mes
    */

    if ($fin > $fin_mes) {
        $fin = clone $fin_mes;
    }

    while ($inicio <= $fin) {

        $dia = (int)$inicio->format('j');

        if (!isset($campanas_por_dia[$dia])) {
            $campanas_por_dia[$dia] = [];
        }

        $campanas_por_dia[$dia][] = $campana;

        $inicio->modify('+1 day');
    }
}


/*
|--------------------------------------------------------------------------
| PRIMER DÍA DE LA SEMANA
|--------------------------------------------------------------------------
|
| PHP:
| N = 1 lunes
| N = 7 domingo
|
*/

$fecha_inicio_mes = new DateTime($primer_dia);

$dia_semana = (int)$fecha_inicio_mes->format('N');

$dias_antes = $dia_semana - 1;


/*
|--------------------------------------------------------------------------
| TOTAL DÍAS DEL MES
|--------------------------------------------------------------------------
*/

$total_dias = (int)date(
    't',
    strtotime($primer_dia)
);


/*
|--------------------------------------------------------------------------
| CELDAS NECESARIAS
|--------------------------------------------------------------------------
*/

$total_celdas = $dias_antes + $total_dias;

$semanas = (int)ceil($total_celdas / 7);

$total_celdas = $semanas * 7;


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS DEL MES
|--------------------------------------------------------------------------
*/

$total_campanas_mes = count($campanas);

$activas_mes = 0;
$pendientes_mes = 0;
$borradores_mes = 0;
$finalizadas_mes = 0;
$canceladas_mes = 0;

foreach ($campanas as $campana) {

    switch ($campana['estado']) {

        case 'ACTIVA':
            $activas_mes++;
            break;

        case 'PENDIENTE':
            $pendientes_mes++;
            break;

        case 'BORRADOR':
            $borradores_mes++;
            break;

        case 'FINALIZADA':
            $finalizadas_mes++;
            break;

        case 'CANCELADA':
            $canceladas_mes++;
            break;
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIONES VISUALES
|--------------------------------------------------------------------------
*/

function clase_estado_calendario($estado)
{
    switch ($estado) {

        case 'ACTIVA':
            return 'estado-activa';

        case 'PENDIENTE':
            return 'estado-pendiente';

        case 'BORRADOR':
            return 'estado-borrador';

        case 'FINALIZADA':
            return 'estado-finalizada';

        case 'CANCELADA':
            return 'estado-cancelada';

        default:
            return 'estado-borrador';
    }
}


function nombre_estado_calendario($estado)
{
    switch ($estado) {

        case 'ACTIVA':
            return 'ACTIVA';

        case 'PENDIENTE':
            return 'PENDIENTE';

        case 'BORRADOR':
            return 'BORRADOR';

        case 'FINALIZADA':
            return 'FINALIZADA';

        case 'CANCELADA':
            return 'CANCELADA';

        default:
            return $estado;
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

    <title>
        Calendario - Marketing
    </title>


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


        .contenedor {
            width: 95%;
            max-width: 1450px;
            margin: auto;
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
            margin-bottom: 20px;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;

            box-shadow: 0 4px 14px rgba(0,0,0,.06);
        }


        .titulo h1 {
            margin: 0 0 6px;
            font-size: 27px;
            color: #172033;
        }


        .titulo p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }


        .usuario {
            text-align: right;
        }


        .usuario strong {
            display: block;
            font-size: 14px;
        }


        .usuario span {
            display: inline-block;
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 20px;

            background: #eef2ff;
            color: #3730a3;

            font-size: 11px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | BOTONES
        |--------------------------------------------------------------------------
        */

        .barra-acciones {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }


        .grupo-botones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }


        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 9px;

            text-decoration: none;
            border: none;

            font-size: 12px;
            font-weight: bold;

            cursor: pointer;
        }


        .btn-principal {
            background: #2563eb;
            color: #ffffff;
        }


        .btn-principal:hover {
            background: #1d4ed8;
        }


        .btn-secundario {
            background: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
        }


        .btn-secundario:hover {
            background: #f9fafb;
        }


        /*
        |--------------------------------------------------------------------------
        | SELECTOR DE MES
        |--------------------------------------------------------------------------
        */

        .navegacion-mes {
            background: #ffffff;
            border-radius: 14px;

            padding: 15px 18px;
            margin-bottom: 20px;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;

            box-shadow: 0 4px 14px rgba(0,0,0,.06);
        }


        .mes-anterior,
        .mes-siguiente {
            width: 45px;
            height: 40px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            text-decoration: none;

            background: #f3f4f6;
            color: #374151;

            font-size: 20px;
            font-weight: bold;
        }


        .mes-anterior:hover,
        .mes-siguiente:hover {
            background: #e5e7eb;
        }


        .mes-actual {
            text-align: center;
        }


        .mes-actual h2 {
            margin: 0;
            font-size: 24px;
            color: #172033;
        }


        .mes-actual span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #9ca3af;
        }


        /*
        |--------------------------------------------------------------------------
        | ESTADÍSTICAS
        |--------------------------------------------------------------------------
        */

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }


        .estadistica {
            background: #ffffff;
            border-radius: 12px;
            padding: 14px 16px;

            box-shadow: 0 4px 14px rgba(0,0,0,.05);

            border-left: 4px solid #64748b;
        }


        .estadistica .numero {
            font-size: 25px;
            font-weight: bold;
        }


        .estadistica .texto {
            margin-top: 3px;
            color: #6b7280;
            font-size: 10px;
            font-weight: bold;
        }


        .estadistica.total {
            border-left-color: #2563eb;
        }


        .estadistica.activa {
            border-left-color: #16a34a;
        }


        .estadistica.pendiente {
            border-left-color: #f59e0b;
        }


        .estadistica.borrador {
            border-left-color: #64748b;
        }


        .estadistica.finalizada {
            border-left-color: #7c3aed;
        }


        .estadistica.cancelada {
            border-left-color: #dc2626;
        }


        /*
        |--------------------------------------------------------------------------
        | CALENDARIO
        |--------------------------------------------------------------------------
        */

        .calendario-panel {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;

            box-shadow: 0 4px 14px rgba(0,0,0,.06);
        }


        .dias-semana {
            display: grid;
            grid-template-columns: repeat(7, 1fr);

            background: #172033;
            color: #ffffff;
        }


        .dia-semana {
            padding: 12px;
            text-align: center;

            font-size: 11px;
            font-weight: bold;
        }


        .calendario {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }


        .dia {
            min-height: 145px;

            border-right: 1px solid #edf0f4;
            border-bottom: 1px solid #edf0f4;

            padding: 8px;

            position: relative;

            background: #ffffff;
        }


        .dia:nth-child(7n) {
            border-right: none;
        }


        .dia.vacio {
            background: #f8fafc;
        }


        .numero-dia {
            width: 27px;
            height: 27px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            font-size: 12px;
            font-weight: bold;

            color: #4b5563;

            margin-bottom: 5px;
        }


        .hoy .numero-dia {
            background: #2563eb;
            color: #ffffff;
        }


        /*
        |--------------------------------------------------------------------------
        | CAMPAÑAS EN CALENDARIO
        |--------------------------------------------------------------------------
        */

        .campana {
            display: block;

            width: 100%;

            margin-bottom: 5px;
            padding: 6px 7px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 10px;
            font-weight: bold;

            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;

            cursor: pointer;
        }


        .campana:hover {
            filter: brightness(.96);
        }


        .campana small {
            display: block;
            margin-top: 2px;

            font-size: 9px;
            font-weight: normal;

            opacity: .8;
        }


        /*
        |--------------------------------------------------------------------------
        | ESTADOS
        |--------------------------------------------------------------------------
        */

        .estado-activa {
            background: #dcfce7;
            color: #166534;
            border-left: 3px solid #16a34a;
        }


        .estado-pendiente {
            background: #fef3c7;
            color: #92400e;
            border-left: 3px solid #f59e0b;
        }


        .estado-borrador {
            background: #f1f5f9;
            color: #475569;
            border-left: 3px solid #64748b;
        }


        .estado-finalizada {
            background: #ede9fe;
            color: #5b21b6;
            border-left: 3px solid #7c3aed;
        }


        .estado-cancelada {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #dc2626;
        }


        /*
        |--------------------------------------------------------------------------
        | LEYENDA
        |--------------------------------------------------------------------------
        */

        .leyenda {
            padding: 15px 18px;

            display: flex;
            gap: 18px;
            flex-wrap: wrap;

            border-top: 1px solid #edf0f4;
        }


        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 6px;

            font-size: 10px;
            color: #6b7280;
            font-weight: bold;
        }


        .punto {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }


        .punto-activa {
            background: #16a34a;
        }


        .punto-pendiente {
            background: #f59e0b;
        }


        .punto-borrador {
            background: #64748b;
        }


        .punto-finalizada {
            background: #7c3aed;
        }


        .punto-cancelada {
            background: #dc2626;
        }


        /*
        |--------------------------------------------------------------------------
        | MODAL
        |--------------------------------------------------------------------------
        */

        .modal {
            display: none;

            position: fixed;
            inset: 0;

            z-index: 9999;

            background: rgba(0,0,0,.5);

            align-items: center;
            justify-content: center;

            padding: 20px;
        }


        .modal-contenido {
            width: 100%;
            max-width: 560px;

            background: #ffffff;

            border-radius: 14px;

            padding: 25px;

            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }


        .modal-contenido h2 {
            margin: 0 0 18px;

            font-size: 21px;
            color: #172033;
        }


        .detalle {
            margin-bottom: 13px;
        }


        .detalle label {
            display: block;

            margin-bottom: 4px;

            font-size: 10px;
            font-weight: bold;

            color: #9ca3af;

            text-transform: uppercase;
        }


        .detalle div {
            font-size: 13px;
            color: #374151;
        }


        .modal-footer {
            display: flex;
            justify-content: flex-end;

            margin-top: 20px;
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

        @media (max-width: 1100px) {

            .estadisticas {
                grid-template-columns: repeat(3, 1fr);
            }

            .dia {
                min-height: 125px;
            }

        }


        @media (max-width: 800px) {

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }

            .usuario {
                text-align: left;
            }

            .estadisticas {
                grid-template-columns: repeat(2, 1fr);
            }

            .calendario-panel {
                overflow-x: auto;
            }

            .dias-semana,
            .calendario {
                min-width: 850px;
            }

        }


        @media (max-width: 500px) {

            .estadisticas {
                grid-template-columns: 1fr;
            }

            .mes-actual h2 {
                font-size: 19px;
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

            <h1>
                📅 Calendario de Marketing
            </h1>

            <p>
                Visualiza la programación de campañas y periodos de trabajo.
            </p>

        </div>


        <div class="usuario">

            <strong>
                <?php
                echo htmlspecialchars($nombre_usuario);
                ?>
            </strong>

            <span>
                <?php
                echo htmlspecialchars($rol_usuario);
                ?>
            </span>

        </div>

    </div>


    <!-- =====================================================
         ACCIONES
    ====================================================== -->

    <div class="barra-acciones">

        <div class="grupo-botones">

            <a
                href="dashboard.php"
                class="btn btn-secundario"
            >
                📊 Dashboard
            </a>


            <a
                href="campanas.php"
                class="btn btn-principal"
            >
                📢 Administrar campañas
            </a>


            <a
                href="materiales.php"
                class="btn btn-secundario"
            >
                📁 Materiales
            </a>

        </div>

    </div>


    <!-- =====================================================
         NAVEGACIÓN DEL MES
    ====================================================== -->

    <div class="navegacion-mes">


        <a
            href="calendario.php?mes=<?php echo $mes_anterior; ?>&anio=<?php echo $anio_anterior; ?>"
            class="mes-anterior"
            title="Mes anterior"
        >
            ‹
        </a>


        <div class="mes-actual">

            <h2>

                <?php
                echo htmlspecialchars($nombre_mes);
                ?>

                <?php
                echo $anio;
                ?>

            </h2>

            <span>
                Programación de campañas
            </span>

        </div>


        <a
            href="calendario.php?mes=<?php echo $mes_siguiente; ?>&anio=<?php echo $anio_siguiente; ?>"
            class="mes-siguiente"
            title="Mes siguiente"
        >
            ›
        </a>

    </div>


    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div class="estadisticas">


        <div class="estadistica total">

            <div class="numero">
                <?php echo $total_campanas_mes; ?>
            </div>

            <div class="texto">
                CAMPAÑAS DEL MES
            </div>

        </div>


        <div class="estadistica activa">

            <div class="numero">
                <?php echo $activas_mes; ?>
            </div>

            <div class="texto">
                ACTIVAS
            </div>

        </div>


        <div class="estadistica pendiente">

            <div class="numero">
                <?php echo $pendientes_mes; ?>
            </div>

            <div class="texto">
                PENDIENTES
            </div>

        </div>


        <div class="estadistica borrador">

            <div class="numero">
                <?php echo $borradores_mes; ?>
            </div>

            <div class="texto">
                BORRADORES
            </div>

        </div>


        <div class="estadistica finalizada">

            <div class="numero">
                <?php echo $finalizadas_mes; ?>
            </div>

            <div class="texto">
                FINALIZADAS
            </div>

        </div>


        <div class="estadistica cancelada">

            <div class="numero">
                <?php echo $canceladas_mes; ?>
            </div>

            <div class="texto">
                CANCELADAS
            </div>

        </div>

    </div>


    <!-- =====================================================
         CALENDARIO
    ====================================================== -->

    <div class="calendario-panel">


        <!-- DÍAS DE LA SEMANA -->

        <div class="dias-semana">

            <div class="dia-semana">
                LUN
            </div>

            <div class="dia-semana">
                MAR
            </div>

            <div class="dia-semana">
                MIÉ
            </div>

            <div class="dia-semana">
                JUE
            </div>

            <div class="dia-semana">
                VIE
            </div>

            <div class="dia-semana">
                SÁB
            </div>

            <div class="dia-semana">
                DOM
            </div>

        </div>


        <!-- DÍAS -->

        <div class="calendario">

        <?php

        /*
        |--------------------------------------------------------------------------
        | CELDAS VACÍAS ANTES DEL PRIMER DÍA
        |--------------------------------------------------------------------------
        */

        for ($i = 0; $i < $dias_antes; $i++) {

            echo '<div class="dia vacio"></div>';

        }


        /*
        |--------------------------------------------------------------------------
        | DÍAS DEL MES
        |--------------------------------------------------------------------------
        */

        for ($dia = 1; $dia <= $total_dias; $dia++) {

            $fecha_dia = sprintf(
                '%04d-%02d-%02d',
                $anio,
                $mes,
                $dia
            );


            $es_hoy = (
                $fecha_dia === date('Y-m-d')
            );


            ?>

            <div
                class="dia <?php
                    echo $es_hoy ? 'hoy' : '';
                ?>"
            >


                <div class="numero-dia">

                    <?php
                    echo $dia;
                    ?>

                </div>


                <?php

                if (isset($campanas_por_dia[$dia])) {

                    foreach (
                        $campanas_por_dia[$dia]
                        as $campana
                    ) {

                        $clase =
                            clase_estado_calendario(
                                $campana['estado']
                            );

                        ?>

                        <div
                            class="campana <?php echo $clase; ?>"
                            onclick='mostrarCampana(
                                <?php
                                echo json_encode(
                                    [
                                        'id' => $campana['id'],
                                        'nombre' => $campana['nombre'],
                                        'descripcion' => $campana['descripcion'],
                                        'objetivo' => $campana['objetivo'],
                                        'inicio' => $campana['fecha_inicio'],
                                        'fin' => $campana['fecha_fin'],
                                        'estado' => $campana['estado'],
                                        'materiales' => $campana['total_materiales']
                                    ],
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                );
                                ?>
                            )'
                            title="<?php
                            echo htmlspecialchars(
                                $campana['nombre']
                            );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $campana['nombre']
                            );
                            ?>


                            <small>

                                <?php
                                echo htmlspecialchars(
                                    $campana['estado']
                                );
                                ?>

                                ·

                                <?php
                                echo (int)$campana['total_materiales'];
                                ?>

                                material(es)

                            </small>

                        </div>

                        <?php

                    }

                }

                ?>

            </div>

            <?php

        }


        /*
        |--------------------------------------------------------------------------
        | CELDAS VACÍAS DESPUÉS DEL MES
        |--------------------------------------------------------------------------
        */

        $celdas_restantes =
            $total_celdas -
            ($dias_antes + $total_dias);


        for (
            $i = 0;
            $i < $celdas_restantes;
            $i++
        ) {

            echo '<div class="dia vacio"></div>';

        }

        ?>

        </div>


        <!-- =================================================
             LEYENDA
        ================================================== -->

        <div class="leyenda">


            <div class="leyenda-item">

                <span class="punto punto-activa"></span>

                Activa

            </div>


            <div class="leyenda-item">

                <span class="punto punto-pendiente"></span>

                Pendiente

            </div>


            <div class="leyenda-item">

                <span class="punto punto-borrador"></span>

                Borrador

            </div>


            <div class="leyenda-item">

                <span class="punto punto-finalizada"></span>

                Finalizada

            </div>


            <div class="leyenda-item">

                <span class="punto punto-cancelada"></span>

                Cancelada

            </div>

        </div>

    </div>


    <div class="footer">

        Sistema de Marketing · Calendario de campañas

    </div>

</div>


<!-- =====================================================
     MODAL DETALLE CAMPAÑA
====================================================== -->

<div
    id="modalCampana"
    class="modal"
>

    <div class="modal-contenido">


        <h2 id="modalTitulo">
            Campaña
        </h2>


        <div class="detalle">

            <label>
                Estado
            </label>

            <div id="modalEstado"></div>

        </div>


        <div class="detalle">

            <label>
                Periodo
            </label>

            <div id="modalPeriodo"></div>

        </div>


        <div class="detalle">

            <label>
                Materiales
            </label>

            <div id="modalMateriales"></div>

        </div>


        <div
            class="detalle"
            id="bloqueDescripcion"
        >

            <label>
                Descripción
            </label>

            <div id="modalDescripcion"></div>

        </div>


        <div
            class="detalle"
            id="bloqueObjetivo"
        >

            <label>
                Objetivo
            </label>

            <div id="modalObjetivo"></div>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-secundario"
                onclick="cerrarModal()"
            >
                Cerrar
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| MOSTRAR CAMPAÑA
|--------------------------------------------------------------------------
*/

function mostrarCampana(campana)
{

    document.getElementById('modalTitulo').textContent =
        campana.nombre || 'Campaña';


    document.getElementById('modalEstado').textContent =
        campana.estado || '';


    document.getElementById('modalPeriodo').textContent =
        formatearFecha(campana.inicio) +
        ' al ' +
        formatearFecha(campana.fin);


    document.getElementById('modalMateriales').textContent =
        (campana.materiales || 0) +
        ' material(es)';


    const bloqueDescripcion =
        document.getElementById('bloqueDescripcion');

    const bloqueObjetivo =
        document.getElementById('bloqueObjetivo');


    const descripcion =
        document.getElementById('modalDescripcion');

    const objetivo =
        document.getElementById('modalObjetivo');


    if (campana.descripcion) {

        descripcion.textContent =
            campana.descripcion;

        bloqueDescripcion.style.display =
            'block';

    } else {

        bloqueDescripcion.style.display =
            'none';

    }


    if (campana.objetivo) {

        objetivo.textContent =
            campana.objetivo;

        bloqueObjetivo.style.display =
            'block';

    } else {

        bloqueObjetivo.style.display =
            'none';

    }


    document.getElementById(
        'modalCampana'
    ).style.display = 'flex';

}


/*
|--------------------------------------------------------------------------
| CERRAR MODAL
|--------------------------------------------------------------------------
*/

function cerrarModal()
{
    document.getElementById(
        'modalCampana'
    ).style.display = 'none';
}


/*
|--------------------------------------------------------------------------
| FORMATEAR FECHA
|--------------------------------------------------------------------------
*/

function formatearFecha(fecha)
{

    if (!fecha) {
        return 'Sin fecha';
    }


    const partes =
        fecha.split('-');


    if (partes.length !== 3) {
        return fecha;
    }


    return (
        partes[2] +
        '/' +
        partes[1] +
        '/' +
        partes[0]
    );

}


/*
|--------------------------------------------------------------------------
| CLICK FUERA DEL MODAL
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'click',
    function(event)
    {

        const modal =
            document.getElementById(
                'modalCampana'
            );


        if (event.target === modal) {

            cerrarModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event)
    {

        if (event.key === 'Escape') {

            cerrarModal();

        }

    }
);

</script>


</body>

</html>