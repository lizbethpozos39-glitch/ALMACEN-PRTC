<?php

require_once __DIR__ . '/conexion.php';

$mensaje = "";
$tipo_mensaje = "success";


/* =========================================================
   ID
========================================================= */

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {

    header("Location: productos.php");
    exit;
}


/* =========================================================
   MENSAJE DE REDIRECCIÓN
========================================================= */

if (isset($_GET["mensaje"])) {

    if ($_GET["mensaje"] === "creado") {

        $mensaje = "El producto o servicio fue registrado correctamente.";

    } elseif ($_GET["mensaje"] === "actualizado") {

        $mensaje = "Los cambios fueron guardados correctamente.";
    }
}


/* =========================================================
   OBTENER PRODUCTO
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
    WHERE id = ?
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {

    die(
        "Error al preparar la consulta: "
        . $conexion->error
    );
}

$stmt->bind_param("i", $id);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    $stmt->close();

    header("Location: productos.php");
    exit;
}

$producto = $resultado->fetch_assoc();

$stmt->close();


/* =========================================================
   DATOS
========================================================= */

$codigo      = $producto["codigo"];
$descripcion = $producto["descripcion"];
$tipo        = $producto["tipo"];
$unidad      = $producto["unidad"];
$categoria   = $producto["categoria"];
$precio      = $producto["precio"];
$iva         = $producto["iva"];
$costo       = $producto["costo"];
$notas       = $producto["notas"];
$activo      = (int) $producto["activo"];


/* =========================================================
   PROCESAR ACTUALIZACIÓN
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $codigo      = strtoupper(trim($_POST["codigo"] ?? ""));
    $descripcion = trim($_POST["descripcion"] ?? "");
    $tipo        = trim($_POST["tipo"] ?? "");
    $unidad      = strtoupper(trim($_POST["unidad"] ?? ""));
    $categoria   = trim($_POST["categoria"] ?? "");
    $precio      = trim($_POST["precio"] ?? "0");
    $iva         = trim($_POST["iva"] ?? "0");
    $costo       = trim($_POST["costo"] ?? "0");
    $notas       = trim($_POST["notas"] ?? "");
    $activo      = isset($_POST["activo"]) ? 1 : 0;


    /* =====================================================
       VALIDACIONES
    ===================================================== */

    if ($codigo === "") {

        $mensaje = "El código es obligatorio.";
        $tipo_mensaje = "error";

    } elseif ($descripcion === "") {

        $mensaje = "La descripción es obligatoria.";
        $tipo_mensaje = "error";

    } elseif (!in_array($tipo, ["PRODUCTO", "SERVICIO"], true)) {

        $mensaje = "El tipo seleccionado no es válido.";
        $tipo_mensaje = "error";

    } elseif ($unidad === "") {

        $mensaje = "La unidad es obligatoria.";
        $tipo_mensaje = "error";

    } elseif (!is_numeric($precio) || $precio < 0) {

        $mensaje = "El precio no es válido.";
        $tipo_mensaje = "error";

    } elseif (!is_numeric($iva) || $iva < 0 || $iva > 100) {

        $mensaje = "El IVA debe estar entre 0% y 100%.";
        $tipo_mensaje = "error";

    } elseif (!is_numeric($costo) || $costo < 0) {

        $mensaje = "El costo no es válido.";
        $tipo_mensaje = "error";
    }


    /* =====================================================
       VERIFICAR CÓDIGO
    ===================================================== */

    if ($mensaje === "") {

        $sql_codigo = "
            SELECT id
            FROM productos
            WHERE codigo = ?
              AND id <> ?
            LIMIT 1
        ";

        $stmt_codigo = $conexion->prepare($sql_codigo);

        if (!$stmt_codigo) {

            $mensaje = "Error al verificar el código: "
                     . $conexion->error;

            $tipo_mensaje = "error";

        } else {

            $stmt_codigo->bind_param(
                "si",
                $codigo,
                $id
            );

            $stmt_codigo->execute();

            $resultado_codigo =
                $stmt_codigo->get_result();

            if ($resultado_codigo->num_rows > 0) {

                $mensaje =
                    "El código ya pertenece a otro producto o servicio.";

                $tipo_mensaje = "error";
            }

            $stmt_codigo->close();
        }
    }


    /* =====================================================
       ACTUALIZAR
    ===================================================== */

    if ($mensaje === "") {

        $sql_update = "
            UPDATE productos
            SET
                codigo = ?,
                descripcion = ?,
                tipo = ?,
                unidad = ?,
                categoria = ?,
                precio = ?,
                iva = ?,
                costo = ?,
                notas = ?,
                activo = ?
            WHERE id = ?
        ";

        $stmt_update =
            $conexion->prepare($sql_update);

        if (!$stmt_update) {

            $mensaje =
                "Error al preparar la actualización: "
                . $conexion->error;

            $tipo_mensaje = "error";

        } else {

            $precio_num = (float) $precio;
            $iva_num    = (float) $iva;
            $costo_num  = (float) $costo;

            $stmt_update->bind_param(
                "sssssdddsii",
                $codigo,
                $descripcion,
                $tipo,
                $unidad,
                $categoria,
                $precio_num,
                $iva_num,
                $costo_num,
                $notas,
                $activo,
                $id
            );


            if ($stmt_update->execute()) {

                $stmt_update->close();

                header(
                    "Location: editar_producto.php?id="
                    . $id
                    . "&mensaje=actualizado"
                );

                exit;

            } else {

                $mensaje =
                    "No fue posible actualizar: "
                    . $stmt_update->error;

                $tipo_mensaje = "error";
            }

            $stmt_update->close();
        }
    }
}


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

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Editar producto - Sistema Comercial
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

