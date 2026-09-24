<?php

require_once __DIR__ . '/conexion.php';

$mensaje = "";
$tipo_mensaje = "success";


/* =========================================================
   PROCESAR ACTIVAR / DESACTIVAR
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

    if ($id > 0) {

        if ($accion === "activar") {

            $sql = "
                UPDATE productos
                SET activo = 1
                WHERE id = ?
            ";

            $stmt = $conexion->prepare($sql);

            if ($stmt) {

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $mensaje = "El producto o servicio fue activado correctamente.";

                $stmt->close();
            }

        } elseif ($accion === "desactivar") {

            $sql = "
                UPDATE productos
                SET activo = 0
                WHERE id = ?
            ";

            $stmt = $conexion->prepare($sql);

            if ($stmt) {

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $mensaje = "El producto o servicio fue desactivado correctamente.";

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   FILTROS
========================================================= */

$buscar = trim($_GET["buscar"] ?? "");
$tipo = $_GET["tipo"] ?? "TODOS";
$estado = $_GET["estado"] ?? "TODOS";


/* =========================================================
   CONSTRUIR CONSULTA
========================================================= */

$sql = "
    SELECT
        id,
        codigo,
        descripcion,
        tipo,
        unidad,
        categoria,
        precio,
        iva,
        costo,
        notas,
        activo
    FROM productos
    WHERE 1 = 1
";

$parametros = [];
$tipos_parametros = "";


/* Búsqueda */

if ($buscar !== "") {

    $sql .= "
        AND (
            codigo LIKE ?
            OR descripcion LIKE ?
            OR categoria LIKE ?
        )
    ";

    $buscar_like = "%" . $buscar . "%";

    $parametros[] = $buscar_like;
    $parametros[] = $buscar_like;
    $parametros[] = $buscar_like;

    $tipos_parametros .= "sss";
}


/* Tipo */

if (in_array($tipo, ["PRODUCTO", "SERVICIO"], true)) {

    $sql .= "
        AND tipo = ?
    ";

    $parametros[] = $tipo;
    $tipos_parametros .= "s";
}


/* Estado */

if ($estado === "ACTIVOS") {

    $sql .= "
        AND activo = 1
    ";

} elseif ($estado === "INACTIVOS") {

    $sql .= "
        AND activo = 0
    ";
}


$sql .= "
    ORDER BY activo DESC, descripcion ASC
";


/* =========================================================
   EJECUTAR
========================================================= */

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

if (!empty($parametros)) {

    $stmt->bind_param(
        $tipos_parametros,
        ...$parametros
    );
}

$stmt->execute();

$resultados = $stmt->get_result();


/* =========================================================
   CONTADORES
========================================================= */

$sql_contadores = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) AS inactivos,
        SUM(CASE WHEN tipo = 'PRODUCTO' AND activo = 1 THEN 1 ELSE 0 END) AS productos,
        SUM(CASE WHEN tipo = 'SERVICIO' AND activo = 1 THEN 1 ELSE 0 END) AS servicios
    FROM productos
";

$res_contadores = $conexion->query($sql_contadores);

$contadores = $res_contadores
    ? $res_contadores->fetch_assoc()
    : [
        "total" => 0,
        "activos" => 0,
        "inactivos" => 0,
        "productos" => 0,
        "servicios" => 0
    ];


/* =========================================================
   FUNCIONES
========================================================= */

function esc($valor)
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


function moneda($valor)
{
    return "$" . number_format(
        (float) $valor,
        2,
        ".",
        ","
    );
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Productos y servicios - Sistema Comercial
</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #222;
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
    padding: 20px 15px;
    overflow-y: auto;
}

.logo {
    font-size: 21px;
    font-weight: bold;
    margin-bottom: 5px;
}

.sublogo {
    font-size: 12px;
    color: #cbd5e1;
    margin-bottom: 25px;
}

.menu-titulo {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    margin: 20px 8px 8px;
    font-weight: bold;
}

.sidebar a {
    display: block;
    text-decoration: none;
    color: #e5e7eb;
    padding: 11px 12px;
    margin-bottom: 4px;
    border-radius: 6px;
    font-size: 14px;
}

.sidebar a:hover {
    background: #374151;
    color: white;
}

.sidebar a.activo {
    background: #2563eb;
    color: white;
}


/* =====================================================
   CONTENIDO
===================================================== */

.contenido {
    margin-left: 240px;
    padding: 25px;
    min-height: 100vh;
}

.encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.encabezado h1 {
    margin: 0;
    font-size: 25px;
    color: #1f2937;
}

.encabezado p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* =====================================================
   BOTONES
===================================================== */

.acciones {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
}

