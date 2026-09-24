<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_admin_marketing();

$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());

$mensaje = "";
$error   = "";


/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA AUDITORÍA
|--------------------------------------------------------------------------
*/

function registrar_auditoria_categoria(
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
        (?, ?, 'CATEGORIAS', ?, ?)
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
| CREAR CATEGORÍA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['crear_categoria'])
) {

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {

        $error = "Debes ingresar el nombre de la categoría.";

    } elseif (mb_strlen($nombre) > 100) {

        $error = "El nombre de la categoría no puede superar los 100 caracteres.";

    } else {

        /*
        |--------------------------------------------------------------
        | VERIFICAR DUPLICADO
        |--------------------------------------------------------------
        */

        $stmt = $conexion->prepare("
            SELECT id
            FROM marketing_categorias
            WHERE LOWER(nombre) = LOWER(?)
            LIMIT 1
        ");

        $stmt->bind_param("s", $nombre);

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $error = "Ya existe una categoría con ese nombre.";

        } else {

            /*
            |----------------------------------------------------------
            | INSERTAR
            |----------------------------------------------------------
            */

            $stmtInsert = $conexion->prepare("
                INSERT INTO marketing_categorias
                (
                    nombre,
                    descripcion,
                    activo
                )
                VALUES
                (?, ?, 1)
            ");

            if (!$stmtInsert) {

                $error = "No fue posible preparar el registro.";

            } else {

                $stmtInsert->bind_param(
                    "ss",
                    $nombre,
                    $descripcion
                );

                if ($stmtInsert->execute()) {

                    $categoria_id = $conexion->insert_id;

                    registrar_auditoria_categoria(
                        $conexion,
                        $empleado_id,
                        'CREAR_CATEGORIA',
                        $categoria_id,
                        'Se creó la categoría: ' . $nombre
                    );

                    $mensaje = "Categoría creada correctamente.";

                } else {

                    $error = "No fue posible crear la categoría.";

                }

                $stmtInsert->close();
            }
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| EDITAR CATEGORÍA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['editar_categoria'])
) {

    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($categoria_id <= 0) {

        $error = "Categoría no válida.";

    } elseif ($nombre === '') {

        $error = "Debes ingresar el nombre de la categoría.";

    } elseif (mb_strlen($nombre) > 100) {

        $error = "El nombre de la categoría no puede superar los 100 caracteres.";

    } else {

        /*
        |--------------------------------------------------------------
        | VERIFICAR QUE NO EXISTA OTRA IGUAL
        |--------------------------------------------------------------
        */

        $stmt = $conexion->prepare("
            SELECT id
            FROM marketing_categorias
            WHERE LOWER(nombre) = LOWER(?)
              AND id <> ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $nombre,
            $categoria_id
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $error = "Ya existe otra categoría con ese nombre.";

        } else {

            /*
            |----------------------------------------------------------
            | OBTENER NOMBRE ANTERIOR
            |----------------------------------------------------------
            */

            $stmtAnterior = $conexion->prepare("
                SELECT nombre
                FROM marketing_categorias
                WHERE id = ?
                LIMIT 1
            ");

            $stmtAnterior->bind_param(
                "i",
                $categoria_id
            );

            $stmtAnterior->execute();

            $resultadoAnterior = $stmtAnterior->get_result();

            $nombre_anterior = '';

            if ($filaAnterior = $resultadoAnterior->fetch_assoc()) {
                $nombre_anterior = $filaAnterior['nombre'];
            }

            $stmtAnterior->close();


            /*
            |----------------------------------------------------------
            | ACTUALIZAR
            |----------------------------------------------------------
            */

            $stmtUpdate = $conexion->prepare("
                UPDATE marketing_categorias
                SET
                    nombre = ?,
                    descripcion = ?
                WHERE id = ?
            ");

            if (!$stmtUpdate) {

                $error = "No fue posible preparar la actualización.";

            } else {

                $stmtUpdate->bind_param(
                    "ssi",
                    $nombre,
                    $descripcion,
                    $categoria_id
                );

                if ($stmtUpdate->execute()) {

                    registrar_auditoria_categoria(
                        $conexion,
                        $empleado_id,
                        'EDITAR_CATEGORIA',
                        $categoria_id,
                        'Se modificó la categoría de "' .
                        $nombre_anterior .
                        '" a "' .
                        $nombre .
                        '"'
                    );

                    $mensaje = "Categoría actualizada correctamente.";

                } else {

                    $error = "No fue posible actualizar la categoría.";

                }

                $stmtUpdate->close();
            }
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| CAMBIAR ESTADO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cambiar_estado'])
) {

    $categoria_id = (int)($_POST['categoria_id'] ?? 0);

    if ($categoria_id <= 0) {

        $error = "Categoría no válida.";

    } else {

        /*
        |--------------------------------------------------------------
        | OBTENER DATOS ACTUALES
        |--------------------------------------------------------------
        */

        $stmt = $conexion->prepare("
            SELECT
                nombre,
                activo
            FROM marketing_categorias
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $categoria_id
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if (!$fila = $resultado->fetch_assoc()) {

            $error = "La categoría no existe.";

        } else {

            $nombre = $fila['nombre'];
            $estado_actual = (int)$fila['activo'];

            $nuevo_estado = $estado_actual === 1 ? 0 : 1;

            /*
            |----------------------------------------------------------
            | DESACTIVAR / ACTIVAR
            |----------------------------------------------------------
            */

            $stmtUpdate = $conexion->prepare("
                UPDATE marketing_categorias
                SET activo = ?
                WHERE id = ?
            ");

            $stmtUpdate->bind_param(
                "ii",
                $nuevo_estado,
                $categoria_id
            );

            if ($stmtUpdate->execute()) {

                $accion = $nuevo_estado === 1
                    ? 'ACTIVAR_CATEGORIA'
                    : 'DESACTIVAR_CATEGORIA';

                $texto = $nuevo_estado === 1
                    ? 'Se activó la categoría: '
                    : 'Se desactivó la categoría: ';

                registrar_auditoria_categoria(
                    $conexion,
                    $empleado_id,
                    $accion,
                    $categoria_id,
                    $texto . $nombre
                );

                $mensaje = $nuevo_estado === 1
                    ? "Categoría activada correctamente."
                    : "Categoría desactivada correctamente.";

            } else {

                $error = "No fue posible cambiar el estado.";

            }

            $stmtUpdate->close();
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| BUSCADOR
|--------------------------------------------------------------------------
*/

$busqueda = trim($_GET['buscar'] ?? '');

$categorias = [];


/*
|--------------------------------------------------------------------------
| CONSULTA DE CATEGORÍAS
|--------------------------------------------------------------------------
*/

if ($busqueda !== '') {

    $sql = "
        SELECT
            c.id,
            c.nombre,
            c.descripcion,
            c.activo,
            c.fecha_registro,
            COUNT(m.id) AS total_materiales
        FROM marketing_categorias c

        LEFT JOIN marketing_materiales m
            ON m.categoria_id = c.id

        WHERE
            c.nombre LIKE ?
            OR c.descripcion LIKE ?

        GROUP BY
            c.id,
            c.nombre,
            c.descripcion,
            c.activo,
            c.fecha_registro

        ORDER BY c.nombre ASC
    ";

    $stmt = $conexion->prepare($sql);

    $texto_busqueda = '%' . $busqueda . '%';

    $stmt->bind_param(
        "ss",
        $texto_busqueda,
        $texto_busqueda
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

} else {

    $sql = "
        SELECT
            c.id,
            c.nombre,
            c.descripcion,
            c.activo,
            c.fecha_registro,
            COUNT(m.id) AS total_materiales
        FROM marketing_categorias c

        LEFT JOIN marketing_materiales m
            ON m.categoria_id = c.id

        GROUP BY
            c.id,
            c.nombre,
            c.descripcion,
            c.activo,
            c.fecha_registro

        ORDER BY c.nombre ASC
    ";

    $resultado = $conexion->query($sql);
}


if ($resultado) {

    while ($fila = $resultado->fetch_assoc()) {

        $categorias[] = $fila;

    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Categorías - Marketing</title>

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
            width: 94%;
            max-width: 1300px;
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
            box-shadow: 0 4px 14px rgba(0,0,0,.06);

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
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
            background: #eef2ff;
            color: #3730a3;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | BOTONES
        |--------------------------------------------------------------------------
        */

        .botones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn {
            border: none;
            border-radius: 9px;
            padding: 10px 16px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
        }

        .btn-principal {
            background: #2563eb;
            color: white;
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

        .btn-editar {
            background: #eef2ff;
            color: #3730a3;
        }

        .btn-activar {
            background: #f0fdf4;
            color: #15803d;
        }

        .btn-desactivar {
            background: #fef2f2;
            color: #b91c1c;
        }

        /*
        |--------------------------------------------------------------------------
        | MENSAJES
        |--------------------------------------------------------------------------
        */

        .mensaje {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 13px 15px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 13px 15px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | BUSCADOR
        |--------------------------------------------------------------------------
        */

        .busqueda {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);

            display: flex;
            gap: 10px;
        }

        .busqueda input {
            flex: 1;
            min-width: 0;
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }

        .busqueda input:focus {
            border-color: #2563eb;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLA
        |--------------------------------------------------------------------------
        */

        .panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);
            overflow: hidden;
        }

        .panel-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .contador {
            color: #6b7280;
            font-size: 12px;
        }

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
            padding: 12px 15px;
            background: #f8fafc;
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
        }

        td {
            padding: 14px 15px;
            border-top: 1px solid #edf0f4;
            font-size: 13px;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafafa;
        }

        .nombre {
            font-weight: bold;
            color: #1f2937;
        }

        .descripcion {
            color: #6b7280;
            font-size: 11px;
            margin-top: 4px;
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
        }

        .activo {
            background: #f0fdf4;
            color: #15803d;
        }

        .inactivo {
            background: #f3f4f6;
            color: #6b7280;
        }

        .cantidad {
            background: #eff6ff;
            color: #1d4ed8;
        }

        /*
        |--------------------------------------------------------------------------
        | ACCIONES
        |--------------------------------------------------------------------------
        */

        .acciones {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .acciones form {
            margin: 0;
        }

        .acciones .btn {
            padding: 7px 10px;
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | MODAL
        |--------------------------------------------------------------------------
        */

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            background: rgba(0,0,0,.5);

            align-items: center;
            justify-content: center;

            padding: 20px;
        }

        .modal-contenido {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,.2);
        }

        .modal-contenido h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 20px;
        }

        .campo {
            margin-bottom: 15px;
        }

        .campo label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: bold;
            color: #374151;
        }

        .campo input,
        .campo textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            outline: none;
        }

        .campo textarea {
            min-height: 100px;
            resize: vertical;
        }

        .campo input:focus,
        .campo textarea:focus {
            border-color: #2563eb;
        }

        .modal-botones {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | VACÍO
        |--------------------------------------------------------------------------
        */

        .vacio {
            padding: 40px;
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

        @media (max-width: 700px) {

            .contenedor {
                width: 95%;
                padding-top: 15px;
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }

            .usuario {
                text-align: left;
            }

            .busqueda {
                flex-direction: column;
            }

            .busqueda .btn {
                width: 100%;
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

            <h1>🏷️ Categorías de Marketing</h1>

            <p>
                Administra la clasificación de los materiales publicitarios.
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
         MENSAJES
    ====================================================== -->

    <?php if ($mensaje !== ''): ?>

        <div class="mensaje">

            ✓
            <?php echo htmlspecialchars($mensaje); ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="error">

            ⚠
            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         BOTONES
    ====================================================== -->

    <div class="botones">

        <button
            type="button"
            class="btn btn-principal"
            onclick="abrirModalCrear()"
        >
            ➕ Nueva categoría
        </button>

        <a
            href="dashboard.php"
            class="btn btn-secundario"
        >
            📊 Dashboard
        </a>

        <a
            href="materiales.php"
            class="btn btn-secundario"
        >
            📁 Materiales
        </a>

        <a
            href="campanas.php"
            class="btn btn-secundario"
        >
            📢 Campañas
        </a>

    </div>


    <!-- =====================================================
         BUSCADOR
    ====================================================== -->

    <form
        method="GET"
        class="busqueda"
    >

        <input
            type="text"
            name="buscar"
            placeholder="Buscar categoría..."
            value="<?php echo htmlspecialchars($busqueda); ?>"
        >

        <button
            type="submit"
            class="btn btn-principal"
        >
            🔎 Buscar
        </button>

        <?php if ($busqueda !== ''): ?>

            <a
                href="categorias.php"
                class="btn btn-secundario"
            >
                Limpiar
            </a>

        <?php endif; ?>

    </form>


    <!-- =====================================================
         TABLA
    ====================================================== -->

    <div class="panel">

        <div class="panel-header">

            <h2>
                Categorías registradas
            </h2>

            <span class="contador">

                <?php
                echo count($categorias);
                ?>

                categoría(s)

            </span>

        </div>


        <div class="tabla-contenedor">

            <?php if (empty($categorias)): ?>

                <div class="vacio">

                    No hay categorías registradas.

                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Categoría</th>

                            <th>Descripción</th>

                            <th>Materiales</th>

                            <th>Estado</th>

                            <th>Registro</th>

                            <th>Acciones</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($categorias as $categoria): ?>

                        <tr>

                            <td>

                                #<?php
                                echo (int)$categoria['id'];
                                ?>

                            </td>


                            <td>

                                <div class="nombre">

                                    <?php
                                    echo htmlspecialchars(
                                        $categoria['nombre']
                                    );
                                    ?>

                                </div>

                            </td>


                            <td>

                                <?php if (!empty($categoria['descripcion'])): ?>

                                    <div class="descripcion">

                                        <?php
                                        echo htmlspecialchars(
                                            $categoria['descripcion']
                                        );
                                        ?>

                                    </div>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">
                                        Sin descripción
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="badge cantidad">

                                    <?php
                                    echo (int)$categoria['total_materiales'];
                                    ?>

                                    material(es)

                                </span>

                            </td>


                            <td>

                                <?php if ((int)$categoria['activo'] === 1): ?>

                                    <span class="badge activo">
                                        ● ACTIVA
                                    </span>

                                <?php else: ?>

                                    <span class="badge inactivo">
                                        ● INACTIVA
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    'd/m/Y',
                                    strtotime(
                                        $categoria['fecha_registro']
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <div class="acciones">

                                    <button
                                        type="button"
                                        class="btn btn-editar"
                                        onclick='abrirModalEditar(
                                            <?php echo json_encode(
                                                $categoria['id'],
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ); ?>,
                                            <?php echo json_encode(
                                                $categoria['nombre'],
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ); ?>,
                                            <?php echo json_encode(
                                                $categoria['descripcion'] ?? '',
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ); ?>
                                        )'
                                    >
                                        ✏️ Editar
                                    </button>


                                    <form
                                        method="POST"
                                        onsubmit="return confirmarEstado(
                                            <?php echo (int)$categoria['activo']; ?>
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="categoria_id"
                                            value="<?php
                                            echo (int)$categoria['id'];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="cambiar_estado"
                                            class="btn <?php
                                            echo (int)$categoria['activo'] === 1
                                                ? 'btn-desactivar'
                                                : 'btn-activar';
                                            ?>"
                                        >

                                            <?php if ((int)$categoria['activo'] === 1): ?>

                                                🔴 Desactivar

                                            <?php else: ?>

                                                🟢 Activar

                                            <?php endif; ?>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>


    <div class="footer">

        Sistema de Marketing · Administración de categorías

    </div>

</div>


<!-- =====================================================
     MODAL CREAR
====================================================== -->

<div
    id="modalCrear"
    class="modal"
>

    <div class="modal-contenido">

        <h2>
            ➕ Nueva categoría
        </h2>

        <form
            method="POST"
            onsubmit="return validarCrear();"
        >

            <div class="campo">

                <label>
                    Nombre de la categoría *
                </label>

                <input
                    type="text"
                    id="crear_nombre"
                    name="nombre"
                    maxlength="100"
                    required
                    placeholder="Ej. PROMOCIONAL"
                >

            </div>


            <div class="campo">

                <label>
                    Descripción
                </label>

                <textarea
                    name="descripcion"
                    maxlength="255"
                    placeholder="Describe el uso de esta categoría..."
                ></textarea>

            </div>


            <div class="modal-botones">

                <button
                    type="button"
                    class="btn btn-secundario"
                    onclick="cerrarModalCrear()"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    name="crear_categoria"
                    class="btn btn-principal"
                >
                    Guardar categoría
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     MODAL EDITAR
====================================================== -->

<div
    id="modalEditar"
    class="modal"
>

    <div class="modal-contenido">

        <h2>
            ✏️ Editar categoría
        </h2>

        <form
            method="POST"
            onsubmit="return validarEditar();"
        >

            <input
                type="hidden"
                id="editar_id"
                name="categoria_id"
            >


            <div class="campo">

                <label>
                    Nombre de la categoría *
                </label>

                <input
                    type="text"
                    id="editar_nombre"
                    name="nombre"
                    maxlength="100"
                    required
                >

            </div>


            <div class="campo">

                <label>
                    Descripción
                </label>

                <textarea
                    id="editar_descripcion"
                    name="descripcion"
                    maxlength="255"
                ></textarea>

            </div>


            <div class="modal-botones">

                <button
                    type="button"
                    class="btn btn-secundario"
                    onclick="cerrarModalEditar()"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    name="editar_categoria"
                    class="btn btn-principal"
                >
                    Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| MODAL CREAR
|--------------------------------------------------------------------------
*/

function abrirModalCrear()
{
    document.getElementById('modalCrear').style.display = 'flex';

    setTimeout(function() {

        document.getElementById('crear_nombre').focus();

    }, 100);
}


function cerrarModalCrear()
{
    document.getElementById('modalCrear').style.display = 'none';
}


/*
|--------------------------------------------------------------------------
| MODAL EDITAR
|--------------------------------------------------------------------------
*/

function abrirModalEditar(id, nombre, descripcion)
{
    document.getElementById('editar_id').value = id;

    document.getElementById('editar_nombre').value = nombre;

    document.getElementById('editar_descripcion').value =
        descripcion || '';

    document.getElementById('modalEditar').style.display = 'flex';

    setTimeout(function() {

        document.getElementById('editar_nombre').focus();

    }, 100);
}


function cerrarModalEditar()
{
    document.getElementById('modalEditar').style.display = 'none';
}


/*
|--------------------------------------------------------------------------
| VALIDACIONES
|--------------------------------------------------------------------------
*/

function validarCrear()
{
    const nombre =
        document.getElementById('crear_nombre').value.trim();

    if (nombre === '') {

        alert('Debes ingresar el nombre de la categoría.');

        return false;
    }

    return true;
}


function validarEditar()
{
    const nombre =
        document.getElementById('editar_nombre').value.trim();

    if (nombre === '') {

        alert('Debes ingresar el nombre de la categoría.');

        return false;
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| CONFIRMAR CAMBIO DE ESTADO
|--------------------------------------------------------------------------
*/

function confirmarEstado(activo)
{
    if (activo === 1) {

        return confirm(
            '¿Deseas desactivar esta categoría?'
        );

    }

    return confirm(
        '¿Deseas activar esta categoría?'
    );
}


/*
|--------------------------------------------------------------------------
| CERRAR MODALES AL HACER CLICK AFUERA
|--------------------------------------------------------------------------
*/

window.addEventListener('click', function(event)
{

    const modalCrear =
        document.getElementById('modalCrear');

    const modalEditar =
        document.getElementById('modalEditar');


    if (event.target === modalCrear) {

        cerrarModalCrear();

    }


    if (event.target === modalEditar) {

        cerrarModalEditar();

    }

});


/*
|--------------------------------------------------------------------------
| ESC PARA CERRAR
|--------------------------------------------------------------------------
*/

document.addEventListener('keydown', function(event)
{

    if (event.key === 'Escape') {

        cerrarModalCrear();

        cerrarModalEditar();

    }

});

</script>

</body>

</html>