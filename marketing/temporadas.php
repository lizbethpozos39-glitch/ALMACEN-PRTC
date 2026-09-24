<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();

$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());

$es_admin = ($rol_usuario === 'ADMIN');

$mensaje = "";
$error   = "";

$editar_id = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;


/*
|--------------------------------------------------------------------------
| FUNCIÓN DE AUDITORÍA
|--------------------------------------------------------------------------
*/

function registrar_auditoria_temporada(
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
        VALUES (?, ?, 'TEMPORADAS', ?, ?)
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
| CREAR TEMPORADA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_temporada'])) {

    if (!$es_admin) {

        $error = "No tienes permisos para crear temporadas.";

    } else {

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        $fecha_inicio = !empty($_POST['fecha_inicio'])
            ? $_POST['fecha_inicio']
            : null;

        $fecha_fin = !empty($_POST['fecha_fin'])
            ? $_POST['fecha_fin']
            : null;

        $dias_anticipacion = (int)($_POST['dias_anticipacion'] ?? 15);

        if ($nombre === '') {

            $error = "El nombre de la temporada es obligatorio.";

        } elseif ($dias_anticipacion < 0) {

            $error = "Los días de anticipación no pueden ser negativos.";

        } elseif (
            $fecha_inicio !== null &&
            $fecha_fin !== null &&
            $fecha_fin < $fecha_inicio
        ) {

            $error = "La fecha final no puede ser anterior a la fecha inicial.";

        } else {

            $sql = "
                INSERT INTO marketing_temporadas
                (
                    nombre,
                    descripcion,
                    fecha_inicio,
                    fecha_fin,
                    dias_anticipacion,
                    activo
                )
                VALUES (?, ?, ?, ?, ?, 1)
            ";

            $stmt = $conexion->prepare($sql);

            if (!$stmt) {

                $error = "Error al preparar el registro: " . $conexion->error;

            } else {

                $stmt->bind_param(
                    "ssssi",
                    $nombre,
                    $descripcion,
                    $fecha_inicio,
                    $fecha_fin,
                    $dias_anticipacion
                );

                if ($stmt->execute()) {

                    $nuevo_id = $stmt->insert_id;

                    registrar_auditoria_temporada(
                        $conexion,
                        $empleado_id,
                        'CREAR_TEMPORADA',
                        $nuevo_id,
                        "Se creó la temporada: " . $nombre
                    );

                    $mensaje = "Temporada creada correctamente.";

                } else {

                    $error = "No fue posible crear la temporada: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDITAR TEMPORADA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_temporada'])) {

    if (!$es_admin) {

        $error = "No tienes permisos para editar temporadas.";

    } else {

        $id = (int)($_POST['id'] ?? 0);

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        $fecha_inicio = !empty($_POST['fecha_inicio'])
            ? $_POST['fecha_inicio']
            : null;

        $fecha_fin = !empty($_POST['fecha_fin'])
            ? $_POST['fecha_fin']
            : null;

        $dias_anticipacion = (int)($_POST['dias_anticipacion'] ?? 15);

        if ($id <= 0) {

            $error = "Temporada inválida.";

        } elseif ($nombre === '') {

            $error = "El nombre de la temporada es obligatorio.";

        } elseif ($dias_anticipacion < 0) {

            $error = "Los días de anticipación no pueden ser negativos.";

        } elseif (
            $fecha_inicio !== null &&
            $fecha_fin !== null &&
            $fecha_fin < $fecha_inicio
        ) {

            $error = "La fecha final no puede ser anterior a la fecha inicial.";

        } else {

            $sql = "
                UPDATE marketing_temporadas
                SET
                    nombre = ?,
                    descripcion = ?,
                    fecha_inicio = ?,
                    fecha_fin = ?,
                    dias_anticipacion = ?
                WHERE id = ?
            ";

            $stmt = $conexion->prepare($sql);

            if (!$stmt) {

                $error = "Error al preparar la actualización: " . $conexion->error;

            } else {

                $stmt->bind_param(
                    "ssssii",
                    $nombre,
                    $descripcion,
                    $fecha_inicio,
                    $fecha_fin,
                    $dias_anticipacion,
                    $id
                );

                if ($stmt->execute()) {

                    registrar_auditoria_temporada(
                        $conexion,
                        $empleado_id,
                        'EDITAR_TEMPORADA',
                        $id,
                        "Se editó la temporada: " . $nombre
                    );

                    $mensaje = "Temporada actualizada correctamente.";

                } else {

                    $error = "No fue posible actualizar la temporada: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ACTIVAR / DESACTIVAR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {

    if (!$es_admin) {

        $error = "No tienes permisos para cambiar el estado.";

    } else {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {

            $error = "Temporada inválida.";

        } else {

            $sql = "
                SELECT nombre, activo
                FROM marketing_temporadas
                WHERE id = ?
                LIMIT 1
            ";

            $stmt = $conexion->prepare($sql);

            if ($stmt) {

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $resultado = $stmt->get_result();
                $temporada = $resultado->fetch_assoc();

                $stmt->close();

                if (!$temporada) {

                    $error = "La temporada no existe.";

                } else {

                    $nuevo_estado = ((int)$temporada['activo'] === 1) ? 0 : 1;

                    $sqlUpdate = "
                        UPDATE marketing_temporadas
                        SET activo = ?
                        WHERE id = ?
                    ";

                    $stmtUpdate = $conexion->prepare($sqlUpdate);

                    if ($stmtUpdate) {

                        $stmtUpdate->bind_param(
                            "ii",
                            $nuevo_estado,
                            $id
                        );

                        if ($stmtUpdate->execute()) {

                            $accion = $nuevo_estado
                                ? 'ACTIVAR_TEMPORADA'
                                : 'DESACTIVAR_TEMPORADA';

                            $texto = $nuevo_estado
                                ? 'activó'
                                : 'desactivó';

                            registrar_auditoria_temporada(
                                $conexion,
                                $empleado_id,
                                $accion,
                                $id,
                                "Se " . $texto . " la temporada: " . $temporada['nombre']
                            );

                            $mensaje = "Temporada " .
                                ($nuevo_estado ? "activada" : "desactivada") .
                                " correctamente.";

                        } else {

                            $error = "No fue posible cambiar el estado.";
                        }

                        $stmtUpdate->close();

                    } else {

                        $error = "Error al preparar el cambio de estado.";
                    }
                }

            } else {

                $error = "Error al consultar la temporada.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER TEMPORADA PARA EDITAR
|--------------------------------------------------------------------------
*/

$temporada_editar = null;

if ($editar_id > 0) {

    $sql = "
        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            dias_anticipacion,
            activo
        FROM marketing_temporadas
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $editar_id);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $temporada_editar = $resultado->fetch_assoc();

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET['buscar'] ?? '');


/*
|--------------------------------------------------------------------------
| OBTENER TEMPORADAS
|--------------------------------------------------------------------------
*/

$temporadas = [];

$sql = "
    SELECT
        t.id,
        t.nombre,
        t.descripcion,
        t.fecha_inicio,
        t.fecha_fin,
        t.dias_anticipacion,
        t.activo,
        t.fecha_registro,

        (
            SELECT COUNT(*)
            FROM marketing_campanas c
            WHERE
                c.fecha_inicio IS NOT NULL
                AND c.fecha_fin IS NOT NULL
                AND (
                    c.nombre LIKE CONCAT('%', t.nombre, '%')
                    OR t.nombre LIKE CONCAT('%', c.nombre, '%')
                )
        ) AS total_campanas

    FROM marketing_temporadas t
";

if ($buscar !== '') {

    $sql .= "
        WHERE
            t.nombre LIKE ?
            OR t.descripcion LIKE ?
    ";
}

$sql .= "
    ORDER BY
        t.fecha_inicio IS NULL,
        t.fecha_inicio ASC,
        t.nombre ASC
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
        $temporadas[] = $fila;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$total_temporadas = count($temporadas);

$activas = 0;
$vigentes = 0;
$proximas = 0;
$finalizadas = 0;

$hoy = date('Y-m-d');

foreach ($temporadas as $temporada) {

    if ((int)$temporada['activo'] === 1) {
        $activas++;
    }

    $inicio = $temporada['fecha_inicio'];
    $fin    = $temporada['fecha_fin'];

    if ($inicio && $fin) {

        if ($hoy >= $inicio && $hoy <= $fin) {
            $vigentes++;
        } elseif ($hoy < $inicio) {
            $proximas++;
        } elseif ($hoy > $fin) {
            $finalizadas++;
        }

    } elseif ($inicio && $hoy < $inicio) {

        $proximas++;

    } elseif ($fin && $hoy > $fin) {

        $finalizadas++;
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN ESTADO TEMPORADA
|--------------------------------------------------------------------------
*/

function estado_temporada($inicio, $fin, $activo)
{
    if ((int)$activo !== 1) {
        return [
            'texto' => 'INACTIVA',
            'clase' => 'inactiva'
        ];
    }

    $hoy = date('Y-m-d');

    if ($inicio && $fin) {

        if ($hoy >= $inicio && $hoy <= $fin) {
            return [
                'texto' => 'VIGENTE',
                'clase' => 'vigente'
            ];
        }

        if ($hoy < $inicio) {
            return [
                'texto' => 'PRÓXIMA',
                'clase' => 'proxima'
            ];
        }

        return [
            'texto' => 'FINALIZADA',
            'clase' => 'finalizada'
        ];
    }

    if ($inicio && $hoy < $inicio) {

        return [
            'texto' => 'PRÓXIMA',
            'clase' => 'proxima'
        ];
    }

    if ($fin && $hoy > $fin) {

        return [
            'texto' => 'FINALIZADA',
            'clase' => 'finalizada'
        ];
    }

    return [
        'texto' => 'SIN FECHA',
        'clase' => 'sinfecha'
    ];
}


/*
|--------------------------------------------------------------------------
| FORMATO FECHA
|--------------------------------------------------------------------------
*/

function fecha_temporada($fecha)
{
    if (!$fecha) {
        return 'Sin definir';
    }

    $timestamp = strtotime($fecha);

    return date('d/m/Y', $timestamp);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Temporadas | Marketing</title>

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


/* CONTENEDOR */

.contenedor {
    width: 94%;
    max-width: 1450px;
    margin: 30px auto;
}


/* ENCABEZADO */

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


/* BOTONES */

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

.btn-editar {
    background: #f59e0b;
    color: white;
}

.btn-editar:hover {
    background: #d97706;
}

.btn-activo {
    background: #dcfce7;
    color: #166534;
}

.btn-inactivo {
    background: #fee2e2;
    color: #991b1b;
}


/* ALERTAS */

.alerta {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
}

.alerta.exito {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alerta.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}


/* ESTADÍSTICAS */

.estadisticas {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.tarjeta {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
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


/* FORMULARIO */

.formulario {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.formulario h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.grid-form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.campo {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.campo.completo {
    grid-column: 1 / -1;
}

.campo label {
    font-size: 14px;
    font-weight: bold;
}

.campo input,
.campo textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-family: inherit;
    font-size: 14px;
}

.campo textarea {
    min-height: 90px;
    resize: vertical;
}

.acciones-form {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}


/* BUSCADOR */

.buscador {
    background: white;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.form-busqueda {
    display: flex;
    gap: 10px;
}

.form-busqueda input {
    flex: 1;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: 14px;
}


/* TABLA */

.contenedor-tabla {
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

thead {
    background: #f9fafb;
}

th,
td {
    padding: 14px 15px;
    border-bottom: 1px solid #e5e7eb;
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

.nombre-temporada {
    font-weight: bold;
    color: #111827;
}

.descripcion {
    color: #6b7280;
    max-width: 300px;
}


/* ESTADOS */

.estado {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.estado.vigente {
    background: #dcfce7;
    color: #166534;
}

.estado.proxima {
    background: #dbeafe;
    color: #1d4ed8;
}

.estado.finalizada {
    background: #e5e7eb;
    color: #374151;
}

.estado.inactiva {
    background: #fee2e2;
    color: #991b1b;
}

.estado.sinfecha {
    background: #fef3c7;
    color: #92400e;
}


/* ACCIONES */

.acciones {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.acciones form {
    margin: 0;
}


/* VACÍO */

.vacio {
    text-align: center;
    padding: 50px;
    color: #6b7280;
}


/* RESPONSIVE */

@media (max-width: 1000px) {

    .estadisticas {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .encabezado {
        flex-direction: column;
        align-items: flex-start;
    }

    .grid-form {
        grid-template-columns: 1fr;
    }

    .campo.completo {
        grid-column: auto;
    }

    .estadisticas {
        grid-template-columns: 1fr;
    }

    .form-busqueda {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="contenedor">


    <!-- ENCABEZADO -->

    <div class="encabezado">

        <div class="titulo">

            <h1>📅 Temporadas</h1>

            <p>
                Administración de temporadas y fechas importantes de Marketing.
            </p>

        </div>

        <div class="botones">

            <a href="dashboard.php" class="btn btn-secundario">
                Dashboard
            </a>

            <a href="calendario.php" class="btn btn-secundario">
                Calendario
            </a>

            <?php if ($es_admin): ?>

                <a href="temporadas.php#formulario"
                   class="btn btn-principal">
                    + Nueva temporada
                </a>

            <?php endif; ?>

        </div>

    </div>


    <!-- MENSAJES -->

    <?php if ($mensaje): ?>

        <div class="alerta exito">
            <?= htmlspecialchars($mensaje) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alerta error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ESTADÍSTICAS -->

    <div class="estadisticas">

        <div class="tarjeta">

            <div class="numero">
                <?= $total_temporadas ?>
            </div>

            <div class="texto">
                Total de temporadas
            </div>

        </div>


        <div class="tarjeta">

            <div class="numero">
                <?= $activas ?>
            </div>

            <div class="texto">
                Temporadas activas
            </div>

        </div>


        <div class="tarjeta">

            <div class="numero">
                <?= $vigentes ?>
            </div>

            <div class="texto">
                Vigentes actualmente
            </div>

        </div>


        <div class="tarjeta">

            <div class="numero">
                <?= $proximas ?>
            </div>

            <div class="texto">
                Próximas temporadas
            </div>

        </div>

    </div>


    <!-- FORMULARIO -->

    <?php if ($es_admin): ?>

        <div class="formulario" id="formulario">

            <?php if ($temporada_editar): ?>

                <h2>✏️ Editar temporada</h2>

                <form method="POST">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$temporada_editar['id'] ?>"
                    >

                    <div class="grid-form">

                        <div class="campo completo">

                            <label>
                                Nombre de la temporada *
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                maxlength="150"
                                required
                                value="<?= htmlspecialchars($temporada_editar['nombre']) ?>"
                                placeholder="Ej. Navidad 2026"
                            >

                        </div>


                        <div class="campo completo">

                            <label>
                                Descripción
                            </label>

                            <textarea
                                name="descripcion"
                                placeholder="Describe la temporada, campaña comercial o fecha especial..."
                            ><?= htmlspecialchars($temporada_editar['descripcion'] ?? '') ?></textarea>

                        </div>


                        <div class="campo">

                            <label>
                                Fecha de inicio
                            </label>

                            <input
                                type="date"
                                name="fecha_inicio"
                                value="<?= htmlspecialchars($temporada_editar['fecha_inicio'] ?? '') ?>"
                            >

                        </div>


                        <div class="campo">

                            <label>
                                Fecha de fin
                            </label>

                            <input
                                type="date"
                                name="fecha_fin"
                                value="<?= htmlspecialchars($temporada_editar['fecha_fin'] ?? '') ?>"
                            >

                        </div>


                        <div class="campo">

                            <label>
                                Días de anticipación
                            </label>

                            <input
                                type="number"
                                name="dias_anticipacion"
                                min="0"
                                max="365"
                                value="<?= (int)$temporada_editar['dias_anticipacion'] ?>"
                            >

                        </div>

                    </div>


                    <div class="acciones-form">

                        <button
                            type="submit"
                            name="editar_temporada"
                            class="btn btn-principal"
                        >
                            Guardar cambios
                        </button>

                        <a
                            href="temporadas.php"
                            class="btn btn-secundario"
                        >
                            Cancelar
                        </a>

                    </div>

                </form>

            <?php else: ?>

                <h2>➕ Nueva temporada</h2>

                <form method="POST">

                    <div class="grid-form">

                        <div class="campo completo">

                            <label>
                                Nombre de la temporada *
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                maxlength="150"
                                required
                                placeholder="Ej. Navidad 2026"
                            >

                        </div>


                        <div class="campo completo">

                            <label>
                                Descripción
                            </label>

                            <textarea
                                name="descripcion"
                                placeholder="Describe la temporada..."
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
                                Días de anticipación
                            </label>

                            <input
                                type="number"
                                name="dias_anticipacion"
                                min="0"
                                max="365"
                                value="15"
                            >

                        </div>

                    </div>


                    <div class="acciones-form">

                        <button
                            type="submit"
                            name="crear_temporada"
                            class="btn btn-principal"
                        >
                            Crear temporada
                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- BUSCADOR -->

    <div class="buscador">

        <form method="GET" class="form-busqueda">

            <input
                type="text"
                name="buscar"
                value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar temporada..."
            >

            <button
                type="submit"
                class="btn btn-principal"
            >
                🔎 Buscar
            </button>

            <?php if ($buscar !== ''): ?>

                <a
                    href="temporadas.php"
                    class="btn btn-secundario"
                >
                    Limpiar
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- TABLA -->

    <div class="contenedor-tabla">

        <?php if (empty($temporadas)): ?>

            <div class="vacio">

                <h3>No hay temporadas registradas</h3>

                <p>
                    <?= $buscar !== ''
                        ? 'No se encontraron resultados para la búsqueda.'
                        : 'Comienza creando la primera temporada.'
                    ?>
                </p>

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>Temporada</th>

                        <th>Periodo</th>

                        <th>Anticipación</th>

                        <th>Estado</th>

                        <th>Campañas</th>

                        <th>Registro</th>

                        <?php if ($es_admin): ?>
                            <th>Acciones</th>
                        <?php endif; ?>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($temporadas as $temporada): ?>

                    <?php

                    $estado = estado_temporada(
                        $temporada['fecha_inicio'],
                        $temporada['fecha_fin'],
                        $temporada['activo']
                    );

                    ?>

                    <tr>

                        <td>

                            <div class="nombre-temporada">

                                <?= htmlspecialchars($temporada['nombre']) ?>

                            </div>

                            <?php if (!empty($temporada['descripcion'])): ?>

                                <div class="descripcion">

                                    <?= htmlspecialchars(
                                        mb_strimwidth(
                                            $temporada['descripcion'],
                                            0,
                                            120,
                                            '...'
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <strong>
                                <?= fecha_temporada($temporada['fecha_inicio']) ?>
                            </strong>

                            <br>

                            <span style="color:#6b7280;">
                                al
                                <?= fecha_temporada($temporada['fecha_fin']) ?>
                            </span>

                        </td>


                        <td>

                            <?= (int)$temporada['dias_anticipacion'] ?>

                            día(s)

                        </td>


                        <td>

                            <span class="estado <?= $estado['clase'] ?>">

                                <?= $estado['texto'] ?>

                            </span>

                        </td>


                        <td>

                            <strong>
                                <?= (int)$temporada['total_campanas'] ?>
                            </strong>

                        </td>


                        <td>

                            <?= fecha_temporada(
                                substr(
                                    $temporada['fecha_registro'],
                                    0,
                                    10
                                )
                            ) ?>

                        </td>


                        <?php if ($es_admin): ?>

                            <td>

                                <div class="acciones">

                                    <a
                                        href="temporadas.php?editar=<?= (int)$temporada['id'] ?>#formulario"
                                        class="btn btn-editar"
                                    >
                                        ✏️ Editar
                                    </a>


                                    <form
                                        method="POST"
                                        onsubmit="return confirmarEstado();"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$temporada['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="cambiar_estado"
                                            class="btn <?= ((int)$temporada['activo'] === 1)
                                                ? 'btn-activo'
                                                : 'btn-inactivo'
                                            ?>"
                                        >

                                            <?= ((int)$temporada['activo'] === 1)
                                                ? 'Desactivar'
                                                : 'Activar'
                                            ?>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        <?php endif; ?>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>


<script>

function confirmarEstado() {

    return confirm(
        "¿Deseas cambiar el estado de esta temporada?"
    );

}

</script>

</body>

</html>