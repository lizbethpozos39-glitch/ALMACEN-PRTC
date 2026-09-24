<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_gerencia_marketing();

/*
|--------------------------------------------------------------------------
| INFORMACIÓN DEL USUARIO
|--------------------------------------------------------------------------
*/

$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());

$mensaje = "";
$error   = "";


/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_campanas'])) {
    $_SESSION['csrf_campanas'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_campanas'];


/*
|--------------------------------------------------------------------------
| FUNCIONES
|--------------------------------------------------------------------------
*/

function escapar($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| AUDITORÍA
|--------------------------------------------------------------------------
*/

function registrar_auditoria_campana(
    $conexion,
    $empleado_id,
    $accion,
    $referencia_id,
    $descripcion
) {
    $sql = "
        INSERT INTO marketing_auditoria
        (
            empleado_id,
            accion,
            modulo,
            referencia_id,
            descripcion
        )
        VALUES
        (
            ?,
            ?,
            'CAMPANAS',
            ?,
            ?
        )
    ";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isis",
        $empleado_id,
        $accion,
        $referencia_id,
        $descripcion
    );

    $resultado = $stmt->execute();

    $stmt->close();

    return $resultado;
}


/*
|--------------------------------------------------------------------------
| PROCESAR FORMULARIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CSRF
    |--------------------------------------------------------------------------
    */

    $token_recibido = $_POST['csrf'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_campanas'],
            $token_recibido
        )
    ) {
        $error = "La sesión de seguridad expiró. Recarga la página e inténtalo nuevamente.";
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR CAMPAÑA
    |--------------------------------------------------------------------------
    */

    elseif (
        isset($_POST['accion']) &&
        $_POST['accion'] === 'crear'
    ) {

        /*
        |----------------------------------------------------------------------
        | SOLO ADMIN
        |----------------------------------------------------------------------
        */

        if ($rol_usuario !== 'ADMIN') {

            $error = "No tienes permisos para crear campañas.";

        } else {

            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $objetivo = trim($_POST['objetivo'] ?? '');

            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
            $fecha_fin = trim($_POST['fecha_fin'] ?? '');

            $responsable_id = (int)($_POST['responsable_id'] ?? 0);

            $estado = strtoupper(
                trim($_POST['estado'] ?? 'BORRADOR')
            );


            /*
            |------------------------------------------------------------------
            | VALIDACIONES
            |------------------------------------------------------------------
            */

            $estados_validos = [
                'BORRADOR',
                'PENDIENTE',
                'ACTIVA',
                'FINALIZADA',
                'CANCELADA'
            ];

            if ($nombre === '') {

                $error = "El nombre de la campaña es obligatorio.";

            } elseif (
                !in_array(
                    $estado,
                    $estados_validos,
                    true
                )
            ) {

                $error = "El estado seleccionado no es válido.";

            } elseif (
                $fecha_inicio !== '' &&
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fecha_inicio
                )
            ) {

                $error = "La fecha de inicio no es válida.";

            } elseif (
                $fecha_fin !== '' &&
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fecha_fin
                )
            ) {

                $error = "La fecha de fin no es válida.";

            } elseif (
                $fecha_inicio !== '' &&
                $fecha_fin !== '' &&
                $fecha_fin < $fecha_inicio
            ) {

                $error = "La fecha de fin no puede ser anterior a la fecha de inicio.";

            } else {

                /*
                |--------------------------------------------------------------
                | INSERTAR
                |--------------------------------------------------------------
                */

                $fecha_inicio_db =
                    ($fecha_inicio !== '')
                    ? $fecha_inicio
                    : null;

                $fecha_fin_db =
                    ($fecha_fin !== '')
                    ? $fecha_fin
                    : null;

                $responsable_db =
                    ($responsable_id > 0)
                    ? $responsable_id
                    : null;


                $sql = "
                    INSERT INTO marketing_campanas
                    (
                        nombre,
                        descripcion,
                        objetivo,
                        fecha_inicio,
                        fecha_fin,
                        estado,
                        responsable_id
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ";

                $stmt = $conexion->prepare($sql);

                if (!$stmt) {

                    $error =
                        "Error al preparar la creación de la campaña: "
                        . $conexion->error;

                } else {

                    $stmt->bind_param(
                        "ssssssi",
                        $nombre,
                        $descripcion,
                        $objetivo,
                        $fecha_inicio_db,
                        $fecha_fin_db,
                        $estado,
                        $responsable_db
                    );

                    if ($stmt->execute()) {

                        $campana_id =
                            (int)$conexion->insert_id;

                        registrar_auditoria_campana(
                            $conexion,
                            $empleado_id,
                            'CREAR_CAMPANA',
                            $campana_id,
                            'Se creó la campaña: ' . $nombre
                        );

                        $mensaje =
                            "La campaña se creó correctamente.";

                    } else {

                        $error =
                            "No se pudo crear la campaña: "
                            . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDITAR CAMPAÑA
    |--------------------------------------------------------------------------
    */

    elseif (
        isset($_POST['accion']) &&
        $_POST['accion'] === 'editar'
    ) {

        if ($rol_usuario !== 'ADMIN') {

            $error = "No tienes permisos para editar campañas.";

        } else {

            $campana_id =
                (int)($_POST['campana_id'] ?? 0);

            $nombre =
                trim($_POST['nombre'] ?? '');

            $descripcion =
                trim($_POST['descripcion'] ?? '');

            $objetivo =
                trim($_POST['objetivo'] ?? '');

            $fecha_inicio =
                trim($_POST['fecha_inicio'] ?? '');

            $fecha_fin =
                trim($_POST['fecha_fin'] ?? '');

            $responsable_id =
                (int)($_POST['responsable_id'] ?? 0);

            $estado =
                strtoupper(
                    trim($_POST['estado'] ?? 'BORRADOR')
                );


            $estados_validos = [
                'BORRADOR',
                'PENDIENTE',
                'ACTIVA',
                'FINALIZADA',
                'CANCELADA'
            ];


            if ($campana_id <= 0) {

                $error = "Campaña no válida.";

            } elseif ($nombre === '') {

                $error = "El nombre de la campaña es obligatorio.";

            } elseif (
                !in_array(
                    $estado,
                    $estados_validos,
                    true
                )
            ) {

                $error = "El estado seleccionado no es válido.";

            } elseif (
                $fecha_inicio !== '' &&
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fecha_inicio
                )
            ) {

                $error = "La fecha de inicio no es válida.";

            } elseif (
                $fecha_fin !== '' &&
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fecha_fin
                )
            ) {

                $error = "La fecha de fin no es válida.";

            } elseif (
                $fecha_inicio !== '' &&
                $fecha_fin !== '' &&
                $fecha_fin < $fecha_inicio
            ) {

                $error =
                    "La fecha de fin no puede ser anterior a la fecha de inicio.";

            } else {

                $fecha_inicio_db =
                    ($fecha_inicio !== '')
                    ? $fecha_inicio
                    : null;

                $fecha_fin_db =
                    ($fecha_fin !== '')
                    ? $fecha_fin
                    : null;

                $responsable_db =
                    ($responsable_id > 0)
                    ? $responsable_id
                    : null;


                /*
                |--------------------------------------------------------------
                | OBTENER NOMBRE ANTERIOR PARA AUDITORÍA
                |--------------------------------------------------------------
                */

                $nombre_anterior = '';

                $stmtAnterior = $conexion->prepare("
                    SELECT nombre
                    FROM marketing_campanas
                    WHERE id = ?
                    LIMIT 1
                ");

                if ($stmtAnterior) {

                    $stmtAnterior->bind_param(
                        "i",
                        $campana_id
                    );

                    $stmtAnterior->execute();

                    $resultadoAnterior =
                        $stmtAnterior->get_result();

                    if ($filaAnterior =
                        $resultadoAnterior->fetch_assoc()
                    ) {
                        $nombre_anterior =
                            $filaAnterior['nombre'];
                    }

                    $stmtAnterior->close();
                }


                /*
                |--------------------------------------------------------------
                | ACTUALIZAR
                |--------------------------------------------------------------
                */

                $sql = "
                    UPDATE marketing_campanas
                    SET
                        nombre = ?,
                        descripcion = ?,
                        objetivo = ?,
                        fecha_inicio = ?,
                        fecha_fin = ?,
                        estado = ?,
                        responsable_id = ?
                    WHERE id = ?
                    LIMIT 1
                ";

                $stmt =
                    $conexion->prepare($sql);

                if (!$stmt) {

                    $error =
                        "Error al preparar la actualización: "
                        . $conexion->error;

                } else {

                    $stmt->bind_param(
                        "ssssssii",
                        $nombre,
                        $descripcion,
                        $objetivo,
                        $fecha_inicio_db,
                        $fecha_fin_db,
                        $estado,
                        $responsable_db,
                        $campana_id
                    );

                    if ($stmt->execute()) {

                        registrar_auditoria_campana(
                            $conexion,
                            $empleado_id,
                            'EDITAR_CAMPANA',
                            $campana_id,
                            'Se editó la campaña: '
                            . $nombre
                            . (
                                $nombre_anterior !== ''
                                ? ' | Nombre anterior: '
                                . $nombre_anterior
                                : ''
                            )
                        );

                        $mensaje =
                            "La campaña se actualizó correctamente.";

                    } else {

                        $error =
                            "No se pudo actualizar la campaña: "
                            . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CAMBIAR ESTADO
    |--------------------------------------------------------------------------
    */

    elseif (
        isset($_POST['accion']) &&
        $_POST['accion'] === 'cambiar_estado'
    ) {

        if ($rol_usuario !== 'ADMIN') {

            $error =
                "No tienes permisos para cambiar el estado de campañas.";

        } else {

            $campana_id =
                (int)($_POST['campana_id'] ?? 0);

            $nuevo_estado =
                strtoupper(
                    trim($_POST['nuevo_estado'] ?? '')
                );


            $estados_validos = [
                'BORRADOR',
                'PENDIENTE',
                'ACTIVA',
                'FINALIZADA',
                'CANCELADA'
            ];


            if ($campana_id <= 0) {

                $error = "Campaña no válida.";

            } elseif (
                !in_array(
                    $nuevo_estado,
                    $estados_validos,
                    true
                )
            ) {

                $error = "Estado no válido.";

            } else {

                /*
                |--------------------------------------------------------------
                | OBTENER ESTADO ACTUAL
                |--------------------------------------------------------------
                */

                $estado_anterior = '';

                $stmtActual =
                    $conexion->prepare("
                        SELECT estado, nombre
                        FROM marketing_campanas
                        WHERE id = ?
                        LIMIT 1
                    ");

                if ($stmtActual) {

                    $stmtActual->bind_param(
                        "i",
                        $campana_id
                    );

                    $stmtActual->execute();

                    $resultadoActual =
                        $stmtActual->get_result();

                    if ($filaActual =
                        $resultadoActual->fetch_assoc()
                    ) {

                        $estado_anterior =
                            $filaActual['estado'];

                        $nombre_campana =
                            $filaActual['nombre'];

                    } else {

                        $nombre_campana = '';
                    }

                    $stmtActual->close();

                } else {

                    $nombre_campana = '';
                }


                if ($estado_anterior === '') {

                    $error =
                        "La campaña no existe.";

                } elseif (
                    $estado_anterior === $nuevo_estado
                ) {

                    $error =
                        "La campaña ya tiene ese estado.";

                } else {

                    /*
                    |----------------------------------------------------------
                    | ACTUALIZAR ESTADO
                    |----------------------------------------------------------
                    */

                    $stmtEstado =
                        $conexion->prepare("
                            UPDATE marketing_campanas
                            SET estado = ?
                            WHERE id = ?
                            LIMIT 1
                        ");

                    if (!$stmtEstado) {

                        $error =
                            "Error al preparar el cambio de estado: "
                            . $conexion->error;

                    } else {

                        $stmtEstado->bind_param(
                            "si",
                            $nuevo_estado,
                            $campana_id
                        );

                        if ($stmtEstado->execute()) {

                            registrar_auditoria_campana(
                                $conexion,
                                $empleado_id,
                                'CAMBIAR_ESTADO_CAMPANA',
                                $campana_id,
                                'La campaña "'
                                . $nombre_campana
                                . '" cambió de estado de '
                                . $estado_anterior
                                . ' a '
                                . $nuevo_estado
                            );

                            $mensaje =
                                "El estado de la campaña se actualizó correctamente.";

                        } else {

                            $error =
                                "No se pudo cambiar el estado: "
                                . $stmtEstado->error;
                        }

                        $stmtEstado->close();
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER CAMPAÑA PARA EDITAR
|--------------------------------------------------------------------------
*/

$campana_editar = null;

if (
    isset($_GET['editar']) &&
    (int)$_GET['editar'] > 0
) {

    $id_editar =
        (int)$_GET['editar'];

    $stmtEditar =
        $conexion->prepare("
            SELECT
                id,
                nombre,
                descripcion,
                objetivo,
                fecha_inicio,
                fecha_fin,
                estado,
                responsable_id,
                fecha_registro,
                fecha_actualizacion
            FROM marketing_campanas
            WHERE id = ?
            LIMIT 1
        ");

    if ($stmtEditar) {

        $stmtEditar->bind_param(
            "i",
            $id_editar
        );

        $stmtEditar->execute();

        $resultadoEditar =
            $stmtEditar->get_result();

        $campana_editar =
            $resultadoEditar->fetch_assoc();

        $stmtEditar->close();
    }
}


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

$busqueda =
    trim($_GET['buscar'] ?? '');


/*
|--------------------------------------------------------------------------
| CONSULTAR CAMPAÑAS
|--------------------------------------------------------------------------
|
| Se utiliza subconsulta para contar materiales.
| No hacemos JOIN con usuarios ni empleados.
|--------------------------------------------------------------------------
*/

if ($busqueda !== '') {

    $like =
        '%' . $busqueda . '%';

    $sqlCampanas = "
        SELECT
            c.id,
            c.nombre,
            c.descripcion,
            c.objetivo,
            c.fecha_inicio,
            c.fecha_fin,
            c.estado,
            c.responsable_id,
            c.fecha_registro,
            c.fecha_actualizacion,

            (
                SELECT COUNT(*)
                FROM marketing_materiales m
                WHERE m.campana_id = c.id
            ) AS total_materiales

        FROM marketing_campanas c

        WHERE
            c.nombre LIKE ?
            OR c.descripcion LIKE ?
            OR c.objetivo LIKE ?

        ORDER BY
            c.fecha_registro DESC
    ";

    $stmtCampanas =
        $conexion->prepare($sqlCampanas);

    $stmtCampanas->bind_param(
        "sss",
        $like,
        $like,
        $like
    );

    $stmtCampanas->execute();

    $resultadoCampanas =
        $stmtCampanas->get_result();

} else {

    $sqlCampanas = "
        SELECT
            c.id,
            c.nombre,
            c.descripcion,
            c.objetivo,
            c.fecha_inicio,
            c.fecha_fin,
            c.estado,
            c.responsable_id,
            c.fecha_registro,
            c.fecha_actualizacion,

            (
                SELECT COUNT(*)
                FROM marketing_materiales m
                WHERE m.campana_id = c.id
            ) AS total_materiales

        FROM marketing_campanas c

        ORDER BY
            c.fecha_registro DESC
    ";

    $resultadoCampanas =
        $conexion->query($sqlCampanas);
}


/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$total_campanas = 0;
$total_activas = 0;
$total_borradores = 0;
$total_pendientes = 0;

$campanas = [];

if ($resultadoCampanas) {

    while ($fila =
        $resultadoCampanas->fetch_assoc()
    ) {

        $campanas[] = $fila;

        $total_campanas++;

        if ($fila['estado'] === 'ACTIVA') {
            $total_activas++;
        }

        if ($fila['estado'] === 'BORRADOR') {
            $total_borradores++;
        }

        if ($fila['estado'] === 'PENDIENTE') {
            $total_pendientes++;
        }
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA ESTADO
|--------------------------------------------------------------------------
*/

function clase_estado($estado)
{
    switch ($estado) {

        case 'ACTIVA':
            return 'estado-activa';

        case 'PENDIENTE':
            return 'estado-pendiente';

        case 'FINALIZADA':
            return 'estado-finalizada';

        case 'CANCELADA':
            return 'estado-cancelada';

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

    <title>Campañas - Marketing</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #222;
        }


        /*
        |--------------------------------------------------------------------------
        | CONTENEDOR
        |--------------------------------------------------------------------------
        */

        .contenedor {
            width: 94%;
            max-width: 1450px;
            margin: 30px auto 50px;
        }


        /*
        |--------------------------------------------------------------------------
        | ENCABEZADO
        |--------------------------------------------------------------------------
        */

        .encabezado {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,.07);

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .encabezado h1 {
            margin: 0 0 6px;
            font-size: 27px;
        }

        .encabezado p {
            margin: 0;
            color: #777;
        }


        /*
        |--------------------------------------------------------------------------
        | BOTONES
        |--------------------------------------------------------------------------
        */

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 10px 15px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-principal {
            background: #1f6feb;
            color: white;
        }

        .btn-principal:hover {
            background: #155dcc;
        }

        .btn-editar {
            background: #f0f2f5;
            color: #333;
        }

        .btn-editar:hover {
            background: #e1e4e8;
        }

        .btn-ver {
            background: #e9f3ff;
            color: #1769aa;
        }

        .btn-cancelar {
            background: #e5e7eb;
            color: #333;
        }

        .btn-danger {
            background: #fde8e8;
            color: #b42318;
        }


        /*
        |--------------------------------------------------------------------------
        | MENSAJES
        |--------------------------------------------------------------------------
        */

        .mensaje {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-weight: bold;
        }

        .mensaje-ok {
            background: #e8f7ed;
            color: #176b36;
            border: 1px solid #b7e4c7;
        }

        .mensaje-error {
            background: #fdecec;
            color: #a61b1b;
            border: 1px solid #f3b5b5;
        }


        /*
        |--------------------------------------------------------------------------
        | TARJETAS DE INDICADORES
        |--------------------------------------------------------------------------
        */

        .indicadores {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 15px;
            margin-bottom: 20px;
        }

        .indicador {
            background: white;
            border-radius: 12px;
            padding: 20px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.06);
        }

        .indicador-titulo {
            color: #777;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .indicador-numero {
            font-size: 30px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | FORMULARIO
        |--------------------------------------------------------------------------
        */

        .panel {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.06);
        }

        .panel h2 {
            margin-top: 0;
        }

        .grid-form {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 16px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .campo-completo {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: bold;
            color: #444;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d5d9df;
            border-radius: 7px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            outline: none;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #1f6feb;
        }

        textarea {
            min-height: 95px;
            resize: vertical;
        }

        .ayuda {
            font-size: 12px;
            color: #777;
        }

        .acciones-form {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }


        /*
        |--------------------------------------------------------------------------
        | BÚSQUEDA
        |--------------------------------------------------------------------------
        */

        .busqueda {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .busqueda input {
            flex: 1;
        }


        /*
        |--------------------------------------------------------------------------
        | TABLA
        |--------------------------------------------------------------------------
        */

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #f7f8fa;
            text-align: left;
            font-size: 12px;
            color: #666;
            padding: 13px 12px;
            border-bottom: 1px solid #e4e6e9;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf0f2;
            vertical-align: middle;
            font-size: 14px;
        }

        tr:hover td {
            background: #fafbfc;
        }

        .nombre-campana {
            font-weight: bold;
            color: #222;
        }

        .descripcion-tabla {
            max-width: 280px;
            color: #666;
            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | ESTADOS
        |--------------------------------------------------------------------------
        */

        .estado {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .estado-borrador {
            background: #eef0f3;
            color: #555;
        }

        .estado-pendiente {
            background: #fff4d6;
            color: #8a5a00;
        }

        .estado-activa {
            background: #e7f7ed;
            color: #176b36;
        }

        .estado-finalizada {
            background: #e8eefc;
            color: #315aa8;
        }

        .estado-cancelada {
            background: #fde8e8;
            color: #a61b1b;
        }


        /*
        |--------------------------------------------------------------------------
        | MATERIAL
        |--------------------------------------------------------------------------
        */

        .contador-materiales {
            display: inline-block;
            min-width: 30px;
            text-align: center;
            padding: 5px 8px;
            border-radius: 15px;
            background: #eef3ff;
            color: #315aa8;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | ACCIONES
        |--------------------------------------------------------------------------
        */

        .acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .acciones .btn {
            padding: 7px 10px;
            font-size: 12px;
        }

        .form-estado {
            display: inline-flex;
            gap: 5px;
        }

        .form-estado select {
            width: auto;
            padding: 7px;
            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | SIN DATOS
        |--------------------------------------------------------------------------
        */

        .sin-datos {
            text-align: center;
            padding: 45px 20px;
            color: #777;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .indicadores {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .grid-form {
                grid-template-columns: 1fr;
            }

            .campo-completo {
                grid-column: auto;
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }
        }


        @media (max-width: 600px) {

            .contenedor {
                width: 96%;
                margin-top: 15px;
            }

            .indicadores {
                grid-template-columns: 1fr;
            }

            .busqueda {
                flex-direction: column;
            }

            .acciones-form {
                flex-direction: column;
            }
        }

    </style>

</head>


<body>

<div class="contenedor">


    <!--
    |--------------------------------------------------------------------------
    | ENCABEZADO
    |--------------------------------------------------------------------------
    -->

    <div class="encabezado">

        <div>

            <h1>📅 Campañas de Marketing</h1>

            <p>
                Administración y seguimiento de campañas
                de contenido.
            </p>

        </div>


        <?php if ($rol_usuario === 'ADMIN'): ?>

            <a
                href="#form-campana"
                class="btn btn-principal"
            >
                + Nueva campaña
            </a>

        <?php endif; ?>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | MENSAJES
    |--------------------------------------------------------------------------
    -->

    <?php if ($mensaje !== ''): ?>

        <div class="mensaje mensaje-ok">
            ✓ <?= escapar($mensaje) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="mensaje mensaje-error">
            ⚠ <?= escapar($error) ?>
        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | INDICADORES
    |--------------------------------------------------------------------------
    -->

    <div class="indicadores">

        <div class="indicador">

            <div class="indicador-titulo">
                TOTAL DE CAMPAÑAS
            </div>

            <div class="indicador-numero">
                <?= $total_campanas ?>
            </div>

        </div>


        <div class="indicador">

            <div class="indicador-titulo">
                CAMPAÑAS ACTIVAS
            </div>

            <div class="indicador-numero">
                <?= $total_activas ?>
            </div>

        </div>


        <div class="indicador">

            <div class="indicador-titulo">
                BORRADORES
            </div>

            <div class="indicador-numero">
                <?= $total_borradores ?>
            </div>

        </div>


        <div class="indicador">

            <div class="indicador-titulo">
                PENDIENTES
            </div>

            <div class="indicador-numero">
                <?= $total_pendientes ?>
            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | FORMULARIO CREAR / EDITAR
    |--------------------------------------------------------------------------
    -->

    <?php if ($rol_usuario === 'ADMIN'): ?>

        <div
            class="panel"
            id="form-campana"
        >

            <?php if ($campana_editar): ?>

                <h2>
                    ✏️ Editar campaña
                </h2>

                <p class="ayuda">
                    Modifica la información de la campaña seleccionada.
                </p>

                <form
                    method="POST"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= escapar($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="editar"
                    >

                    <input
                        type="hidden"
                        name="campana_id"
                        value="<?= (int)$campana_editar['id'] ?>"
                    >


                    <div class="grid-form">


                        <div class="campo campo-completo">

                            <label>
                                Nombre de la campaña *
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                maxlength="200"
                                required
                                value="<?= escapar(
                                    $campana_editar['nombre']
                                ) ?>"
                            >

                        </div>


                        <div class="campo campo-completo">

                            <label>
                                Descripción
                            </label>

                            <textarea
                                name="descripcion"
                            ><?= escapar(
                                $campana_editar['descripcion']
                            ) ?></textarea>

                        </div>


                        <div class="campo campo-completo">

                            <label>
                                Objetivo
                            </label>

                            <textarea
                                name="objetivo"
                            ><?= escapar(
                                $campana_editar['objetivo']
                            ) ?></textarea>

                        </div>


                        <div class="campo">

                            <label>
                                Fecha de inicio
                            </label>

                            <input
                                type="date"
                                name="fecha_inicio"
                                value="<?= escapar(
                                    $campana_editar['fecha_inicio']
                                ) ?>"
                            >

                        </div>


                        <div class="campo">

                            <label>
                                Fecha de fin
                            </label>

                            <input
                                type="date"
                                name="fecha_fin"
                                value="<?= escapar(
                                    $campana_editar['fecha_fin']
                                ) ?>"
                            >

                        </div>


                        <div class="campo">

                            <label>
                                Responsable
                            </label>

                            <input
                                type="number"
                                name="responsable_id"
                                min="0"
                                value="<?= (int)(
                                    $campana_editar['responsable_id'] ?? 0
                                ) ?>"
                            >

                            <span class="ayuda">
                                ID del empleado responsable.
                            </span>

                        </div>


                        <div class="campo">

                            <label>
                                Estado
                            </label>

                            <select name="estado">

                                <?php
                                $estados = [
                                    'BORRADOR',
                                    'PENDIENTE',
                                    'ACTIVA',
                                    'FINALIZADA',
                                    'CANCELADA'
                                ];
                                ?>

                                <?php foreach (
                                    $estados as $estado
                                ): ?>

                                    <option
                                        value="<?= $estado ?>"
                                        <?= (
                                            $campana_editar['estado']
                                            === $estado
                                        )
                                        ? 'selected'
                                        : ''
                                        ?>
                                    >
                                        <?= $estado ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="acciones-form">

                            <button
                                type="submit"
                                class="btn btn-principal"
                            >
                                Guardar cambios
                            </button>


                            <a
                                href="campanas.php"
                                class="btn btn-cancelar"
                            >
                                Cancelar
                            </a>

                        </div>

                    </div>

                </form>


            <?php else: ?>


                <h2>
                    ➕ Nueva campaña
                </h2>

                <p class="ayuda">
                    Registra una nueva campaña de Marketing.
                </p>


                <form
                    method="POST"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= escapar($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="crear"
                    >


                    <div class="grid-form">


                        <div class="campo campo-completo">

                            <label>
                                Nombre de la campaña *
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                maxlength="200"
                                required
                                placeholder="Ej. Campaña Navidad 2026"
                            >

                        </div>


                        <div class="campo campo-completo">

                            <label>
                                Descripción
                            </label>

                            <textarea
                                name="descripcion"
                                placeholder="Descripción general de la campaña..."
                            ></textarea>

                        </div>


                        <div class="campo campo-completo">

                            <label>
                                Objetivo
                            </label>

                            <textarea
                                name="objetivo"
                                placeholder="¿Qué se busca lograr con esta campaña?"
                            ></textarea>

                        </div>


                        <div class="campo">

                            <label>
                                Fecha de inicio
                            </label>

                            <input
                                type="date"
                                name="fecha_inicio"
                            >

                        </div>


                        <div class="campo">

                            <label>
                                Fecha de fin
                            </label>

                            <input
                                type="date"
                                name="fecha_fin"
                            >

                        </div>


                        <div class="campo">

                            <label>
                                Responsable
                            </label>

                            <input
                                type="number"
                                name="responsable_id"
                                min="0"
                                value="<?= $empleado_id ?>"
                            >

                            <span class="ayuda">
                                ID del empleado responsable.
                            </span>

                        </div>


                        <div class="campo">

                            <label>
                                Estado
                            </label>

                            <select name="estado">

                                <option value="BORRADOR">
                                    BORRADOR
                                </option>

                                <option value="PENDIENTE">
                                    PENDIENTE
                                </option>

                                <option value="ACTIVA">
                                    ACTIVA
                                </option>

                                <option value="FINALIZADA">
                                    FINALIZADA
                                </option>

                                <option value="CANCELADA">
                                    CANCELADA
                                </option>

                            </select>

                        </div>


                        <div class="acciones-form">

                            <button
                                type="submit"
                                class="btn btn-principal"
                            >
                                Guardar campaña
                            </button>

                        </div>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | BÚSQUEDA
    |--------------------------------------------------------------------------
    -->

    <div class="panel">

        <form
            method="GET"
            class="busqueda"
        >

            <input
                type="text"
                name="buscar"
                placeholder="🔎 Buscar campaña por nombre, descripción u objetivo..."
                value="<?= escapar($busqueda) ?>"
            >

            <button
                type="submit"
                class="btn btn-principal"
            >
                Buscar
            </button>

            <?php if ($busqueda !== ''): ?>

                <a
                    href="campanas.php"
                    class="btn btn-cancelar"
                >
                    Limpiar
                </a>

            <?php endif; ?>

        </form>


        <!--
        |--------------------------------------------------------------------------
        | TABLA
        |--------------------------------------------------------------------------
        -->

        <div class="tabla-contenedor">

            <?php if (count($campanas) > 0): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                CAMPAÑA
                            </th>

                            <th>
                                PERIODO
                            </th>

                            <th>
                                ESTADO
                            </th>

                            <th>
                                MATERIALES
                            </th>

                            <th>
                                RESPONSABLE
                            </th>

                            <th>
                                REGISTRO
                            </th>

                            <?php if (
                                $rol_usuario === 'ADMIN'
                            ): ?>

                                <th>
                                    ACCIONES
                                </th>

                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $campanas as $campana
                        ): ?>

                            <tr>


                                <!--
                                ------------------------------------------------
                                CAMPAÑA
                                ------------------------------------------------
                                -->

                                <td>

                                    <div class="nombre-campana">

                                        <?= escapar(
                                            $campana['nombre']
                                        ) ?>

                                    </div>


                                    <?php if (
                                        trim(
                                            $campana['descripcion'] ?? ''
                                        ) !== ''
                                    ): ?>

                                        <div
                                            class="descripcion-tabla"
                                        >

                                            <?= escapar(
                                                mb_strimwidth(
                                                    $campana['descripcion'],
                                                    0,
                                                    100,
                                                    '...'
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!--
                                ------------------------------------------------
                                PERIODO
                                ------------------------------------------------
                                -->

                                <td>

                                    <?php if (
                                        $campana['fecha_inicio']
                                    ): ?>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $campana['fecha_inicio']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>


                                    <br>


                                    <span
                                        style="color:#777;font-size:12px;"
                                    >

                                        hasta

                                    </span>


                                    <br>


                                    <?php if (
                                        $campana['fecha_fin']
                                    ): ?>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $campana['fecha_fin']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!--
                                ------------------------------------------------
                                ESTADO
                                ------------------------------------------------
                                -->

                                <td>

                                    <span
                                        class="estado <?= clase_estado(
                                            $campana['estado']
                                        ) ?>"
                                    >

                                        <?= escapar(
                                            $campana['estado']
                                        ) ?>

                                    </span>

                                </td>


                                <!--
                                ------------------------------------------------
                                MATERIALES
                                ------------------------------------------------
                                -->

                                <td>

                                    <span
                                        class="contador-materiales"
                                    >

                                        <?= (int)(
                                            $campana['total_materiales']
                                        ) ?>

                                    </span>

                                </td>


                                <!--
                                ------------------------------------------------
                                RESPONSABLE
                                ------------------------------------------------
                                -->

                                <td>

                                    <?php if (
                                        (int)$campana['responsable_id'] > 0
                                    ): ?>

                                        Empleado #<?= (int)(
                                            $campana['responsable_id']
                                        ) ?>

                                    <?php else: ?>

                                        <span
                                            style="color:#999;"
                                        >
                                            Sin asignar
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!--
                                ------------------------------------------------
                                FECHA REGISTRO
                                ------------------------------------------------
                                -->

                                <td>

                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $campana['fecha_registro']
                                        )
                                    ) ?>

                                </td>


                                <!--
                                ------------------------------------------------
                                ACCIONES
                                ------------------------------------------------
                                -->

                                <?php if (
                                    $rol_usuario === 'ADMIN'
                                ): ?>

                                    <td>

                                        <div class="acciones">


                                            <!-- EDITAR -->

                                            <a
                                                href="campanas.php?editar=<?= (int)$campana['id'] ?>#form-campana"
                                                class="btn btn-editar"
                                            >
                                                ✏ Editar
                                            </a>


                                            <!-- CAMBIAR ESTADO -->

                                            <form
                                                method="POST"
                                                class="form-estado"
                                                onsubmit="return confirmarEstado(this);"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf"
                                                    value="<?= escapar($csrf) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="accion"
                                                    value="cambiar_estado"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="campana_id"
                                                    value="<?= (int)$campana['id'] ?>"
                                                >


                                                <select
                                                    name="nuevo_estado"
                                                    aria-label="Cambiar estado"
                                                >

                                                    <option value="">
                                                        Estado...
                                                    </option>

                                                    <?php foreach (
                                                        [
                                                            'BORRADOR',
                                                            'PENDIENTE',
                                                            'ACTIVA',
                                                            'FINALIZADA',
                                                            'CANCELADA'
                                                        ]
                                                        as $estado
                                                    ): ?>

                                                        <?php if (
                                                            $estado
                                                            !== $campana['estado']
                                                        ): ?>

                                                            <option
                                                                value="<?= $estado ?>"
                                                            >
                                                                <?= $estado ?>
                                                            </option>

                                                        <?php endif; ?>

                                                    <?php endforeach; ?>

                                                </select>


                                                <button
                                                    type="submit"
                                                    class="btn btn-ver"
                                                >
                                                    Cambiar
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                <?php endif; ?>


                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>


            <?php else: ?>

                <div class="sin-datos">

                    <div
                        style="
                            font-size:40px;
                            margin-bottom:10px;
                        "
                    >
                        📅
                    </div>

                    <strong>
                        No hay campañas registradas.
                    </strong>

                    <p>
                        <?php if ($busqueda !== ''): ?>

                            No se encontraron campañas
                            con el criterio de búsqueda.

                        <?php else: ?>

                            Puedes comenzar creando
                            la primera campaña.

                        <?php endif; ?>
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| CONFIRMAR CAMBIO DE ESTADO
|--------------------------------------------------------------------------
*/

function confirmarEstado(formulario)
{
    const select =
        formulario.querySelector(
            'select[name="nuevo_estado"]'
        );

    if (!select.value) {

        alert(
            'Selecciona el nuevo estado de la campaña.'
        );

        return false;
    }


    return confirm(
        '¿Deseas cambiar el estado de esta campaña a "' +
        select.value +
        '"?'
    );
}


/*
|--------------------------------------------------------------------------
| OCULTAR MENSAJES
|--------------------------------------------------------------------------
*/

setTimeout(function() {

    const mensajes =
        document.querySelectorAll(
            '.mensaje'
        );

    mensajes.forEach(function(elemento) {

        elemento.style.transition =
            'opacity .4s';

        elemento.style.opacity = '0';

        setTimeout(function() {

            elemento.remove();

        }, 400);

    });

}, 5000);

</script>


</body>

</html>