<?php

// ==========================================================
// CONEXIÓN
// ==========================================================

require_once __DIR__ . '/conexion.php';


// ==========================================================
// VARIABLES
// ==========================================================

$mensaje = "";
$tipo_mensaje = "";


// ==========================================================
// PROCESAR ACTIVAR / DESACTIVAR
// ==========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

    if ($id > 0) {

        if ($accion === "desactivar") {

            $stmt = $conexion->prepare("
                UPDATE clientes
                SET estatus = 'INACTIVO'
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $mensaje = "Cliente desactivado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "No fue posible desactivar el cliente.";
                $tipo_mensaje = "error";
            }

            $stmt->close();
        }


        elseif ($accion === "activar") {

            $stmt = $conexion->prepare("
                UPDATE clientes
                SET estatus = 'ACTIVO'
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $mensaje = "Cliente activado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "No fue posible activar el cliente.";
                $tipo_mensaje = "error";
            }

            $stmt->close();
        }
    }
}


// ==========================================================
// FILTROS
// ==========================================================

$buscar = trim($_GET["buscar"] ?? "");
$estatus = $_GET["estatus"] ?? "ACTIVO";


// ==========================================================
// CONSULTA DE CLIENTES
// ==========================================================

$clientes = [];

$sql = "
    SELECT
        id,
        tipo_cliente,
        nombre,
        razon_social,
        rfc,
        contacto,
        telefono,
        correo,
        ciudad,
        estado,
        estatus,
        fecha_registro
    FROM clientes
    WHERE 1 = 1
";

$params = [];
$tipos = "";


// ----------------------------------------------------------
// BUSCADOR
// ----------------------------------------------------------

if ($buscar !== "") {

    $sql .= "
        AND (
            nombre LIKE ?
            OR razon_social LIKE ?
            OR rfc LIKE ?
            OR contacto LIKE ?
            OR telefono LIKE ?
            OR correo LIKE ?
        )
    ";

    $termino = "%" . $buscar . "%";

    $params[] = $termino;
    $params[] = $termino;
    $params[] = $termino;
    $params[] = $termino;
    $params[] = $termino;
    $params[] = $termino;

    $tipos .= "ssssss";
}


// ----------------------------------------------------------
// FILTRO ESTATUS
// ----------------------------------------------------------

if ($estatus === "ACTIVO" || $estatus === "INACTIVO") {

    $sql .= " AND estatus = ? ";

    $params[] = $estatus;
    $tipos .= "s";
}


$sql .= "
    ORDER BY
        CASE
            WHEN estatus = 'ACTIVO' THEN 0
            ELSE 1
        END,
        nombre ASC
";


// ==========================================================
// EJECUTAR CONSULTA
// ==========================================================

$stmt = $conexion->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param(
        $tipos,
        ...$params
    );
}

$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {

    $clientes[] = $fila;
}

$stmt->close();


// ==========================================================
// CONTADORES
// ==========================================================

$total_activos = 0;
$total_inactivos = 0;

