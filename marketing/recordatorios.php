<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();

$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());

$buscar = trim($_GET['buscar'] ?? '');
$filtro = strtoupper(trim($_GET['filtro'] ?? 'TODOS'));

$hoy = date('Y-m-d');


/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA DETERMINAR ESTADO DEL RECORDATORIO
|--------------------------------------------------------------------------
*/

function estado_recordatorio($inicio, $fin, $dias_anticipacion, $hoy)
{
    if (!$inicio && !$fin) {

        return [
            'codigo' => 'SIN_FECHA',
            'texto'  => 'SIN FECHA',
            'clase'  => 'sin-fecha',
            'detalle' => 'No tiene fechas configuradas.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPORADA YA FINALIZADA
    |--------------------------------------------------------------------------
    */

    if ($fin && $hoy > $fin) {

        return [
            'codigo' => 'FINALIZADA',
            'texto'  => 'FINALIZADA',
            'clase'  => 'finalizada',
            'detalle' => 'La temporada ya terminó.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPORADA VIGENTE
    |--------------------------------------------------------------------------
    */

    if (
        $inicio &&
        $fin &&
        $hoy >= $inicio &&
        $hoy <= $fin
    ) {

        return [
            'codigo' => 'VIGENTE',
            'texto'  => 'VIGENTE',
            'clase'  => 'vigente',
            'detalle' => 'La temporada está actualmente activa.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TODAVÍA NO COMIENZA
    |--------------------------------------------------------------------------
    */

    if ($inicio && $hoy < $inicio) {

        $timestamp_hoy    = strtotime($hoy);
        $timestamp_inicio = strtotime($inicio);

        $diferencia = (int)floor(
            ($timestamp_inicio - $timestamp_hoy) / 86400
        );


        /*
        |--------------------------------------------------------------------------
        | YA ENTRA EN PERIODO DE PREPARACIÓN
        |--------------------------------------------------------------------------
        */

        if ($diferencia <= (int)$dias_anticipacion) {

            if ($diferencia <= 7) {

                return [
                    'codigo' => 'URGENTE',
                    'texto'  => 'URGENTE',
                    'clase'  => 'urgente',
                    'detalle' =>
                        'Faltan ' .
                        $diferencia .
                        ' día(s) para el inicio.'
                ];
            }

            return [
                'codigo' => 'PREPARAR',
                'texto'  => 'PREPARAR MATERIAL',
                'clase'  => 'preparar',
                'detalle' =>
                    'Faltan ' .
                    $diferencia .
                    ' día(s) para el inicio.'
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | TODAVÍA HAY TIEMPO
        |--------------------------------------------------------------------------
        */

        return [
            'codigo' => 'EN_TIEMPO',
            'texto'  => 'EN TIEMPO',
            'clase'  => 'en-tiempo',
            'detalle' =>
                'Faltan ' .
                $diferencia .
                ' día(s) para el inicio.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | SI SOLO TIENE FECHA FINAL
    |--------------------------------------------------------------------------
    */

    if (!$inicio && $fin) {

        return [
            'codigo' => 'VIGENTE',
            'texto'  => 'VIGENTE',
            'clase'  => 'vigente',
            'detalle' => 'Temporada sin fecha de inicio.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | SI SOLO TIENE FECHA INICIAL Y YA LLEGÓ
    |--------------------------------------------------------------------------
    */

    if ($inicio && !$fin && $hoy >= $inicio) {

        return [
            'codigo' => 'VIGENTE',
            'texto'  => 'VIGENTE',
            'clase'  => 'vigente',
            'detalle' => 'Temporada iniciada.'
        ];
    }


    return [
        'codigo' => 'SIN_FECHA',
        'texto'  => 'SIN FECHA',
        'clase'  => 'sin-fecha',
        'detalle' => 'Revisar configuración.'
    ];
}


/*
|--------------------------------------------------------------------------
| FORMATO DE FECHA
|--------------------------------------------------------------------------
*/

function formato_fecha_recordatorio($fecha)
{
    if (!$fecha) {
        return 'Sin definir';
    }

    return date('d/m/Y', strtotime($fecha));
}


/*
|--------------------------------------------------------------------------
| OBTENER TEMPORADAS
|--------------------------------------------------------------------------
*/

$temporadas = [];

$sql = "
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        dias_anticipacion,
        activo,
        fecha_registro
    FROM marketing_temporadas
    WHERE 1 = 1
";


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

if ($buscar !== '') {

    $sql .= "
        AND (
            nombre LIKE ?
            OR descripcion LIKE ?
        )
    ";
}


/*
|--------------------------------------------------------------------------
| SOLO ACTIVAS
|--------------------------------------------------------------------------
*/

$sql .= "
    AND activo = 1
";


$sql .= "
    ORDER BY
        fecha_inicio IS NULL,
        fecha_inicio ASC,
        nombre ASC
";


$stmt = $conexion->prepare($sql);

if ($stmt) {

    if ($buscar !== '') {

        $buscarLike = "%" . $buscar . "%";

        $stmt->bind_param(
            "ss",
            $buscarLike,
            $buscarLike
        );
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        $estado = estado_recordatorio(
            $fila['fecha_inicio'],
            $fila['fecha_fin'],
            $fila['dias_anticipacion'],
            $hoy
        );

        $fila['estado_recordatorio'] = $estado;

        /*
        |--------------------------------------------------------------------------
        | APLICAR FILTRO
        |--------------------------------------------------------------------------
        */

        if (
            $filtro !== 'TODOS' &&
            $estado['codigo'] !== $filtro
        ) {
            continue;
        }

        $temporadas[] = $fila;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$total = count($temporadas);

$urgentes    = 0;
$preparar    = 0;
$en_tiempo   = 0;
$vigentes    = 0;
$finalizadas = 0;
$sin_fecha   = 0;

foreach ($temporadas as $temporada) {

    switch ($temporada['estado_recordatorio']['codigo']) {

        case 'URGENTE':
            $urgentes++;
            break;

        case 'PREPARAR':
            $preparar++;
            break;

        case 'EN_TIEMPO':
            $en_tiempo++;
            break;

        case 'VIGENTE':
            $vigentes++;
            break;

        case 'FINALIZADA':
            $finalizadas++;
            break;

        case 'SIN_FECHA':
            $sin_fecha++;
            break;
    }
}

$requieren_atencion = $urgentes + $preparar;


/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Recordatorios | Marketing</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #1f2937;
}


/*
|--------------------------------------------------------------------------
| CONTENEDOR
|--------------------------------------------------------------------------
*/

.contenedor {

    width: 94%;

    max-width: 1450px;

    margin: 30px auto;
}


/*
|--------------------------------------------------------------------------
| ENCABEZADO
|--------------------------------------------------------------------------
*/

.encabezado {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;
}

.titulo h1 {

    margin: 0;

    font-size: 30px;
}

.titulo p {

    margin: 7px 0 0;

    color: #6b7280;
}

.botones {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| BOTONES
|--------------------------------------------------------------------------
*/

.btn {

    display: inline-block;

    border: none;

    border-radius: 8px;

    padding: 11px 17px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

    font-weight: bold;
}

.btn-principal {

    background: #2563eb;

    color: white;
}

.btn-principal:hover {

    background: #1d4ed8;
}

.btn-secundario {

    background: #e5e7eb;

    color: #374151;
}

.btn-secundario:hover {

    background: #d1d5db;
}


/*
|--------------------------------------------------------------------------
| ALERTA SUPERIOR
|--------------------------------------------------------------------------
*/

.alerta-atencion {

    background: #fff7ed;

    border: 1px solid #fed7aa;

    color: #9a3412;

    border-radius: 10px;

    padding: 17px 20px;

    margin-bottom: 22px;

    font-size: 15px;
}

.alerta-atencion strong {

    font-size: 17px;
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.tarjeta {

    background: white;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.06);
}

.tarjeta .numero {

    font-size: 28px;

    font-weight: bold;

    margin-bottom: 5px;
}

.tarjeta .texto {

    color: #6b7280;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| BUSCADOR
|--------------------------------------------------------------------------
*/

.buscador {

    background: white;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.06);
}

.form-busqueda {

    display: grid;

    grid-template-columns:
        1fr 220px auto auto;

    gap: 10px;
}

.form-busqueda input,
.form-busqueda select {

    width: 100%;

    padding: 11px;

    border:
        1px solid #d1d5db;

    border-radius: 7px;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| TABLA
|--------------------------------------------------------------------------
*/

.contenedor-tabla {

    background: white;

    border-radius: 12px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.06);

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1050px;
}

thead {

    background: #f9fafb;
}

th,
td {

    padding: 14px 15px;

    border-bottom:
        1px solid #e5e7eb;

    text-align: left;

    font-size: 14px;
}

th {

    font-size: 13px;

    color: #4b5563;

    text-transform: uppercase;
}

tbody tr:hover {

    background: #f9fafb;
}


/*
|--------------------------------------------------------------------------
| NOMBRE
|--------------------------------------------------------------------------
*/

.nombre-temporada {

    font-weight: bold;

    color: #111827;

    font-size: 15px;
}

.descripcion {

    color: #6b7280;

    max-width: 300px;

    margin-top: 5px;

    line-height: 1.4;
}


/*
|--------------------------------------------------------------------------
| ESTADOS
|--------------------------------------------------------------------------
*/

.estado {

    display: inline-block;

    padding: 7px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    white-space: nowrap;
}

.estado.urgente {

    background: #fee2e2;

    color: #991b1b;
}

.estado.preparar {

    background: #ffedd5;

    color: #9a3412;
}

.estado.en-tiempo {

    background: #dcfce7;

    color: #166534;
}

.estado.vigente {

    background: #dbeafe;

    color: #1d4ed8;
}

.estado.finalizada {

    background: #e5e7eb;

    color: #374151;
}

.estado.sin-fecha {

    background: #fef3c7;

    color: #92400e;
}


/*
|--------------------------------------------------------------------------
| DETALLE
|--------------------------------------------------------------------------
*/

.detalle-recordatorio {

    color: #6b7280;

    font-size: 13px;

    margin-top: 5px;
}


/*
|--------------------------------------------------------------------------
| FECHA
|--------------------------------------------------------------------------
*/

.fecha-principal {

    font-weight: bold;
}

.fecha-secundaria {

    color: #6b7280;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| ANTICIPACIÓN
|--------------------------------------------------------------------------
*/

.anticipacion {

    font-weight: bold;
}

.anticipacion span {

    display: block;

    color: #6b7280;

    font-size: 12px;

    font-weight: normal;

    margin-top: 4px;
}


/*
|--------------------------------------------------------------------------
| VACÍO
|--------------------------------------------------------------------------
*/

.vacio {

    text-align: center;

    padding: 55px;

    color: #6b7280;
}

.vacio h3 {

    margin-bottom: 8px;

    color: #374151;
}


/*
|--------------------------------------------------------------------------
| INFORMACIÓN
|--------------------------------------------------------------------------
*/

.informacion {

    margin-top: 25px;

    background: white;

    border-radius: 12px;

    padding: 22px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.06);
}

.informacion h2 {

    margin-top: 0;

    font-size: 20px;
}

.informacion-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;
}

.info {

    padding: 15px;

    border-radius: 8px;

    background: #f9fafb;
}

.info strong {

    display: block;

    margin-bottom: 5px;
}

.info span {

    color: #6b7280;

    font-size: 13px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1050px) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .form-busqueda {

        grid-template-columns: 1fr 1fr;
    }

    .informacion-grid {

        grid-template-columns:
            1fr 1fr;
    }
}


@media (max-width: 700px) {

    .encabezado {

        flex-direction: column;

        align-items: flex-start;
    }

    .estadisticas {

        grid-template-columns: 1fr;
    }

    .form-busqueda {

        grid-template-columns: 1fr;
    }

    .informacion-grid {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<div class="contenedor">


    <!-- ==========================================================
         ENCABEZADO
         ========================================================== -->

    <div class="encabezado">

        <div class="titulo">

            <h1>⏰ Recordatorios</h1>

            <p>
                Seguimiento de temporadas y fechas para preparar materiales de Marketing.
            </p>

        </div>


        <div class="botones">

            <a
                href="dashboard.php"
                class="btn btn-secundario"
            >
                Dashboard
            </a>


            <a
                href="temporadas.php"
                class="btn btn-secundario"
            >
                Temporadas
            </a>


            <a
                href="calendario.php"
                class="btn btn-secundario"
            >
                Calendario
            </a>

        </div>

    </div>


    <!-- ==========================================================
         ALERTA DE ATENCIÓN
         ========================================================== -->

    <?php if ($requieren_atencion > 0): ?>

        <div class="alerta-atencion">

            <strong>
                ⚠️ Atención:
            </strong>

            Hay
            <strong>
                <?= $requieren_atencion ?>
            </strong>

            temporada(s) que requieren preparación de material.

            <?php if ($urgentes > 0): ?>

                De ellas,
                <strong>
                    <?= $urgentes ?>
                </strong>

                requieren atención urgente.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- ==========================================================
         ESTADÍSTICAS
         ========================================================== -->

    <div class="estadisticas">


        <div class="tarjeta">

            <div class="numero">
                <?= $requieren_atencion ?>
            </div>

            <div class="texto">
                Requieren atención
            </div>

        </div>


        <div class="tarjeta">

            <div class="numero">
                <?= $urgentes ?>
            </div>

            <div class="texto">
                Urgentes
            </div>

        </div>


        <div class="tarjeta">

            <div class="numero">
                <?= $preparar ?>
            </div>

            <div class="texto">
                Preparar material
            </div>

        </div>


        <div class="tarjeta">

            <div class="numero">
                <?= $vigentes ?>
            </div>

            <div class="texto">
                Temporadas vigentes
            </div>

        </div>

    </div>


    <!-- ==========================================================
         BUSCADOR Y FILTRO
         ========================================================== -->

    <div class="buscador">

        <form
            method="GET"
            class="form-busqueda"
        >


            <input
                type="text"
                name="buscar"
                value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar temporada..."
            >


            <select name="filtro">

                <option value="TODOS"
                    <?= $filtro === 'TODOS' ? 'selected' : '' ?>>
                    Todos los estados
                </option>

                <option value="URGENTE"
                    <?= $filtro === 'URGENTE' ? 'selected' : '' ?>>
                    Urgentes
                </option>

                <option value="PREPARAR"
                    <?= $filtro === 'PREPARAR' ? 'selected' : '' ?>>
                    Preparar material
                </option>

                <option value="EN_TIEMPO"
                    <?= $filtro === 'EN_TIEMPO' ? 'selected' : '' ?>>
                    En tiempo
                </option>

                <option value="VIGENTE"
                    <?= $filtro === 'VIGENTE' ? 'selected' : '' ?>>
                    Vigentes
                </option>

                <option value="FINALIZADA"
                    <?= $filtro === 'FINALIZADA' ? 'selected' : '' ?>>
                    Finalizadas
                </option>

                <option value="SIN_FECHA"
                    <?= $filtro === 'SIN_FECHA' ? 'selected' : '' ?>>
                    Sin fecha
                </option>

            </select>


            <button
                type="submit"
                class="btn btn-principal"
            >
                🔎 Buscar
            </button>


            <?php if (
                $buscar !== '' ||
                $filtro !== 'TODOS'
            ): ?>

                <a
                    href="recordatorios.php"
                    class="btn btn-secundario"
                >
                    Limpiar
                </a>

            <?php endif; ?>


        </form>

    </div>


    <!-- ==========================================================
         TABLA
         ========================================================== -->

    <div class="contenedor-tabla">


        <?php if (empty($temporadas)): ?>

            <div class="vacio">

                <h3>
                    No hay recordatorios para mostrar
                </h3>

                <p>

                    <?php if ($buscar !== ''): ?>

                        No se encontraron temporadas para
                        <strong>
                            <?= htmlspecialchars($buscar) ?>
                        </strong>.

                    <?php elseif ($filtro !== 'TODOS'): ?>

                        No existen temporadas con el estado seleccionado.

                    <?php else: ?>

                        No existen temporadas activas registradas.

                    <?php endif; ?>

                </p>

            </div>


        <?php else: ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            Temporada
                        </th>

                        <th>
                            Inicio
                        </th>

                        <th>
                            Fin
                        </th>

                        <th>
                            Anticipación
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Situación
                        </th>

                        <th>
                            Acción
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($temporadas as $temporada): ?>


                    <?php

                    $estado =
                        $temporada['estado_recordatorio'];

                    ?>


                    <tr>


                        <!-- TEMPORADA -->

                        <td>

                            <div class="nombre-temporada">

                                <?= htmlspecialchars(
                                    $temporada['nombre']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $temporada['descripcion']
                                )
                            ): ?>

                                <div class="descripcion">

                                    <?= htmlspecialchars(
                                        mb_strimwidth(
                                            $temporada['descripcion'],
                                            0,
                                            130,
                                            '...'
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- INICIO -->

                        <td>

                            <div class="fecha-principal">

                                <?= formato_fecha_recordatorio(
                                    $temporada['fecha_inicio']
                                ) ?>

                            </div>

                        </td>


                        <!-- FIN -->

                        <td>

                            <div class="fecha-principal">

                                <?= formato_fecha_recordatorio(
                                    $temporada['fecha_fin']
                                ) ?>

                            </div>

                        </td>


                        <!-- ANTICIPACIÓN -->

                        <td>

                            <div class="anticipacion">

                                <?= (int)$temporada[
                                    'dias_anticipacion'
                                ] ?>

                                días

                                <span>
                                    antes del inicio
                                </span>

                            </div>

                        </td>


                        <!-- ESTADO -->

                        <td>

                            <span
                                class="estado <?= htmlspecialchars(
                                    $estado['clase']
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $estado['texto']
                                ) ?>

                            </span>

                        </td>


                        <!-- SITUACIÓN -->

                        <td>

                            <div class="detalle-recordatorio">

                                <?= htmlspecialchars(
                                    $estado['detalle']
                                ) ?>

                            </div>

                        </td>


                        <!-- ACCIÓN -->

                        <td>

                            <?php if (
                                $estado['codigo'] === 'URGENTE' ||
                                $estado['codigo'] === 'PREPARAR'
                            ): ?>

                                <a
                                    href="materiales.php"
                                    class="btn btn-principal"
                                >
                                    📤 Materiales
                                </a>

                            <?php elseif (
                                $estado['codigo'] === 'VIGENTE'
                            ): ?>

                                <a
                                    href="campanas.php"
                                    class="btn btn-secundario"
                                >
                                    📢 Campañas
                                </a>

                            <?php else: ?>

                                <a
                                    href="temporadas.php"
                                    class="btn btn-secundario"
                                >
                                    Ver temporada
                                </a>

                            <?php endif; ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php endif; ?>


    </div>


    <!-- ==========================================================
         EXPLICACIÓN
         ========================================================== -->

    <div class="informacion">


        <h2>
            ¿Cómo funcionan los recordatorios?
        </h2>


        <div class="informacion-grid">


            <div class="info">

                <strong>
                    🔴 Urgente
                </strong>

                <span>
                    La temporada comienza en 7 días o menos.
                    Es recomendable revisar inmediatamente
                    los materiales necesarios.
                </span>

            </div>


            <div class="info">

                <strong>
                    🟠 Preparar material
                </strong>

                <span>
                    La temporada ya entró en el periodo
                    de preparación definido en
                    "Días de anticipación".
                </span>

            </div>


            <div class="info">

                <strong>
                    🟢 En tiempo
                </strong>

                <span>
                    Todavía no se ha llegado al periodo
                    configurado para comenzar la preparación.
                </span>

            </div>


            <div class="info">

                <strong>
                    🔵 Vigente
                </strong>

                <span>
                    La fecha actual se encuentra dentro
                    del periodo de la temporada.
                </span>

            </div>


            <div class="info">

                <strong>
                    ⚫ Finalizada
                </strong>

                <span>
                    La fecha de finalización ya pasó.
                </span>

            </div>


            <div class="info">

                <strong>
                    ⏰ Días de anticipación
                </strong>

                <span>
                    Define con cuántos días de anticipación
                    Marketing debe comenzar a preparar
                    materiales para esa temporada.
                </span>

            </div>


        </div>

    </div>


</div>

</body>

</html>