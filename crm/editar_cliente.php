<?php

require_once __DIR__ . '/conexion.php';

$mensaje = "";
$tipo_mensaje = "success";

/* =========================================================
   VALIDAR ID
========================================================= */

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: clientes.php");
    exit;
}


/* =========================================================
   OBTENER CLIENTE
========================================================= */

$sql_cliente = "
    SELECT
        id,
        tipo_cliente,
        nombre,
        razon_social,
        rfc,
        contacto,
        telefono,
        telefono2,
        correo,
        calle,
        numero,
        colonia,
        ciudad,
        estado,
        codigo_postal,
        notas,
        estatus
    FROM clientes
    WHERE id = ?
    LIMIT 1
";

$stmt_cliente = $conexion->prepare($sql_cliente);

if (!$stmt_cliente) {
    die("Error al preparar la consulta: " . $conexion->error);
}

$stmt_cliente->bind_param("i", $id);
$stmt_cliente->execute();

$resultado_cliente = $stmt_cliente->get_result();

if ($resultado_cliente->num_rows === 0) {

    $stmt_cliente->close();

    header("Location: clientes.php");
    exit;
}

$cliente = $resultado_cliente->fetch_assoc();

$stmt_cliente->close();


/* =========================================================
   CARGAR DATOS INICIALES
========================================================= */

$tipo_cliente  = $cliente["tipo_cliente"];
$nombre        = $cliente["nombre"];
$razon_social  = $cliente["razon_social"];
$rfc           = $cliente["rfc"];
$contacto      = $cliente["contacto"];
$telefono      = $cliente["telefono"];
$telefono2     = $cliente["telefono2"];
$correo        = $cliente["correo"];
$calle         = $cliente["calle"];
$numero        = $cliente["numero"];
$colonia       = $cliente["colonia"];
$ciudad        = $cliente["ciudad"];
$estado        = $cliente["estado"];
$codigo_postal = $cliente["codigo_postal"];
$notas         = $cliente["notas"];
$estatus       = $cliente["estatus"];