$resultado_contadores = $conexion->query("
    SELECT
        SUM(estatus = 'ACTIVO') AS activos,
        SUM(estatus = 'INACTIVO') AS inactivos
    FROM clientes
");

if ($resultado_contadores) {

    $contador = $resultado_contadores->fetch_assoc();

    $total_activos = (int)($contador["activos"] ?? 0);
    $total_inactivos = (int)($contador["inactivos"] ?? 0);
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

    <title>Clientes | Sistema Comercial</title>


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

            color: #333;
        }


        /* ==================================================
           MENU
        ================================================== */

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

            text-align: center;

            margin-bottom: 25px;
        }


        .logo h2 {

            margin: 0;

            font-size: 21px;
        }


        .logo span {

            display: block;

            margin-top: 5px;

            font-size: 12px;

            color: #cbd5e1;
        }


        .menu-titulo {

            font-size: 11px;

            color: #94a3b8;

            margin:
                20px 10px 8px;

            text-transform: uppercase;
        }


        .menu a {

            display: block;

            color: #e5e7eb;

            text-decoration: none;

            padding: 11px 12px;

            margin-bottom: 4px;

            border-radius: 6px;

            font-size: 14px;
        }


        .menu a:hover {

            background: #374151;
        }


        .menu a.activo {

            background: #2563eb;

            color: white;
        }


        /* ==================================================
           CONTENIDO
        ================================================== */

        .contenido {

            margin-left: 240px;

            padding: 25px;
        }


        .encabezado {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 20px;
        }


        .encabezado h1 {

            margin: 0;

            font-size: 26px;

            color: #1f2937;
        }


        .encabezado p {

            margin:
                5px 0 0;

            color: #6b7280;

            font-size: 14px;
        }


        /* ==================================================
           BOTONES
        ================================================== */

        .boton {

            display: inline-block;

            padding:
                10px 15px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 13px;

            border: none;

            cursor: pointer;
        }


        .boton-principal {

            background: #2563eb;

            color: white;
        }


        .boton-principal:hover {

            background: #1d4ed8;
        }


        .boton-secundario {

            background: #e5e7eb;

            color: #374151;
        }


        .boton-editar {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .boton-ver {

            background: #e0e7ff;

            color: #4338ca;
        }


        .boton-desactivar {

            background: #fee2e2;

            color: #991b1b;
        }


        .boton-activar {

            background: #dcfce7;

            color: #166534;
        }


        /* ==================================================
           MENSAJES
        ================================================== */

        .mensaje {

            padding: 13px 16px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 13px;
        }


        .mensaje.success {

            background: #dcfce7;

            color: #166534;

            border:
                1px solid #bbf7d0;
        }


        .mensaje.error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;
        }


        /* ==================================================
           RESUMEN
        ================================================== */

        .resumen {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;

            margin-bottom: 20px;
        }


        .resumen-card {

            background: white;

            padding: 17px 20px;

            border-radius: 8px;

            box-shadow:
                0 2px 7px
                rgba(0,0,0,0.05);

            border-left:
                5px solid #2563eb;
        }


        .resumen-card.inactivos {

            border-left-color:
                #9ca3af;
        }


        .resumen-titulo {

            font-size: 12px;

            color: #6b7280;
        }


        .resumen-numero {

            margin-top: 5px;

            font-size: 26px;

            font-weight: bold;
        }


        /* ==================================================
           FILTROS
        ================================================== */

        .filtros {

            background: white;

            padding: 18px;

            border-radius: 9px;

            box-shadow:
                0 2px 7px
                rgba(0,0,0,0.05);

            margin-bottom: 20px;
        }


        .formulario-filtros {

            display: grid;

            grid-template-columns:
                1fr 180px auto auto;

            gap: 10px;

            align-items: end;
        }


        .campo label {

            display: block;

            font-size: 12px;

            font-weight: bold;

            color: #4b5563;

            margin-bottom: 6px;
        }


        .campo input,
        .campo select {

            width: 100%;

            padding: 10px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            font-size: 13px;

            background: white;
        }


        .campo input:focus,
        .campo select:focus {

            outline: none;

            border-color:
                #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37,99,235,.10);
        }


        /* ==================================================
           TABLA
        ================================================== */

        .panel {

            background: white;

            border-radius: 9px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,0.06);

            overflow: hidden;
        }


        .panel-header {

            padding:
                17px 20px;

            border-bottom:
                1px solid #e5e7eb;

            display: flex;

            justify-content:
                space-between;

            align-items: center;
        }


        .panel-header h2 {

            margin: 0;

            font-size: 17px;
        }


        .cantidad {

            font-size: 12px;

            color: #6b7280;
        }


        .tabla-contenedor {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 1000px;
        }


        th {

            background: #f8fafc;

            color: #64748b;

            font-size: 11px;

            text-transform:
                uppercase;

            text-align: left;

            padding: 12px;
        }


        td {

            padding: 12px;

            border-top:
                1px solid #f1f5f9;

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


        .rfc {

            font-family:
                Arial,
                sans-serif;

            color: #475569;

            font-size: 12px;
        }


        .tipo {

            display: inline-block;

            padding:
                4px 7px;

            border-radius: 15px;

            background: #f1f5f9;

            color: #475569;

            font-size: 10px;

            font-weight: bold;
        }


        .estatus {

            display: inline-block;

            padding:
                5px 8px;

            border-radius: 15px;

            font-size: 10px;

            font-weight: bold;
        }


        .estatus-activo {

            background: #dcfce7;

            color: #166534;
        }


        .estatus-inactivo {

            background: #f3f4f6;

            color: #6b7280;
        }


        .acciones {

            display: flex;

            gap: 5px;

            flex-wrap: wrap;
        }


        .acciones .boton {

            padding:
                7px 9px;

            font-size: 11px;
        }


        .sin-datos {

            text-align: center;

            padding: 45px 20px;

            color: #9ca3af;

            font-size: 14px;
        }


        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 900px) {

            .formulario-filtros {

                grid-template-columns:
                    1fr 1fr;
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

                display: block;
            }


            .encabezado .boton {

                margin-top: 15px;
            }


            .resumen {

                grid-template-columns:
                    1fr;
            }


            .formulario-filtros {

                grid-template-columns:
                    1fr;
            }

        }

    </style>

