<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_admin();

$mensaje = "";
$error = "";


/*
|--------------------------------------------------------------------------
| CAMBIAR ROL / ACTIVAR / DESACTIVAR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {

        if ($accion === 'cambiar_rol') {

            $rol = strtoupper(
                trim($_POST['rol'] ?? '')
            );

            $roles_validos = [
                'ADMIN',
                'GERENCIA',
                'USUARIO',
                'CONSULTA'
            ];

            if (
                in_array(
                    $rol,
                    $roles_validos,
                    true
                )
            ) {

                $stmt = $conexion->prepare("
                    UPDATE inventario_db.usuarios
                    SET rol = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "si",
                    $rol,
                    $id
                );

                if ($stmt->execute()) {
                    $mensaje = "Rol actualizado correctamente.";
                } else {
                    $error = "No fue posible actualizar el rol.";
                }

                $stmt->close();

            } else {

                $error = "Rol no válido.";
            }


        } elseif ($accion === 'activar') {

            $stmt = $conexion->prepare("
                UPDATE inventario_db.usuarios
                SET activo = 1
                WHERE id = ?
            ");

            $stmt->bind_param(
                "i",
                $id
            );

            $stmt->execute();
            $stmt->close();

            $mensaje = "Usuario activado correctamente.";


        } elseif ($accion === 'desactivar') {

            /*
            |--------------------------------------------------------------------------
            | EVITAR DESACTIVAR EL USUARIO ADMIN ACTUAL
            |--------------------------------------------------------------------------
            */

            if (
                $id === (int)usuario_id_actual()
            ) {

                $error =
                    "No puedes desactivar el usuario con el que estás conectado.";

            } else {

                $stmt = $conexion->prepare("
                    UPDATE inventario_db.usuarios
                    SET activo = 0
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $id
                );

                $stmt->execute();
                $stmt->close();

                $mensaje =
                    "Usuario desactivado correctamente.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CAMBIAR CONTRASEÑA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['accion'] ?? '') === 'cambiar_password'
) {

    $id = (int)($_POST['id'] ?? 0);

    $password =
        $_POST['password'] ?? '';

    $password_confirmacion =
        $_POST['password_confirmacion'] ?? '';

    if ($id <= 0) {

        $error = "Usuario inválido.";

    } elseif (strlen($password) < 6) {

        $error =
            "La contraseña debe tener al menos 6 caracteres.";

    } elseif ($password !== $password_confirmacion) {

        $error =
            "Las contraseñas no coinciden.";

    } else {

        $hash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        $stmt = $conexion->prepare("
            UPDATE inventario_db.usuarios
            SET password_hash = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $hash,
            $id
        );

        if ($stmt->execute()) {

            $mensaje =
                "Contraseña actualizada correctamente.";

        } else {

            $error =
                "No fue posible actualizar la contraseña.";
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| BUSCAR USUARIOS
|--------------------------------------------------------------------------
*/

$buscar =
    trim($_GET['buscar'] ?? '');

$rol_filtro =
    strtoupper(
        trim($_GET['rol'] ?? '')
    );


$where = [];
$parametros = [];
$tipos = '';


if ($buscar !== '') {

    $texto = '%' . $buscar . '%';

    $where[] = "
        (
            u.usuario LIKE ?
            OR e.nombre LIKE ?
            OR e.apellido_paterno LIKE ?
            OR e.apellido_materno LIKE ?
        )
    ";

    $parametros[] = $texto;
    $parametros[] = $texto;
    $parametros[] = $texto;
    $parametros[] = $texto;

    $tipos .= 'ssss';
}


if ($rol_filtro !== '') {

    $where[] = "u.rol = ?";

    $parametros[] = $rol_filtro;

    $tipos .= 's';
}


$where_sql = '';

if (!empty($where)) {

    $where_sql =
        "WHERE " .
        implode(
            " AND ",
            $where
        );
}


/*
|--------------------------------------------------------------------------
| CONSULTAR USUARIOS
|--------------------------------------------------------------------------
*/

$usuarios = [];

$sql = "
    SELECT

        u.id,
        u.empleado_id,
        u.usuario,
        u.rol,
        u.activo,

        e.nombre,
        e.apellido_paterno,
        e.apellido_materno,
        e.puesto,
        e.area

    FROM inventario_db.usuarios u

    INNER JOIN inventario_db.empleados e
        ON e.id = u.empleado_id

    $where_sql

    ORDER BY
        e.nombre,
        e.apellido_paterno
";

$stmt = $conexion->prepare($sql);

if (!empty($parametros)) {

    $stmt->bind_param(
        $tipos,
        ...$parametros
    );
}

$stmt->execute();

$resultado =
    $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {

    $usuarios[] = $fila;
}

$stmt->close();


function e($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
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

<title>Usuarios | Sistema Comercial</title>

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

.menu a:hover,
.menu a.activo {
    background: #374151;
    color: white;
    border-left-color: #3b82f6;
}

.contenido {
    margin-left: 240px;
    padding: 30px;
}

.encabezado {
    margin-bottom: 25px;
}

.encabezado h1 {
    margin: 0;
    font-size: 28px;
}

.encabezado p {
    color: #6b7280;
}

.panel {
    background: white;
    padding: 22px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.filtros {
    display: flex;
    gap: 10px;
}

.filtros input,
.filtros select {
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.filtros input {
    flex: 1;
}

.boton {
    display: inline-block;
    padding: 9px 13px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

.azul {
    background: #2563eb;
    color: white;
}

.verde {
    background: #16a34a;
    color: white;
}

.rojo {
    background: #dc2626;
    color: white;
}

.gris {
    background: #6b7280;
    color: white;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f3f4f6;
    text-align: left;
    padding: 12px;
    font-size: 12px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    vertical-align: middle;
}

.estado {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 11px;
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

.rol-form {
    display: flex;
    gap: 5px;
}

.rol-form select {
    padding: 7px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    font-size: 12px;
}

.acciones {
    white-space: nowrap;
}

.mensaje {
    padding: 12px;
    background: #dcfce7;
    color: #166534;
    border-radius: 6px;
    margin-bottom: 20px;
}

.error {
    padding: 12px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 6px;
    margin-bottom: 20px;
}

.tabla {
    overflow-x: auto;
}

.password-box {
    margin-top: 8px;
    padding: 10px;
    background: #f9fafb;
    border-radius: 6px;
}

.password-box input {
    padding: 7px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    width: 150px;
}

@media(max-width:900px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 20px;
    }

    .filtros {
        flex-direction: column;
    }
}

</style>

</head>

<body>


<aside class="sidebar">

    <div class="logo">
        Sistema Comercial
        <span>Administración</span>
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

        <div class="menu-titulo">
            Productos
        </div>

        <a href="productos.php">
            Productos y servicios
        </a>

        <div class="menu-titulo">
            Cotizaciones
        </div>

        <a href="cotizaciones.php">
            Cotizaciones
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

        <div class="menu-titulo">
            Administración
        </div>

        <a href="empleados.php">
            Empleados
        </a>

        <a href="usuarios.php" class="activo">
            Usuarios
        </a>

    </div>

</aside>


<main class="contenido">

    <div class="encabezado">

        <h1>
            Usuarios
        </h1>

        <p>
            Administración de usuarios y permisos del sistema Comercial.
        </p>

    </div>


    <?php if ($mensaje !== ''): ?>

        <div class="mensaje">
            <?= e($mensaje) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <div class="panel">

        <form method="GET">

            <div class="filtros">

                <input
                    type="text"
                    name="buscar"
                    value="<?= e($buscar) ?>"
                    placeholder="Buscar usuario o empleado..."
                >

                <select name="rol">

                    <option value="">
                        Todos los roles
                    </option>

                    <option
                        value="ADMIN"
                        <?= $rol_filtro === 'ADMIN' ? 'selected' : '' ?>
                    >
                        ADMIN
                    </option>

                    <option
                        value="GERENCIA"
                        <?= $rol_filtro === 'GERENCIA' ? 'selected' : '' ?>
                    >
                        GERENCIA
                    </option>

                    <option
                        value="USUARIO"
                        <?= $rol_filtro === 'USUARIO' ? 'selected' : '' ?>
                    >
                        USUARIO
                    </option>

                    <option
                        value="CONSULTA"
                        <?= $rol_filtro === 'CONSULTA' ? 'selected' : '' ?>
                    >
                        CONSULTA
                    </option>

                </select>

                <button
                    type="submit"
                    class="boton azul"
                >
                    Buscar
                </button>

                <a
                    href="usuarios.php"
                    class="boton gris"
                >
                    Limpiar
                </a>

            </div>

        </form>

    </div>


    <div class="panel">

        <div class="tabla">

            <table>

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Empleado</th>
                        <th>Puesto / Área</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Cambiar rol</th>
                        <th>Acciones</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($usuarios)): ?>

                    <?php foreach ($usuarios as $usuario): ?>

                        <tr>

                            <td>
                                <?= e($usuario['id']) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e($usuario['usuario']) ?>
                                </strong>

                            </td>

                            <td>

                                <?= e(
                                    trim(
                                        $usuario['nombre'] . ' ' .
                                        $usuario['apellido_paterno'] . ' ' .
                                        $usuario['apellido_materno']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= e($usuario['puesto']) ?>

                                <br>

                                <small style="color:#6b7280;">
                                    <?= e($usuario['area']) ?>
                                </small>

                            </td>

                            <td>

                                <strong>
                                    <?= e($usuario['rol']) ?>
                                </strong>

                            </td>

                            <td>

                                <?php if ((int)$usuario['activo'] === 1): ?>

                                    <span class="estado activo">
                                        ACTIVO
                                    </span>

                                <?php else: ?>

                                    <span class="estado inactivo">
                                        INACTIVO
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <form
                                    method="POST"
                                    class="rol-form"
                                >

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="cambiar_rol"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= e($usuario['id']) ?>"
                                    >

                                    <select name="rol">

                                        <option
                                            value="ADMIN"
                                            <?= $usuario['rol'] === 'ADMIN' ? 'selected' : '' ?>
                                        >
                                            ADMIN
                                        </option>

                                        <option
                                            value="GERENCIA"
                                            <?= $usuario['rol'] === 'GERENCIA' ? 'selected' : '' ?>
                                        >
                                            GERENCIA
                                        </option>

                                        <option
                                            value="USUARIO"
                                            <?= $usuario['rol'] === 'USUARIO' ? 'selected' : '' ?>
                                        >
                                            USUARIO
                                        </option>

                                        <option
                                            value="CONSULTA"
                                            <?= $usuario['rol'] === 'CONSULTA' ? 'selected' : '' ?>
                                        >
                                            CONSULTA
                                        </option>

                                    </select>

                                    <button
                                        type="submit"
                                        class="boton azul"
                                    >
                                        Guardar
                                    </button>

                                </form>

                            </td>

                            <td class="acciones">

                                <?php if ((int)$usuario['activo'] === 1): ?>

                                    <?php if (
                                        (int)$usuario['id']
                                        !==
                                        (int)usuario_id_actual()
                                    ): ?>

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="desactivar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= e($usuario['id']) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="boton rojo"
                                                onclick="return confirm('¿Desactivar este usuario?');"
                                            >
                                                Desactivar
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#6b7280;
                                                font-size:11px;
                                            "
                                        >
                                            Usuario actual
                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                    >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="activar"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= e($usuario['id']) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="boton verde"
                                        >
                                            Activar
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            style="text-align:center;padding:35px;"
                        >
                            No se encontraron usuarios.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

</body>

</html>