.btn-azul {
    background: #2563eb;
    color: white;
}

.btn-azul:hover {
    background: #1d4ed8;
}

.btn-verde {
    background: #16a34a;
    color: white;
}

.btn-verde:hover {
    background: #15803d;
}

.btn-gris {
    background: #6b7280;
    color: white;
}

.btn-gris:hover {
    background: #4b5563;
}

.btn-rojo {
    background: #dc2626;
    color: white;
}

.btn-rojo:hover {
    background: #b91c1c;
}

.btn-pequeno {
    padding: 7px 10px;
    font-size: 12px;
}


/* =====================================================
   MENSAJE
===================================================== */

.mensaje {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #166534;
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: bold;
}


/* =====================================================
   TARJETAS
===================================================== */

.tarjetas {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.tarjeta {
    background: white;
    padding: 17px;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
}

.tarjeta-titulo {
    font-size: 11px;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: bold;
}

.tarjeta-numero {
    font-size: 25px;
    font-weight: bold;
    margin-top: 7px;
    color: #1f2937;
}


/* =====================================================
   FILTROS
===================================================== */

.filtros {
    background: white;
    padding: 18px;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    margin-bottom: 20px;
}

.form-filtros {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}

.campo label {
    display: block;
    font-size: 12px;
    font-weight: bold;
    color: #374151;
    margin-bottom: 5px;
}

.campo input,
.campo select {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}

.campo input:focus,
.campo select:focus {
    outline: none;
    border-color: #2563eb;
}


/* =====================================================
   TABLA
===================================================== */

.tabla-contenedor {
    background: white;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th {
    text-align: left;
    background: #f9fafb;
    color: #4b5563;
    font-size: 11px;
    text-transform: uppercase;
    padding: 12px 10px;
    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 12px 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    vertical-align: middle;
}

tr:hover td {
    background: #fafafa;
}


/* =====================================================
   ESTADOS
===================================================== */

.estado {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: bold;
}

.estado-activo {
    background: #dcfce7;
    color: #166534;
}

.estado-inactivo {
    background: #fee2e2;
    color: #991b1b;
}

.tipo-producto {
    background: #dbeafe;
    color: #1e40af;
}

.tipo-servicio {
    background: #f3e8ff;
    color: #7e22ce;
}


/* =====================================================
   ACCIONES TABLA
===================================================== */

.acciones-tabla {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.acciones-tabla form {
    margin: 0;
}


/* =====================================================
   VACÍO
===================================================== */

.vacio {
    text-align: center;
    padding: 45px 20px;
    color: #6b7280;
}

.vacio strong {
    display: block;
    color: #374151;
    font-size: 16px;
    margin-bottom: 8px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {

    .tarjetas {
        grid-template-columns: repeat(3, 1fr);
    }

    .form-filtros {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 15px;
    }

    .encabezado {
        flex-direction: column;
        align-items: flex-start;
    }

    .tarjetas {
        grid-template-columns: 1fr;
    }

    .form-filtros {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="logo">
        Sistema Comercial
    </div>

    <div class="sublogo">
        Gestión comercial
    </div>


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

    <a href="productos.php" class="activo">
        Productos y servicios
    </a>

    <a href="nuevo_producto.php">
        Nuevo producto
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


    <div class="menu-titulo">
        Reportes
    </div>

    <a href="reportes.php">
        Reportes
    </a>

    <a href="auditoria.php">
        Auditoría
    </a>

</aside>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="contenido">


    <div class="encabezado">

        <div>

            <h1>
                Productos y servicios
            </h1>

            <p>
                Administra el catálogo que utilizarás en las cotizaciones.
            </p>

        </div>

        <div class="acciones">

            <a
                href="nuevo_producto.php"
                class="btn btn-verde"
            >
                + Nuevo producto / servicio
            </a>

        </div>

    </div>


    <?php if ($mensaje !== ""): ?>

        <div class="mensaje">
            <?= esc($mensaje) ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         TARJETAS
    ================================================== -->

    <div class="tarjetas">

        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Total
            </div>

            <div class="tarjeta-numero">
                <?= (int) $contadores["total"] ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Activos
            </div>

            <div class="tarjeta-numero">
                <?= (int) $contadores["activos"] ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Inactivos
            </div>

            <div class="tarjeta-numero">
                <?= (int) $contadores["inactivos"] ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Productos
            </div>

            <div class="tarjeta-numero">
                <?= (int) $contadores["productos"] ?>
            </div>

        </div>


        <div class="tarjeta">

            <div class="tarjeta-titulo">
                Servicios
            </div>

            <div class="tarjeta-numero">
                <?= (int) $contadores["servicios"] ?>
            </div>

        </div>

    </div>


    <!-- =================================================
         FILTROS
    ================================================== -->

    <div class="filtros">

        <form method="GET"
              action=""
              class="form-filtros">


            <div class="campo">

                <label>
                    Buscar
                </label>

                <input
                    type="text"
                    name="buscar"
                    value="<?= esc($buscar) ?>"
                    placeholder="Código, descripción o categoría..."
                >

            </div>


            <div class="campo">

                <label>
                    Tipo
                </label>

                <select name="tipo">

                    <option value="TODOS"
                        <?= $tipo === "TODOS" ? "selected" : "" ?>>
                        Todos
                    </option>

                    <option value="PRODUCTO"
                        <?= $tipo === "PRODUCTO" ? "selected" : "" ?>>
                        Productos
                    </option>

                    <option value="SERVICIO"
                        <?= $tipo === "SERVICIO" ? "selected" : "" ?>>
                        Servicios
                    </option>

                </select>

            </div>


            <div class="campo">

                <label>
                    Estado
                </label>

                <select name="estado">

                    <option value="TODOS"
                        <?= $estado === "TODOS" ? "selected" : "" ?>>
                        Todos
                    </option>

                    <option value="ACTIVOS"
                        <?= $estado === "ACTIVOS" ? "selected" : "" ?>>
                        Activos
                    </option>

                    <option value="INACTIVOS"
                        <?= $estado === "INACTIVOS" ? "selected" : "" ?>>
                        Inactivos
                    </option>

                </select>

            </div>


            <div class="acciones">

                <button
                    type="submit"
                    class="btn btn-azul"
                >
                    Buscar
                </button>

                <a
                    href="productos.php"
                    class="btn btn-gris"
                >
                    Limpiar
                </a>

            </div>

        </form>

    </div>


    <!-- =================================================
         TABLA
    ================================================== -->

    <div class="tabla-contenedor">

        <?php if ($resultados->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Código
                        </th>

                        <th>
                            Descripción
                        </th>

                        <th>
                            Tipo
                        </th>

                        <th>
                            Unidad
                        </th>

                        <th>
                            Categoría
                        </th>

                        <th>
                            Precio
                        </th>

                        <th>
                            IVA
                        </th>

                        <th>
                            Costo
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php while ($producto = $resultados->fetch_assoc()): ?>

                        <tr>


                            <td>

                                <strong>
                                    <?= esc($producto["codigo"]) ?>
                                </strong>

                            </td>


                            <td>

                                <?= esc($producto["descripcion"]) ?>

                            </td>


                            <td>

                                <?php if ($producto["tipo"] === "PRODUCTO"): ?>

                                    <span class="estado tipo-producto">
                                        Producto
                                    </span>

                                <?php else: ?>

                                    <span class="estado tipo-servicio">
                                        Servicio
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= esc($producto["unidad"]) ?>

                            </td>


                            <td>

                                <?= $producto["categoria"]
                                    ? esc($producto["categoria"])
                                    : "-"
                                ?>

                            </td>


                            <td>

                                <strong>
                                    <?= moneda($producto["precio"]) ?>
                                </strong>

                            </td>


                            <td>

                                <?= number_format(
                                    (float) $producto["iva"],
                                    2
                                ) ?>%

                            </td>


                            <td>

                                <?= moneda($producto["costo"]) ?>

                            </td>


                            <td>

                                <?php if ((int) $producto["activo"] === 1): ?>

                                    <span class="estado estado-activo">
                                        ACTIVO
                                    </span>

                                <?php else: ?>

                                    <span class="estado estado-inactivo">
                                        INACTIVO
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="acciones-tabla">

                                    <a
                                        href="editar_producto.php?id=<?= (int) $producto["id"] ?>"
                                        class="btn btn-azul btn-pequeno"
                                    >
                                        Editar
                                    </a>


                                    <?php if ((int) $producto["activo"] === 1): ?>

                                        <form
                                            method="POST"
                                            action=""
                                            onsubmit="return confirm('¿Deseas desactivar este producto o servicio?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="desactivar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $producto["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-rojo btn-pequeno"
                                            >
                                                Desactivar
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            action=""
                                        >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="activar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $producto["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-verde btn-pequeno"
                                            >
                                                Activar
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="vacio">

                <strong>
                    No se encontraron productos o servicios.
                </strong>

                Prueba modificando los filtros o registra un nuevo elemento.

                <br><br>

                <a
                    href="nuevo_producto.php"
                    class="btn btn-verde"
                >
                    + Nuevo producto / servicio
                </a>

            </div>

        <?php endif; ?>

    </div>


</main>

</body>
</html>

<?php

$stmt->close();

?>