</head>


<body>


<!-- ======================================================
     MENU LATERAL
====================================================== -->

<aside class="sidebar">

    <div class="logo">

        <h2>GRUPO PROTEC</h2>

        <span>
            Sistema Comercial
        </span>

    </div>


    <nav class="menu">


        <div class="menu-titulo">
            Principal
        </div>


        <a href="index.php">
            🏠 &nbsp; Panel principal
        </a>


        <div class="menu-titulo">
            Clientes
        </div>


        <a
            href="clientes.php"
            class="activo"
        >
            👥 &nbsp; Clientes
        </a>


        <div class="menu-titulo">
            Comercial
        </div>


        <a href="cotizaciones.php">
            📄 &nbsp; Cotizaciones
        </a>


        <a href="seguimientos.php">
            📞 &nbsp; Seguimiento comercial
        </a>


        <div class="menu-titulo">
            Catálogo
        </div>


        <a href="productos.php">
            📦 &nbsp; Productos / Servicios
        </a>


        <div class="menu-titulo">
            Reportes
        </div>


        <a href="reportes.php">
            📊 &nbsp; Reportes
        </a>


        <div class="menu-titulo">
            Sistema
        </div>


        <a href="auditoria.php">
            🔎 &nbsp; Auditoría
        </a>


    </nav>

</aside>


<!-- ======================================================
     CONTENIDO
====================================================== -->