.btn {
    display: inline-block;
    padding: 10px 15px;
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


/* =====================================================
   MENSAJES
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
    border: 1px solid #fecaca;
    color: #991b1b;
}

.mensaje.success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #166534;
}


/* =====================================================
   FORMULARIO
===================================================== */

.formulario {
    background: white;
    padding: 25px;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
}

.seccion {
    margin-bottom: 28px;
}

.seccion-titulo {
    font-size: 17px;
    font-weight: bold;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
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
}

.campo input:focus,
.campo select:focus,
.campo textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37,99,235,.1);
}

.campo textarea {
    min-height: 110px;
    resize: vertical;
}

.ayuda {
    color: #6b7280;
    font-size: 11px;
    margin-top: 4px;
}


/* =====================================================
   ESTADO
===================================================== */

.check-activo {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 26px;
}

.check-activo input {
    width: 17px;
    height: 17px;
}


/* =====================================================
   BOTONES FINALES
===================================================== */

.botones-finales {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e5e7eb;
    margin-top: 25px;
    padding-top: 20px;
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
        padding: 15px;
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
                Editar producto / servicio
            </h1>

            <p>
                Modifica la información del elemento
                <strong>#<?= $id ?></strong>.
            </p>

        </div>


        <a
            href="productos.php"
            class="btn btn-gris"
        >
            ← Regresar
        </a>

    </div>


    <?php if ($mensaje !== ""): ?>

        <div class="mensaje <?= esc($tipo_mensaje) ?>">
            <?= esc($mensaje) ?>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        action=""
        class="formulario"
    >


        <!-- =================================================
             INFORMACIÓN GENERAL
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Información general
            </div>

            <div class="grid">


                <div class="campo">

                    <label for="codigo">
                        Código
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="codigo"
                        id="codigo"
                        maxlength="50"
                        value="<?= esc($codigo) ?>"
                        required
                    >

                    <div class="ayuda">
                        El código debe ser único.
                    </div>

                </div>


                <div class="campo">

                    <label for="tipo">
                        Tipo
                        <span>*</span>
                    </label>

                    <select
                        name="tipo"
                        id="tipo"
                        required
                    >

                        <option value="PRODUCTO"
                            <?= $tipo === "PRODUCTO" ? "selected" : "" ?>>
                            Producto
                        </option>

                        <option value="SERVICIO"
                            <?= $tipo === "SERVICIO" ? "selected" : "" ?>>
                            Servicio
                        </option>

                    </select>

                </div>


                <div class="campo">

                    <label for="unidad">
                        Unidad
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="unidad"
                        id="unidad"
                        maxlength="30"
                        value="<?= esc($unidad) ?>"
                        required
                    >

                </div>


                <div class="campo completo">

                    <label for="descripcion">
                        Descripción
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="descripcion"
                        id="descripcion"
                        maxlength="255"
                        value="<?= esc($descripcion) ?>"
                        required
                    >

                </div>


                <div class="campo">

                    <label for="categoria">
                        Categoría
                    </label>

                    <input
                        type="text"
                        name="categoria"
                        id="categoria"
                        maxlength="100"
                        value="<?= esc($categoria) ?>"
                    >

                </div>

            </div>

        </div>


        <!-- =================================================
             INFORMACIÓN COMERCIAL
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Información comercial
            </div>

            <div class="grid">


                <div class="campo">

                    <label for="precio">
                        Precio de venta
                        <span>*</span>
                    </label>

                    <input
                        type="number"
                        name="precio"
                        id="precio"
                        min="0"
                        step="0.01"
                        value="<?= esc($precio) ?>"
                        required
                    >

                    <div class="ayuda">
                        Precio antes de IVA.
                    </div>

                </div>


                <div class="campo">

                    <label for="iva">
                        IVA %
                        <span>*</span>
                    </label>

                    <select
                        name="iva"
                        id="iva"
                        required
                    >

                        <option value="0"
                            <?= (float)$iva === 0.0 ? "selected" : "" ?>>
                            0%
                        </option>

                        <option value="8"
                            <?= (float)$iva === 8.0 ? "selected" : "" ?>>
                            8%
                        </option>

                        <option value="16"
                            <?= (float)$iva === 16.0 ? "selected" : "" ?>>
                            16%
                        </option>

                    </select>

                </div>


                <div class="campo">

                    <label for="costo">
                        Costo
                    </label>

                    <input
                        type="number"
                        name="costo"
                        id="costo"
                        min="0"
                        step="0.01"
                        value="<?= esc($costo) ?>"
                    >

                </div>


                <div class="campo completo">

                    <label for="notas">
                        Notas
                    </label>

                    <textarea
                        name="notas"
                        id="notas"
                        maxlength="5000"
                    ><?= esc($notas) ?></textarea>

                </div>

            </div>

        </div>


        <!-- =================================================
             ESTADO
        ================================================== -->

        <div class="seccion">

            <div class="seccion-titulo">
                Estado
            </div>

            <label class="check-activo">

                <input
                    type="checkbox"
                    name="activo"
                    value="1"
                    <?= $activo === 1 ? "checked" : "" ?>
                >

                <strong>
                    Producto o servicio activo
                </strong>

            </label>

            <div class="ayuda">
                Los elementos inactivos no deberían aparecer
                posteriormente como opción para nuevas cotizaciones.
            </div>

        </div>


        <!-- =================================================
             BOTONES
        ================================================== -->

        <div class="botones-finales">

            <a
                href="productos.php"
                class="btn btn-gris"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="btn btn-azul"
            >
                Guardar cambios
            </button>

        </div>


    </form>

</main>


<script>

/* =====================================================
   CÓDIGO EN MAYÚSCULAS
===================================================== */

const codigo = document.getElementById("codigo");

if (codigo) {

    codigo.addEventListener("input", function () {

        this.value = this.value.toUpperCase();

    });

}


/* =====================================================
   UNIDAD EN MAYÚSCULAS
===================================================== */

const unidad = document.getElementById("unidad");

if (unidad) {

    unidad.addEventListener("input", function () {

        this.value = this.value.toUpperCase();

    });

}

</script>

</body>
</html>