/* =========================================================
   PROCESAR ACTUALIZACIÓN
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tipo_cliente  = trim($_POST["tipo_cliente"] ?? "");
    $nombre        = trim($_POST["nombre"] ?? "");
    $razon_social  = trim($_POST["razon_social"] ?? "");
    $rfc           = strtoupper(trim($_POST["rfc"] ?? ""));
    $contacto      = trim($_POST["contacto"] ?? "");
    $telefono      = trim($_POST["telefono"] ?? "");
    $telefono2     = trim($_POST["telefono2"] ?? "");
    $correo        = trim($_POST["correo"] ?? "");
    $calle         = trim($_POST["calle"] ?? "");
    $numero        = trim($_POST["numero"] ?? "");
    $colonia       = trim($_POST["colonia"] ?? "");
    $ciudad        = trim($_POST["ciudad"] ?? "");
    $estado        = trim($_POST["estado"] ?? "");
    $codigo_postal = trim($_POST["codigo_postal"] ?? "");
    $notas         = trim($_POST["notas"] ?? "");
    $estatus       = trim($_POST["estatus"] ?? "ACTIVO");


    /* =====================================================
       VALIDACIONES
    ===================================================== */

    if ($nombre === "") {

        $mensaje = "El nombre del cliente es obligatorio.";
        $tipo_mensaje = "error";

    } elseif (!in_array($tipo_cliente, ["PERSONA", "EMPRESA"], true)) {

        $mensaje = "El tipo de cliente seleccionado no es válido.";
        $tipo_mensaje = "error";

    } elseif (!in_array($estatus, ["ACTIVO", "INACTIVO"], true)) {

        $mensaje = "El estatus seleccionado no es válido.";
        $tipo_mensaje = "error";

    } elseif ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $mensaje = "El correo electrónico no tiene un formato válido.";
        $tipo_mensaje = "error";

    }


    /* =====================================================
       VERIFICAR RFC DUPLICADO
    ===================================================== */

    if ($mensaje === "" && $rfc !== "") {

        $sql_rfc = "
            SELECT id, nombre
            FROM clientes
            WHERE rfc = ?
              AND id <> ?
            LIMIT 1
        ";

        $stmt_rfc = $conexion->prepare($sql_rfc);

        if (!$stmt_rfc) {

            $mensaje = "Error al verificar el RFC: "
                     . $conexion->error;

            $tipo_mensaje = "error";

        } else {

            $stmt_rfc->bind_param(
                "si",
                $rfc,
                $id
            );

            $stmt_rfc->execute();

            $resultado_rfc = $stmt_rfc->get_result();

            if ($resultado_rfc->num_rows > 0) {

                $cliente_existente = $resultado_rfc->fetch_assoc();

                $mensaje = "El RFC ya está registrado en otro cliente: "
                         . $cliente_existente["nombre"];

                $tipo_mensaje = "error";
            }

            $stmt_rfc->close();
        }
    }


    /* =====================================================
       ACTUALIZAR CLIENTE
    ===================================================== */

    if ($mensaje === "") {

        $sql_update = "
            UPDATE clientes
            SET
                tipo_cliente = ?,
                nombre = ?,
                razon_social = ?,
                rfc = ?,
                contacto = ?,
                telefono = ?,
                telefono2 = ?,
                correo = ?,
                calle = ?,
                numero = ?,
                colonia = ?,
                ciudad = ?,
                estado = ?,
                codigo_postal = ?,
                notas = ?,
                estatus = ?
            WHERE id = ?
        ";

        $stmt_update = $conexion->prepare($sql_update);

        if (!$stmt_update) {

            $mensaje = "Error al preparar la actualización: "
                     . $conexion->error;

            $tipo_mensaje = "error";

        } else {

            $stmt_update->bind_param(
                "ssssssssssssssssi",
                $tipo_cliente,
                $nombre,
                $razon_social,
                $rfc,
                $contacto,
                $telefono,
                $telefono2,
                $correo,
                $calle,
                $numero,
                $colonia,
                $ciudad,
                $estado,
                $codigo_postal,
                $notas,
                $estatus,
                $id
            );

            if ($stmt_update->execute()) {

                $stmt_update->close();

                header(
                    "Location: detalle_cliente.php?id="
                    . $id
                    . "&mensaje=actualizado"
                );

                exit;

            } else {

                $mensaje = "No fue posible actualizar el cliente: "
                         . $stmt_update->error;

                $tipo_mensaje = "error";
            }

            $stmt_update->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Editar cliente - Sistema Comercial
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
           MENÚ LATERAL
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
            margin-bottom: 20px;
            gap: 15px;
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

        .acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        /* =====================================================
           BOTONES
        ===================================================== */

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-secundario {
            background: #6b7280;
            color: white;
        }

        .btn-secundario:hover {
            background: #4b5563;
        }

        .btn-guardar {
            background: #2563eb;
            color: white;
        }

        .btn-guardar:hover {
            background: #1d4ed8;
        }


        /* =====================================================
           MENSAJE
        ===================================================== */

        .mensaje {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .mensaje.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .mensaje.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }


        /* =====================================================
           FORMULARIO
        ===================================================== */

        .formulario {
            background: white;
            border-radius: 9px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 25px;
        }

        .seccion {
            margin-bottom: 30px;
        }

        .seccion:last-child {
            margin-bottom: 0;
        }

        .seccion-titulo {
            font-size: 17px;
            font-weight: bold;
            color: #1f2937;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 17px;
        }

        .campo {
            display: flex;
            flex-direction: column;
        }

        .campo.completo {
            grid-column: 1 / -1;
        }

        .campo label {
            font-size: 13px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 6px;
        }

        .campo label span {
            color: #dc2626;
        }

        .campo input,
        .campo select,
        .campo textarea {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: Arial, Helvetica, sans-serif;
            background: white;
        }

        .campo input:focus,
        .campo select:focus,
        .campo textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.10);
        }

        .campo textarea {
            resize: vertical;
            min-height: 100px;
        }

        .ayuda {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }


        /* =====================================================
           BOTONES FINALES
        ===================================================== */

        .botones-finales {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            margin-top: 25px;
            border-top: 1px solid #e5e7eb;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .grid {
                grid-template-columns: repeat(2, 1fr);
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
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .campo.completo {
                grid-column: auto;
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }

            .botones-finales {
                flex-direction: column;
            }

            .botones-finales .btn {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>

<body>


<!-- =========================================================
     MENÚ LATERAL
========================================================= -->

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

    <a href="clientes.php" class="activo">
        Clientes
    </a>

    <a href="nuevo_cliente.php">
        Nuevo cliente
    </a>


    <div class="menu-titulo">
        Productos y servicios
    </div>

    <a href="productos.php">
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


<!-- =========================================================
     CONTENIDO
========================================================= -->

<main class="contenido">


    <div class="encabezado">

        <div>

            <h1>
                Editar cliente
            </h1>

            <p>
                Modifica la información del cliente
                <strong>#<?= $id ?></strong>.
            </p>

        </div>


        <div class="acciones">

            <a href="detalle_cliente.php?id=<?= $id ?>"
               class="btn btn-secundario">
                ← Regresar al cliente
            </a>

        </div>

    </div>


    <?php if ($mensaje !== ""): ?>

        <div class="mensaje <?= htmlspecialchars($tipo_mensaje) ?>">

            <?= htmlspecialchars($mensaje) ?>

        </div>

    <?php endif; ?>


    <form method="POST"
          action=""
          class="formulario">


        <!-- =================================================
             DATOS GENERALES
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Datos generales
            </div>

            <div class="grid">


                <div class="campo">

                    <label for="tipo_cliente">
                        Tipo de cliente
                        <span>*</span>
                    </label>

                    <select
                        name="tipo_cliente"
                        id="tipo_cliente"
                        required
                    >

                        <option value="EMPRESA"
                            <?= $tipo_cliente === "EMPRESA" ? "selected" : "" ?>>
                            Empresa
                        </option>

                        <option value="PERSONA"
                            <?= $tipo_cliente === "PERSONA" ? "selected" : "" ?>>
                            Persona
                        </option>

                    </select>

                </div>


                <div class="campo">

                    <label for="nombre">
                        Nombre / Cliente
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="nombre"
                        id="nombre"
                        maxlength="150"
                        value="<?= htmlspecialchars($nombre) ?>"
                        required
                    >

                    <div class="ayuda">
                        Nombre comercial o nombre completo.
                    </div>

                </div>


                <div class="campo">

                    <label for="razon_social">
                        Razón social
                    </label>

                    <input
                        type="text"
                        name="razon_social"
                        id="razon_social"
                        maxlength="200"
                        value="<?= htmlspecialchars($razon_social) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="rfc">
                        RFC
                    </label>

                    <input
                        type="text"
                        name="rfc"
                        id="rfc"
                        maxlength="20"
                        value="<?= htmlspecialchars($rfc) ?>"
                        style="text-transform: uppercase;"
                    >

                    <div class="ayuda">
                        No puede coincidir con otro cliente.
                    </div>

                </div>


                <div class="campo">

                    <label for="estatus">
                        Estatus
                    </label>

                    <select
                        name="estatus"
                        id="estatus"
                    >

                        <option value="ACTIVO"
                            <?= $estatus === "ACTIVO" ? "selected" : "" ?>>
                            Activo
                        </option>

                        <option value="INACTIVO"
                            <?= $estatus === "INACTIVO" ? "selected" : "" ?>>
                            Inactivo
                        </option>

                    </select>

                </div>

            </div>

        </div>


        <!-- =================================================
             CONTACTO
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Datos de contacto
            </div>

            <div class="grid">


                <div class="campo">

                    <label for="contacto">
                        Persona de contacto
                    </label>

                    <input
                        type="text"
                        name="contacto"
                        id="contacto"
                        maxlength="150"
                        value="<?= htmlspecialchars($contacto) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="telefono">
                        Teléfono
                    </label>

                    <input
                        type="text"
                        name="telefono"
                        id="telefono"
                        maxlength="30"
                        value="<?= htmlspecialchars($telefono) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="telefono2">
                        Teléfono adicional
                    </label>

                    <input
                        type="text"
                        name="telefono2"
                        id="telefono2"
                        maxlength="30"
                        value="<?= htmlspecialchars($telefono2) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="correo">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        name="correo"
                        id="correo"
                        maxlength="150"
                        value="<?= htmlspecialchars($correo) ?>"
                    >

                </div>

            </div>

        </div>


        <!-- =================================================
             DOMICILIO
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Domicilio
            </div>

            <div class="grid">


                <div class="campo">

                    <label for="calle">
                        Calle
                    </label>

                    <input
                        type="text"
                        name="calle"
                        id="calle"
                        maxlength="150"
                        value="<?= htmlspecialchars($calle) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="numero">
                        Número
                    </label>

                    <input
                        type="text"
                        name="numero"
                        id="numero"
                        maxlength="30"
                        value="<?= htmlspecialchars($numero) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="colonia">
                        Colonia
                    </label>

                    <input
                        type="text"
                        name="colonia"
                        id="colonia"
                        maxlength="100"
                        value="<?= htmlspecialchars($colonia) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="ciudad">
                        Ciudad
                    </label>

                    <input
                        type="text"
                        name="ciudad"
                        id="ciudad"
                        maxlength="100"
                        value="<?= htmlspecialchars($ciudad) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="estado">
                        Estado
                    </label>

                    <input
                        type="text"
                        name="estado"
                        id="estado"
                        maxlength="100"
                        value="<?= htmlspecialchars($estado) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="codigo_postal">
                        Código postal
                    </label>

                    <input
                        type="text"
                        name="codigo_postal"
                        id="codigo_postal"
                        maxlength="10"
                        value="<?= htmlspecialchars($codigo_postal) ?>"
                    >

                </div>

            </div>

        </div>


        <!-- =================================================
             NOTAS
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Información adicional
            </div>

            <div class="grid">

                <div class="campo completo">

                    <label for="notas">
                        Notas
                    </label>

                    <textarea
                        name="notas"
                        id="notas"
                        maxlength="5000"
                        placeholder="Observaciones, información comercial, preferencias del cliente, etc."
                    ><?= htmlspecialchars($notas) ?></textarea>

                </div>

            </div>

        </div>


        <!-- =================================================
             BOTONES
        ================================================== -->

        <div class="botones-finales">

            <a
                href="detalle_cliente.php?id=<?= $id ?>"
                class="btn btn-secundario"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="btn btn-guardar"
            >
                Guardar cambios
            </button>

        </div>


    </form>

</main>


<script>

    /* =====================================================
       RFC EN MAYÚSCULAS
    ===================================================== */

    const rfc = document.getElementById("rfc");

    if (rfc) {

        rfc.addEventListener("input", function () {

            this.value = this.value.toUpperCase();

        });

    }


    /* =====================================================
       PLACEHOLDER RAZÓN SOCIAL
    ===================================================== */

    const tipoCliente =
        document.getElementById("tipo_cliente");

    const razonSocial =
        document.getElementById("razon_social");


    function actualizarRazonSocial() {

        if (!tipoCliente || !razonSocial) {
            return;
        }

        if (tipoCliente.value === "EMPRESA") {

            razonSocial.placeholder =
                "Razón social de la empresa";

        } else {

            razonSocial.placeholder =
                "Opcional";

        }
    }


    if (tipoCliente) {

        tipoCliente.addEventListener(
            "change",
            actualizarRazonSocial
        );

        actualizarRazonSocial();

    }

</script>

</body>
</html>