<main class="contenido">


    <!-- ==================================================
         ENCABEZADO
    ================================================== -->

    <div class="encabezado">

        <div>

            <h1>
                Clientes
            </h1>

            <p>
                Administración de clientes del sistema comercial
            </p>

        </div>


        <a
            href="nuevo_cliente.php"
            class="boton boton-principal"
        >
            + Nuevo cliente
        </a>

    </div>


    <!-- ==================================================
         MENSAJE
    ================================================== -->

    <?php if ($mensaje !== ""): ?>

        <div
            class="mensaje <?php echo $tipo_mensaje; ?>"
        >

            <?php
            echo htmlspecialchars($mensaje);
            ?>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         RESUMEN
    ================================================== -->

    <section class="resumen">


        <div class="resumen-card">

            <div class="resumen-titulo">
                CLIENTES ACTIVOS
            </div>

            <div class="resumen-numero">
                <?php echo $total_activos; ?>
            </div>

        </div>


        <div class="resumen-card inactivos">

            <div class="resumen-titulo">
                CLIENTES INACTIVOS
            </div>

            <div class="resumen-numero">
                <?php echo $total_inactivos; ?>
            </div>

        </div>


    </section>


    <!-- ==================================================
         FILTROS
    ================================================== -->

    <section class="filtros">


        <form
            method="GET"
            class="formulario-filtros"
        >


            <div class="campo">

                <label>
                    Buscar cliente
                </label>

                <input
                    type="text"
                    name="buscar"
                    value="<?php
                        echo htmlspecialchars($buscar);
                    ?>"
                    placeholder="Nombre, RFC, contacto, teléfono o correo..."
                >

            </div>


            <div class="campo">

                <label>
                    Estatus
                </label>

                <select name="estatus">

                    <option
                        value="ACTIVO"
                        <?php
                        echo $estatus === "ACTIVO"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Activos
                    </option>


                    <option
                        value="INACTIVO"
                        <?php
                        echo $estatus === "INACTIVO"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Inactivos
                    </option>


                    <option
                        value="TODOS"
                        <?php
                        echo $estatus === "TODOS"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Todos
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="boton boton-principal"
            >
                Buscar
            </button>


            <a
                href="clientes.php"
                class="boton boton-secundario"
            >
                Limpiar
            </a>


        </form>


    </section>


    <!-- ==================================================
         TABLA
    ================================================== -->

    <section class="panel">


        <div class="panel-header">

            <h2>
                Clientes registrados
            </h2>


            <span class="cantidad">

                <?php echo count($clientes); ?>

                resultado(s)

            </span>

        </div>


        <div class="tabla-contenedor">


            <?php if (count($clientes) > 0): ?>


                <table>


                    <thead>

                        <tr>

                            <th>
                                Cliente
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                RFC
                            </th>

                            <th>
                                Contacto
                            </th>

                            <th>
                                Teléfono
                            </th>

                            <th>
                                Correo
                            </th>

                            <th>
                                Ubicación
                            </th>

                            <th>
                                Estatus
                            </th>

                            <th>
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($clientes as $cliente): ?>


                            <tr>


                                <!-- CLIENTE -->

                                <td>

                                    <div class="nombre">

                                        <?php
                                        echo htmlspecialchars(
                                            $cliente["nombre"]
                                        );
                                        ?>

                                    </div>


                                    <?php
                                    if (
                                        !empty(
                                            $cliente["razon_social"]
                                        )
                                    ):
                                    ?>

                                        <div
                                            style="
                                                font-size:11px;
                                                color:#64748b;
                                                margin-top:3px;
                                            "
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $cliente[
                                                    "razon_social"
                                                ]
                                            );
                                            ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- TIPO -->

                                <td>

                                    <span class="tipo">

                                        <?php
                                        echo htmlspecialchars(
                                            $cliente[
                                                "tipo_cliente"
                                            ]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- RFC -->

                                <td>

                                    <span class="rfc">

                                        <?php
                                        echo htmlspecialchars(
                                            $cliente["rfc"]
                                                ?: "—"
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- CONTACTO -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $cliente["contacto"]
                                            ?: "—"
                                    );
                                    ?>

                                </td>


                                <!-- TELÉFONO -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $cliente["telefono"]
                                            ?: "—"
                                    );
                                    ?>

                                </td>


                                <!-- CORREO -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $cliente["correo"]
                                            ?: "—"
                                    );
                                    ?>

                                </td>


                                <!-- UBICACIÓN -->

                                <td>

                                    <?php

                                    $ubicacion = [];

                                    if (
                                        !empty(
                                            $cliente["ciudad"]
                                        )
                                    ) {
                                        $ubicacion[] =
                                            $cliente["ciudad"];
                                    }

                                    if (
                                        !empty(
                                            $cliente["estado"]
                                        )
                                    ) {
                                        $ubicacion[] =
                                            $cliente["estado"];
                                    }

                                    echo htmlspecialchars(
                                        !empty($ubicacion)
                                            ? implode(
                                                ", ",
                                                $ubicacion
                                            )
                                            : "—"
                                    );

                                    ?>

                                </td>


                                <!-- ESTATUS -->

                                <td>

                                    <?php if (
                                        $cliente["estatus"]
                                        === "ACTIVO"
                                    ): ?>

                                        <span
                                            class="
                                                estatus
                                                estatus-activo
                                            "
                                        >
                                            ACTIVO
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                estatus
                                                estatus-inactivo
                                            "
                                        >
                                            INACTIVO
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACCIONES -->

                                <td>

                                    <div class="acciones">


                                        <a
                                            href="detalle_cliente.php?id=<?php
                                                echo (int)
                                                    $cliente["id"];
                                            ?>"
                                            class="
                                                boton
                                                boton-ver
                                            "
                                        >
                                            Ver
                                        </a>


                                        <a
                                            href="editar_cliente.php?id=<?php
                                                echo (int)
                                                    $cliente["id"];
                                            ?>"
                                            class="
                                                boton
                                                boton-editar
                                            "
                                        >
                                            Editar
                                        </a>


                                        <?php if (
                                            $cliente["estatus"]
                                            === "ACTIVO"
                                        ): ?>


                                            <form
                                                method="POST"
                                                style="
                                                    display:inline;
                                                "
                                                onsubmit="
                                                    return confirm(
                                                        '¿Deseas desactivar este cliente?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="accion"
                                                    value="desactivar"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php
                                                        echo (int)
                                                            $cliente[
                                                                "id"
                                                            ];
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        boton
                                                        boton-desactivar
                                                    "
                                                >
                                                    Desactivar
                                                </button>

                                            </form>


                                        <?php else: ?>


                                            <form
                                                method="POST"
                                                style="
                                                    display:inline;
                                                "
                                                onsubmit="
                                                    return confirm(
                                                        '¿Deseas activar este cliente?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="accion"
                                                    value="activar"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php
                                                        echo (int)
                                                            $cliente[
                                                                "id"
                                                            ];
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        boton
                                                        boton-activar
                                                    "
                                                >
                                                    Activar
                                                </button>

                                            </form>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            <?php else: ?>


                <div class="sin-datos">

                    <?php if ($buscar !== ""): ?>

                        No se encontraron clientes
                        con los criterios de búsqueda.

                    <?php else: ?>

                        No hay clientes registrados
                        todavía.

                    <?php endif; ?>


                    <div style="margin-top:15px;">

                        <a
                            href="nuevo_cliente.php"
                            class="
                                boton
                                boton-principal
                            "
                        >
                            + Registrar primer cliente
                        </a>

                    </div>

                </div>


            <?php endif; ?>


        </div>

    </section>


</main>


</body>

</html>
