<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_admin();

$mensaje = "";
$tipo_mensaje = "success";


/*
|--------------------------------------------------------------------------
| ACTIVAR / DESACTIVAR EMPLEADO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {

        if ($accion === 'activar') {

            $stmt = $conexion->prepare("
                UPDATE inventario_db.empleados
                SET activo = 1
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Empleado activado correctamente.";

        } elseif ($accion === 'desactivar') {

            $stmt = $conexion->prepare("
                UPDATE inventario_db.empleados
                SET activo = 0
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Empleado desactivado correctamente.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET['buscar'] ?? '');

$empleados = [];


if ($buscar !== '') {

    $texto = '%' . $buscar . '%';

    $stmt = $conexion->prepare("
        SELECT
            id,
            nombre,
            apellido_paterno,
            apellido_materno,
            puesto,
            area,
            correo,
            telefono,
            activo
        FROM inventario_db.empleados
        WHERE
            nombre LIKE ?
            OR apellido_paterno LIKE ?
            OR apellido_materno LIKE ?
            OR puesto LIKE ?
            OR area LIKE ?
            OR correo LIKE ?
        ORDER BY nombre, apellido_paterno
    ");

    $stmt->bind_param(
        "ssssss",
        $texto,
        $texto,
        $texto,
        $texto,
        $texto,
        $texto
    );

} else {

    $stmt = $conexion->prepare("
        SELECT
            id,
            nombre,
            apellido_paterno,
            apellido_materno,
            puesto,
            area,
            correo,
            telefono,
            activo
        FROM inventario_db.empleados
        ORDER BY nombre, apellido_paterno
    ");
}

$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $empleados[] = $fila;
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

<title>Empleados | Sistema Comercial</title>

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
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.filtros input {
    flex: 1;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.boton {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
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

.tabla {
    overflow-x: auto;
}

@media(max-width:850px) {

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

        <a href="empleados.php" class="activo">
            Empleados
        </a>

        <a href="usuarios.php">
            Usuarios
        </a>

    </div>

</aside>


<main class="contenido">

    <div class="encabezado">

        <div>

            <h1>Empleados</h1>

            <p>
                Administración de empleados de la empresa.
            </p>

        </div>

    </div>


    <?php if ($mensaje !== ''): ?>

        <div class="mensaje">
            <?= e($mensaje) ?>
        </div>

    <?php endif; ?>


    <div class="panel">

        <form method="GET">

            <div class="filtros">

                <input
                    type="text"
                    name="buscar"
                    value="<?= e($buscar) ?>"
                    placeholder="Buscar empleado, puesto, área o correo..."
                >

                <button
                    type="submit"
                    class="boton azul"
                >
                    Buscar
                </button>

                <a
                    href="empleados.php"
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
                        <th>Empleado</th>
                        <th>Puesto</th>
                        <th>Área</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Acción</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($empleados)): ?>

                    <?php foreach ($empleados as $empleado): ?>

                        <tr>

                            <td>
                                <?= e($empleado['id']) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e(
                                        trim(
                                            $empleado['nombre'] . ' ' .
                                            $empleado['apellido_paterno'] . ' ' .
                                            $empleado['apellido_materno']
                                        )
                                    ) ?>
                                </strong>

                            </td>

                            <td>
                                <?= e($empleado['puesto']) ?>
                            </td>

                            <td>
                                <?= e($empleado['area']) ?>
                            </td>

                            <td>
                                <?= e($empleado['correo']) ?>
                            </td>

                            <td>
                                <?= e($empleado['telefono']) ?>
                            </td>

                            <td>

                                <?php if ((int)$empleado['activo'] === 1): ?>

                                    <span class="estado activo">
                                        ACTIVO
                                    </span>

                                <?php else: ?>

                                    <span class="estado inactivo">
                                        INACTIVO
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="acciones">

                                <form
                                    method="POST"
                                    style="display:inline;"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= e($empleado['id']) ?>"
                                    >

                                    <?php if ((int)$empleado['activo'] === 1): ?>

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="desactivar"
                                        >

                                        <button
                                            type="submit"
                                            class="boton rojo"
                                            onclick="return confirm('¿Desactivar este empleado?');"
                                        >
                                            Desactivar
                                        </button>

                                    <?php else: ?>

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="activar"
                                        >

                                        <button
                                            type="submit"
                                            class="boton verde"
                                        >
                                            Activar
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            style="text-align:center;padding:35px;"
                        >
                            No se encontraron empleados.
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