<?php

require_once __DIR__ . '/conexion.php';

$mensaje = "";
$tipo_mensaje = "success";


/* =========================================================
   VALORES INICIALES
========================================================= */

$codigo      = "";
$descripcion = "";
$tipo        = "PRODUCTO";
$unidad      = "PZA";
$categoria   = "";
$precio      = "0.00";
$iva         = "16.00";
$costo       = "0.00";
$notas       = "";
$activo      = 1;


/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $codigo      = strtoupper(trim($_POST["codigo"] ?? ""));
    $descripcion = trim($_POST["descripcion"] ?? "");
    $tipo        = trim($_POST["tipo"] ?? "PRODUCTO");
    $unidad      = strtoupper(trim($_POST["unidad"] ?? "PZA"));
    $categoria   = trim($_POST["categoria"] ?? "");
    $precio      = trim($_POST["precio"] ?? "0");
    $iva         = trim($_POST["iva"] ?? "16");
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

        $mensaje = "El precio debe ser un número válido mayor o igual a cero.";
        $tipo_mensaje = "error";

    } elseif (!is_numeric($iva) || $iva < 0 || $iva > 100) {

        $mensaje = "El IVA debe estar entre 0% y 100%.";
        $tipo_mensaje = "error";

    } elseif (!is_numeric($costo) || $costo < 0) {

        $mensaje = "El costo debe ser un número válido mayor o igual a cero.";
        $tipo_mensaje = "error";
    }


    /* =====================================================
       VERIFICAR CÓDIGO DUPLICADO
    ===================================================== */

    if ($mensaje === "") {

        $sql_existe = "
            SELECT id
            FROM productos
            WHERE codigo = ?
            LIMIT 1
        ";

        $stmt_existe = $conexion->prepare($sql_existe);

        if (!$stmt_existe) {

            $mensaje = "Error al verificar el código: "
                     . $conexion->error;

            $tipo_mensaje = "error";

        } else {

            $stmt_existe->bind_param(
                "s",
                $codigo
            );

            $stmt_existe->execute();

            $resultado = $stmt_existe->get_result();

            if ($resultado->num_rows > 0) {

                $mensaje = "Ya existe un producto o servicio con el código: "
                         . $codigo;

                $tipo_mensaje = "error";
            }

            $stmt_existe->close();
        }
    }


    /* =====================================================
       INSERTAR
    ===================================================== */

    if ($mensaje === "") {

        $sql = "
            INSERT INTO productos (
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
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {

            $mensaje = "Error al preparar el registro: "
                     . $conexion->error;

            $tipo_mensaje = "error";

        } else {

            $precio_num = (float) $precio;
            $iva_num    = (float) $iva;
            $costo_num  = (float) $costo;

            $stmt->bind_param(
                "sssssdddsi",
                $codigo,
                $descripcion,
                $tipo,
                $unidad,
                $categoria,
                $precio_num,
                $iva_num,
                $costo_num,
                $notas,
                $activo
            );

            if ($stmt->execute()) {

                $nuevo_id = $stmt->insert_id;

                $stmt->close();

                header(
                    "Location: editar_producto.php?id="
                    . $nuevo_id
                    . "&mensaje=creado"
                );

                exit;

            } else {

                $mensaje = "No fue posible registrar el producto o servicio: "
                         . $stmt->error;

                $tipo_mensaje = "error";
            }

            $stmt->close();
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
    Nuevo producto - Sistema Comercial
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
   ACTIVO
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

    <a href="productos.php">
        Productos y servicios
    </a>

    <a href="nuevo_producto.php" class="activo">
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
                Nuevo producto / servicio
            </h1>

            <p>
                Registra un producto o servicio para utilizarlo en cotizaciones.
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
                        Debe ser único.
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

                    <div class="ayuda">
                        Ejemplo: PZA, SERV, HORA, KIT.
                    </div>

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
                        placeholder="Ej. Seguridad, Instalación..."
                    >

                </div>

            </div>

        </div>


        <!-- =================================================
             PRECIOS
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

                    <div class="ayuda">
                        Podrás cambiarlo posteriormente.
                    </div>

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

                    <div class="ayuda">
                        Costo interno de referencia.
                    </div>

                </div>


                <div class="campo completo">

                    <label for="notas">
                        Notas
                    </label>

                    <textarea
                        name="notas"
                        id="notas"
                        maxlength="5000"
                        placeholder="Información adicional del producto o servicio..."
                    ><?= esc($notas) ?></textarea>

                </div>

            </div>

        </div>


        <!-- =================================================
             ESTATUS
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
                    <?= $activo ? "checked" : "" ?>
                >

                <strong>
                    Producto o servicio activo
                </strong>

            </label>

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
                class="btn btn-verde"
            >
                Guardar producto / servicio
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