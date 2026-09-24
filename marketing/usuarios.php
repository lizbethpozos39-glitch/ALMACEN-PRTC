<?php

// ==========================================================
// MARKETING - ADMINISTRACIÓN DE USUARIOS
// ==========================================================

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_admin_marketing();


// ==========================================================
// DATOS DE SESIÓN
// ==========================================================

$empleado_actual_id = (int)($_SESSION['empleado_id'] ?? 0);
$usuario_actual_id  = (int)($_SESSION['usuario_id'] ?? 0);

$nombre_actual = empleado_actual();
$rol_actual    = strtoupper(trim(rol_marketing()));

$mensaje = "";
$error   = "";


// ==========================================================
// FUNCIÓN DE AUDITORÍA
// ==========================================================

function registrar_auditoria_marketing(
    $conexion,
    $accion,
    $modulo,
    $referencia_id,
    $descripcion
) {

    $empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

    $stmt = $conexion->prepare("
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
            ?,
            ?,
            ?
        )
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "issis",
        $empleado_id,
        $accion,
        $modulo,
        $referencia_id,
        $descripcion
    );

    $resultado = $stmt->execute();

    $stmt->close();

    return $resultado;
}


// ==========================================================
// CAMBIO DE ROL MARKETING
// ==========================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["cambiar_rol_marketing"])
) {

    $marketing_id = (int)($_POST["marketing_id"] ?? 0);
    $nuevo_rol = strtoupper(
        trim($_POST["nuevo_rol_marketing"] ?? "")
    );

    $roles_validos = [
        "ADMIN",
        "GERENCIA",
        "MARKETING",
        "AUDITOR"
    ];


    // ------------------------------------------------------
    // VALIDACIONES
    // ------------------------------------------------------

    if ($marketing_id <= 0) {

        $error = "Usuario de Marketing no válido.";

    } elseif (
        !in_array(
            $nuevo_rol,
            $roles_validos,
            true
        )
    ) {

        $error = "El rol de Marketing seleccionado no es válido.";

    } else {

        // --------------------------------------------------
        // OBTENER DATOS ACTUALES
        // --------------------------------------------------

        $stmt = $conexion->prepare("
            SELECT
                mu.id,
                mu.empleado_id,
                mu.rol_marketing,
                mu.activo,
                e.nombre AS empleado,
                e.numero_empleado,
                u.usuario
            FROM marketing_usuarios mu
            INNER JOIN empleados e
                ON e.id = mu.empleado_id
            LEFT JOIN usuarios u
                ON u.empleado_id = mu.empleado_id
            WHERE mu.id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "No fue posible consultar el usuario de Marketing.";

        } else {

            $stmt->bind_param(
                "i",
                $marketing_id
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 0) {

                $error = "El usuario de Marketing no existe.";

                $stmt->close();

            } else {

                $datos = $resultado->fetch_assoc();

                $empleado_id = (int)$datos["empleado_id"];

                $rol_anterior = strtoupper(
                    trim($datos["rol_marketing"])
                );

                $nombre_empleado = $datos["empleado"];
                $usuario = $datos["usuario"] ?? "";

                $stmt->close();


                // --------------------------------------------------
                // PROTEGER AL ADMINISTRADOR ACTUAL
                // --------------------------------------------------

                if (
                    $empleado_id === $empleado_actual_id
                    && $rol_anterior === "ADMIN"
                    && $nuevo_rol !== "ADMIN"
                ) {

                    $error =
                        "No puedes quitarte el rol ADMIN de Marketing "
                        . "mientras estás conectado con esta cuenta.";

                } elseif ($rol_anterior === $nuevo_rol) {

                    $mensaje =
                        "El usuario ya tiene el rol "
                        . $nuevo_rol
                        . ".";

                } else {

                    // --------------------------------------------------
                    // ACTUALIZAR
                    // --------------------------------------------------

                    $stmt = $conexion->prepare("
                        UPDATE marketing_usuarios
                        SET rol_marketing = ?
                        WHERE id = ?
                    ");

                    if (!$stmt) {

                        $error =
                            "No fue posible preparar el cambio de rol.";

                    } else {

                        $stmt->bind_param(
                            "si",
                            $nuevo_rol,
                            $marketing_id
                        );

                        if ($stmt->execute()) {

                            $mensaje =
                                "Rol de Marketing actualizado correctamente. "
                                . $nombre_empleado
                                . " ahora tiene el rol "
                                . $nuevo_rol
                                . ".";


                            registrar_auditoria_marketing(
                                $conexion,
                                "CAMBIO_ROL_MARKETING",
                                "USUARIOS",
                                $marketing_id,
                                "Se cambió el rol de Marketing del usuario "
                                . "'{$usuario}' ({$nombre_empleado}) "
                                . "de '{$rol_anterior}' a '{$nuevo_rol}'."
                            );

                        } else {

                            $error =
                                "No fue posible actualizar el rol: "
                                . $stmt->error;
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}


// ==========================================================
// ACTIVAR / DESACTIVAR ACCESO MARKETING
// ==========================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["cambiar_estado_marketing"])
) {

    $marketing_id = (int)($_POST["marketing_id"] ?? 0);


    if ($marketing_id <= 0) {

        $error = "Usuario de Marketing no válido.";

    } else {

        // --------------------------------------------------
        // OBTENER DATOS
        // --------------------------------------------------

        $stmt = $conexion->prepare("
            SELECT
                mu.id,
                mu.empleado_id,
                mu.rol_marketing,
                mu.activo,
                e.nombre AS empleado,
                u.usuario
            FROM marketing_usuarios mu
            INNER JOIN empleados e
                ON e.id = mu.empleado_id
            LEFT JOIN usuarios u
                ON u.empleado_id = mu.empleado_id
            WHERE mu.id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                "No fue posible consultar el usuario de Marketing.";

        } else {

            $stmt->bind_param(
                "i",
                $marketing_id
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 0) {

                $error =
                    "El usuario de Marketing no existe.";

                $stmt->close();

            } else {

                $datos = $resultado->fetch_assoc();

                $empleado_id = (int)$datos["empleado_id"];
                $estado_anterior = (int)$datos["activo"];

                $nombre_empleado = $datos["empleado"];
                $usuario = $datos["usuario"] ?? "";

                $stmt->close();


                // --------------------------------------------------
                // PROTEGER CUENTA ACTUAL
                // --------------------------------------------------

                if (
                    $empleado_id === $empleado_actual_id
                ) {

                    $error =
                        "No puedes desactivar tu propio acceso "
                        . "al módulo de Marketing.";

                } else {

                    $nuevo_estado =
                        $estado_anterior === 1
                        ? 0
                        : 1;


                    // --------------------------------------------------
                    // ACTUALIZAR ESTADO
                    // --------------------------------------------------

                    $stmt = $conexion->prepare("
                        UPDATE marketing_usuarios
                        SET activo = ?
                        WHERE id = ?
                    ");

                    if (!$stmt) {

                        $error =
                            "No fue posible preparar el cambio de estado.";

                    } else {

                        $stmt->bind_param(
                            "ii",
                            $nuevo_estado,
                            $marketing_id
                        );

                        if ($stmt->execute()) {

                            if ($nuevo_estado === 1) {

                                $mensaje =
                                    "Acceso a Marketing activado correctamente "
                                    . "para {$nombre_empleado}.";

                                $accion =
                                    "ACTIVAR_ACCESO_MARKETING";

                            } else {

                                $mensaje =
                                    "Acceso a Marketing desactivado correctamente "
                                    . "para {$nombre_empleado}.";

                                $accion =
                                    "DESACTIVAR_ACCESO_MARKETING";
                            }


                            registrar_auditoria_marketing(
                                $conexion,
                                $accion,
                                "USUARIOS",
                                $marketing_id,
                                "Se "
                                . (
                                    $nuevo_estado === 1
                                    ? "activó"
                                    : "desactivó"
                                )
                                . " el acceso al módulo de Marketing "
                                . "para el usuario '{$usuario}' "
                                . "({$nombre_empleado})."
                            );

                        } else {

                            $error =
                                "No fue posible cambiar el estado: "
                                . $stmt->error;
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}


// ==========================================================
// AGREGAR USUARIO A MARKETING
// ==========================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["agregar_marketing"])
) {

    $empleado_id = (int)($_POST["empleado_id"] ?? 0);

    $nuevo_rol = strtoupper(
        trim($_POST["rol_marketing"] ?? "MARKETING")
    );

    $roles_validos = [
        "ADMIN",
        "GERENCIA",
        "MARKETING",
        "AUDITOR"
    ];


    // ------------------------------------------------------
    // VALIDACIONES
    // ------------------------------------------------------

    if ($empleado_id <= 0) {

        $error =
            "Debes seleccionar un empleado.";

    } elseif (
        !in_array(
            $nuevo_rol,
            $roles_validos,
            true
        )
    ) {

        $error =
            "El rol de Marketing seleccionado no es válido.";

    } else {

        // --------------------------------------------------
        // VERIFICAR EMPLEADO
        // --------------------------------------------------

        $stmt = $conexion->prepare("
            SELECT
                id,
                nombre,
                numero_empleado
            FROM empleados
            WHERE id = ?
              AND activo = 1
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                "No fue posible verificar el empleado.";

        } else {

            $stmt->bind_param(
                "i",
                $empleado_id
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 0) {

                $error =
                    "El empleado seleccionado no existe "
                    . "o está inactivo.";

                $stmt->close();

            } else {

                $empleado = $resultado->fetch_assoc();

                $nombre_empleado =
                    $empleado["nombre"];

                $stmt->close();


                // --------------------------------------------------
                // VERIFICAR SI YA TIENE ACCESO
                // --------------------------------------------------

                $stmt = $conexion->prepare("
                    SELECT
                        id,
                        activo,
                        rol_marketing
                    FROM marketing_usuarios
                    WHERE empleado_id = ?
                    LIMIT 1
                ");

                if (!$stmt) {

                    $error =
                        "No fue posible verificar el acceso existente.";

                } else {

                    $stmt->bind_param(
                        "i",
                        $empleado_id
                    );

                    $stmt->execute();

                    $resultado = $stmt->get_result();

                    if ($resultado->num_rows > 0) {

                        $datos_existente =
                            $resultado->fetch_assoc();

                        if (
                            (int)$datos_existente["activo"] === 0
                        ) {

                            $error =
                                "Este empleado ya tiene un registro "
                                . "de Marketing, pero está desactivado. "
                                . "Puedes activarlo desde la lista de usuarios.";

                        } else {

                            $error =
                                "Este empleado ya tiene acceso "
                                . "al módulo de Marketing.";
                        }

                    } else {

                        // --------------------------------------------------
                        // INSERTAR
                        // --------------------------------------------------

                        $stmt_insert = $conexion->prepare("
                            INSERT INTO marketing_usuarios
                            (
                                empleado_id,
                                rol_marketing,
                                activo
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                1
                            )
                        ");

                        if (!$stmt_insert) {

                            $error =
                                "No fue posible preparar el acceso "
                                . "de Marketing.";

                        } else {

                            $stmt_insert->bind_param(
                                "is",
                                $empleado_id,
                                $nuevo_rol
                            );

                            if ($stmt_insert->execute()) {

                                $nuevo_id =
                                    $stmt_insert->insert_id;

                                $mensaje =
                                    "Acceso a Marketing agregado correctamente "
                                    . "para {$nombre_empleado} "
                                    . "con rol {$nuevo_rol}.";


                                registrar_auditoria_marketing(
                                    $conexion,
                                    "AGREGAR_USUARIO_MARKETING",
                                    "USUARIOS",
                                    $nuevo_id,
                                    "Se agregó al empleado "
                                    . "'{$nombre_empleado}' "
                                    . "al módulo de Marketing con rol "
                                    . "'{$nuevo_rol}'."
                                );

                            } else {

                                $error =
                                    "No fue posible agregar el acceso: "
                                    . $stmt_insert->error;
                            }

                            $stmt_insert->close();
                        }
                    }

                    $stmt->close();
                }
            }
        }
    }
}


// ==========================================================
// EMPLEADOS DISPONIBLES PARA MARKETING
// ==========================================================

$empleados_disponibles = [];

$sql_empleados = "
    SELECT
        e.id,
        e.nombre,
        e.numero_empleado,
        e.puesto,
        a.nombre AS area
    FROM empleados e
    LEFT JOIN areas a
        ON a.id = e.area_id
    LEFT JOIN marketing_usuarios mu
        ON mu.empleado_id = e.id
    WHERE e.activo = 1
      AND mu.id IS NULL
    ORDER BY e.nombre ASC
";

$resultado_empleados =
    $conexion->query($sql_empleados);

if ($resultado_empleados) {

    while (
        $fila =
        $resultado_empleados->fetch_assoc()
    ) {

        $empleados_disponibles[] =
            $fila;
    }
}


// ==========================================================
// USUARIOS DE MARKETING
// ==========================================================

$sql_usuarios_marketing = "
    SELECT
        mu.id,
        mu.empleado_id,
        mu.rol_marketing,
        mu.activo,
        mu.fecha_registro,

        e.nombre AS empleado,
        e.numero_empleado,
        e.puesto,
        e.activo AS empleado_activo,

        a.nombre AS area,

        u.id AS usuario_id,
        u.usuario,
        u.rol AS rol_inventario,
        u.activo AS usuario_activo,
        u.ultimo_acceso

    FROM marketing_usuarios mu

    INNER JOIN empleados e
        ON e.id = mu.empleado_id

    LEFT JOIN areas a
        ON a.id = e.area_id

    LEFT JOIN usuarios u
        ON u.empleado_id = e.id

    ORDER BY e.nombre ASC
";

$resultado_usuarios_marketing =
    $conexion->query($sql_usuarios_marketing);


// ==========================================================
// ESTADÍSTICAS
// ==========================================================

$total_marketing = 0;
$total_activos = 0;
$total_inactivos = 0;
$total_admin = 0;
$total_gerencia = 0;
$total_marketing_rol = 0;
$total_auditor = 0;

if ($resultado_usuarios_marketing) {

    $resultado_usuarios_marketing->data_seek(0);

    while (
        $fila =
        $resultado_usuarios_marketing->fetch_assoc()
    ) {

        $total_marketing++;

        if ((int)$fila["activo"] === 1) {
            $total_activos++;
        } else {
            $total_inactivos++;
        }

        switch (
            strtoupper(
                trim($fila["rol_marketing"])
            )
        ) {

            case "ADMIN":
                $total_admin++;
                break;

            case "GERENCIA":
                $total_gerencia++;
                break;

            case "MARKETING":
                $total_marketing_rol++;
                break;

            case "AUDITOR":
                $total_auditor++;
                break;
        }
    }

    $resultado_usuarios_marketing->data_seek(0);
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

<title>Usuarios - Marketing</title>


<style>

/* ==========================================================
   GENERAL
========================================================== */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f4f6f8;

    color: #333;
}


/* ==========================================================
   BARRA SUPERIOR
========================================================== */

.barra {

    background: #212529;

    color: white;

    padding: 15px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 15px;
}

.barra h1 {

    margin: 0;

    font-size: 21px;
}

.barra-derecha {

    display: flex;

    align-items: center;

    gap: 15px;
}

.usuario {

    text-align: right;

    font-size: 13px;
}

.usuario strong {

    display: block;
}

.usuario small {

    color: #ced4da;
}

.salir {

    background: #dc3545;

    color: white;

    text-decoration: none;

    padding: 8px 13px;

    border-radius: 5px;

    font-size: 13px;
}

.salir:hover {

    background: #bb2d3b;
}


/* ==========================================================
   CONTENEDOR
========================================================== */

.contenedor {

    width: 95%;

    max-width: 1500px;

    margin: 30px auto;
}


/* ==========================================================
   TARJETAS
========================================================== */

.tarjeta {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.10);

    margin-bottom: 25px;
}

.tarjeta h2 {

    margin-top: 0;

    margin-bottom: 20px;
}


/* ==========================================================
   MENSAJES
========================================================== */

.mensaje {

    padding: 13px 16px;

    border-radius: 6px;

    margin-bottom: 20px;

    background: #d1e7dd;

    color: #0f5132;

    border: 1px solid #badbcc;
}

.error {

    padding: 13px 16px;

    border-radius: 6px;

    margin-bottom: 20px;

    background: #f8d7da;

    color: #842029;

    border: 1px solid #f5c2c7;
}


/* ==========================================================
   ESTADÍSTICAS
========================================================== */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(180px, 1fr));

    gap: 15px;

    margin-bottom: 25px;
}

.stat {

    background: white;

    border-radius: 10px;

    padding: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);
}

.stat-numero {

    font-size: 28px;

    font-weight: bold;

    margin-bottom: 5px;
}

.stat-texto {

    color: #6c757d;

    font-size: 13px;
}


/* ==========================================================
   FORMULARIO
========================================================== */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(230px, 1fr));

    gap: 15px;
}

.campo label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 6px;
}

.campo input,
.campo select {

    width: 100%;

    padding: 10px;

    border: 1px solid #ccc;

    border-radius: 5px;

    font-size: 14px;

    background: white;
}

.boton {

    margin-top: 20px;

    padding: 11px 20px;

    border: none;

    border-radius: 5px;

    background: #198754;

    color: white;

    cursor: pointer;

    font-size: 14px;

    font-weight: bold;
}

.boton:hover {

    background: #157347;
}


/* ==========================================================
   TABLA
========================================================== */

.tabla-contenedor {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1300px;
}

th {

    background: #212529;

    color: white;

    padding: 12px;

    text-align: left;

    font-size: 12px;
}

td {

    padding: 11px;

    border-bottom: 1px solid #ddd;

    font-size: 13px;

    vertical-align: top;
}

tr:hover {

    background: #f8f9fa;
}


/* ==========================================================
   ROLES
========================================================== */

.rol {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.rol-admin {

    background: #e2d9f3;

    color: #4c2a85;
}

.rol-gerencia {

    background: #ffe5d0;

    color: #9a4d00;
}

.rol-marketing {

    background: #cfe2ff;

    color: #084298;
}

.rol-auditor {

    background: #d1e7dd;

    color: #0f5132;
}


/* ==========================================================
   ESTADOS
========================================================== */

.estado {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.estado-activo {

    background: #d1e7dd;

    color: #0f5132;
}

.estado-inactivo {

    background: #f8d7da;

    color: #842029;
}


/* ==========================================================
   BOTONES
========================================================== */

.btn {

    border: none;

    padding: 7px 10px;

    border-radius: 5px;

    cursor: pointer;

    font-size: 12px;

    color: white;

    margin: 2px;
}

.btn:hover {

    opacity: .85;
}

.btn-rol {

    background: #fd7e14;
}

.btn-estado {

    background: #6c757d;
}


/* ==========================================================
   CAJA ROL
========================================================== */

.rol-box {

    display: none;

    margin-top: 8px;

    padding: 10px;

    background: #fff3cd;

    border: 1px solid #ffe69c;

    border-radius: 5px;
}

.rol-box select {

    padding: 7px;

    border: 1px solid #ccc;

    border-radius: 4px;

    margin-bottom: 6px;

    min-width: 150px;
}

.btn-guardar {

    padding: 7px 10px;

    border: none;

    background: #198754;

    color: white;

    border-radius: 4px;

    cursor: pointer;
}


/* ==========================================================
   PIE
========================================================== */

.footer {

    width: 100%;

    margin-top: 40px;

    padding: 20px;

    text-align: center;

    color: #6c757d;

    font-size: 13px;

    border-top: 1px solid #ddd;

    background: white;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 700px) {

    .barra {

        padding: 15px;
    }

    .barra-derecha {

        width: 100%;

        justify-content: space-between;
    }

    .contenedor {

        width: 95%;

        margin: 20px auto;
    }

    .tarjeta {

        padding: 15px;
    }
}

</style>


<script>

// ==========================================================
// MOSTRAR / OCULTAR ROL
// ==========================================================

function mostrarRol(id)
{
    const elemento =
        document.getElementById(
            "rol-" + id
        );

    if (!elemento) {
        return;
    }

    if (
        elemento.style.display === "block"
    ) {

        elemento.style.display = "none";

    } else {

        elemento.style.display = "block";
    }
}


// ==========================================================
// CONFIRMAR ROL
// ==========================================================

function confirmarRol(nombre)
{
    return confirm(
        "¿Deseas cambiar el rol de Marketing de "
        + nombre
        + "?"
    );
}


// ==========================================================
// CONFIRMAR ESTADO
// ==========================================================

function confirmarEstado(nombre, activo)
{
    if (activo) {

        return confirm(
            "¿Deseas desactivar el acceso a Marketing de "
            + nombre
            + "?"
        );

    } else {

        return confirm(
            "¿Deseas activar el acceso a Marketing de "
            + nombre
            + "?"
        );
    }
}


// ==========================================================
// OCULTAR MENSAJES
// ==========================================================

setTimeout(function() {

    const mensajes =
        document.querySelectorAll(
            ".mensaje"
        );

    mensajes.forEach(function(elemento) {

        elemento.style.transition =
            "opacity .5s";

        elemento.style.opacity = "0";

        setTimeout(function() {

            elemento.style.display =
                "none";

        }, 500);

    });

}, 5000);

</script>

</head>


<body>


<!-- ========================================================
     BARRA
========================================================= -->

<header class="barra">

    <h1>
        👥 Administración de Usuarios — Marketing
    </h1>

    <div class="barra-derecha">

        <div class="usuario">

            <strong>
                👤
                <?= htmlspecialchars(
                    $nombre_actual ?: 'Administrador'
                ) ?>
            </strong>

            <small>
                <?= htmlspecialchars(
                    $rol_actual ?: 'ADMIN'
                ) ?>
            </small>

        </div>

        <a
            href="logout.php"
            class="salir"
        >
            🚪 Salir
        </a>

    </div>

</header>


<!-- ========================================================
     CONTENEDOR
========================================================= -->

<div class="contenedor">


    <!-- ====================================================
         MENSAJES
    ===================================================== -->

    <?php if ($mensaje !== ""): ?>

        <div class="mensaje">

            ✅
            <?= htmlspecialchars($mensaje) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">

            ⚠️
            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- ====================================================
         ESTADÍSTICAS
    ===================================================== -->

    <div class="estadisticas">

        <div class="stat">

            <div class="stat-numero">
                <?= $total_marketing ?>
            </div>

            <div class="stat-texto">
                👥 Usuarios Marketing
            </div>

        </div>


        <div class="stat">

            <div class="stat-numero">
                <?= $total_activos ?>
            </div>

            <div class="stat-texto">
                ✅ Accesos activos
            </div>

        </div>


        <div class="stat">

            <div class="stat-numero">
                <?= $total_inactivos ?>
            </div>

            <div class="stat-texto">
                ⛔ Accesos inactivos
            </div>

        </div>


        <div class="stat">

            <div class="stat-numero">
                <?= $total_admin ?>
            </div>

            <div class="stat-texto">
                🛡️ Administradores
            </div>

        </div>


        <div class="stat">

            <div class="stat-numero">
                <?= $total_gerencia ?>
            </div>

            <div class="stat-texto">
                👔 Gerencia
            </div>

        </div>


        <div class="stat">

            <div class="stat-numero">
                <?= $total_auditor ?>
            </div>

            <div class="stat-texto">
                👁️ Auditores
            </div>

        </div>

    </div>


    <!-- ====================================================
         AGREGAR USUARIO
    ===================================================== -->

    <div class="tarjeta">

        <h2>
            ➕ Agregar acceso a Marketing
        </h2>


        <?php if (count($empleados_disponibles) > 0): ?>

            <form method="POST">

                <div class="form-grid">


                    <div class="campo">

                        <label>
                            Empleado
                        </label>

                        <select
                            name="empleado_id"
                            required
                        >

                            <option value="">
                                Seleccionar empleado
                            </option>

                            <?php foreach (
                                $empleados_disponibles
                                as $empleado
                            ): ?>

                                <option
                                    value="<?= (int)$empleado["id"] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $empleado["nombre"]
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $empleado["numero_empleado"]
                                        )
                                    ): ?>

                                        -
                                        <?= htmlspecialchars(
                                            $empleado["numero_empleado"]
                                        ) ?>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $empleado["puesto"]
                                        )
                                    ): ?>

                                        -
                                        <?= htmlspecialchars(
                                            $empleado["puesto"]
                                        ) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="campo">

                        <label>
                            Rol de Marketing
                        </label>

                        <select
                            name="rol_marketing"
                            required
                        >

                            <option value="MARKETING">
                                MARKETING
                            </option>

                            <option value="AUDITOR">
                                AUDITOR
                            </option>

                            <option value="GERENCIA">
                                GERENCIA
                            </option>

                            <option value="ADMIN">
                                ADMIN
                            </option>

                        </select>

                    </div>


                </div>


                <button
                    type="submit"
                    name="agregar_marketing"
                    class="boton"
                >

                    ➕ Agregar acceso

                </button>

            </form>

        <?php else: ?>

            <div class="mensaje">

                ℹ️ Todos los empleados activos ya tienen
                un registro de acceso a Marketing.

            </div>

        <?php endif; ?>

    </div>


    <!-- ====================================================
         USUARIOS REGISTRADOS
    ===================================================== -->

    <div class="tarjeta">

        <h2>
            👥 Usuarios con acceso a Marketing
        </h2>


        <div class="tabla-contenedor">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Empleado
                        </th>

                        <th>
                            No. empleado
                        </th>

                        <th>
                            Área
                        </th>

                        <th>
                            Puesto
                        </th>

                        <th>
                            Usuario
                        </th>

                        <th>
                            Rol Inventario
                        </th>

                        <th>
                            Rol Marketing
                        </th>

                        <th>
                            Estado Marketing
                        </th>

                        <th>
                            Último acceso
                        </th>

                        <th>
                            Fecha registro
                        </th>

                        <th>
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    $resultado_usuarios_marketing
                    && $resultado_usuarios_marketing->num_rows > 0
                ): ?>


                    <?php while (
                        $usuario =
                        $resultado_usuarios_marketing->fetch_assoc()
                    ): ?>


                        <?php

                        $rol_mkt =
                            strtoupper(
                                trim(
                                    $usuario["rol_marketing"]
                                )
                            );

                        ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <?= (int)$usuario["id"] ?>

                            </td>


                            <!-- EMPLEADO -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $usuario["empleado"]
                                    ) ?>

                                </strong>

                            </td>


                            <!-- NÚMERO -->

                            <td>

                                <?= htmlspecialchars(
                                    $usuario["numero_empleado"]
                                    ?? ""
                                ) ?>

                            </td>


                            <!-- ÁREA -->

                            <td>

                                <?= htmlspecialchars(
                                    $usuario["area"]
                                    ?? ""
                                ) ?>

                            </td>


                            <!-- PUESTO -->

                            <td>

                                <?= htmlspecialchars(
                                    $usuario["puesto"]
                                    ?? ""
                                ) ?>

                            </td>


                            <!-- USUARIO -->

                            <td>

                                <?php if (
                                    !empty(
                                        $usuario["usuario"]
                                    )
                                ): ?>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $usuario["usuario"]
                                        ) ?>

                                    </strong>

                                <?php else: ?>

                                    <span
                                        style="color:#dc3545;"
                                    >
                                        Sin usuario
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ROL INVENTARIO -->

                            <td>

                                <?php if (
                                    !empty(
                                        $usuario["rol_inventario"]
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        strtoupper(
                                            trim(
                                                $usuario["rol_inventario"]
                                            )
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <!-- ROL MARKETING -->

                            <td>


                                <?php if (
                                    $rol_mkt === "ADMIN"
                                ): ?>

                                    <span
                                        class="rol rol-admin"
                                    >
                                        🛡️ ADMIN
                                    </span>


                                <?php elseif (
                                    $rol_mkt === "GERENCIA"
                                ): ?>

                                    <span
                                        class="rol rol-gerencia"
                                    >
                                        👔 GERENCIA
                                    </span>


                                <?php elseif (
                                    $rol_mkt === "AUDITOR"
                                ): ?>

                                    <span
                                        class="rol rol-auditor"
                                    >
                                        👁️ AUDITOR
                                    </span>


                                <?php else: ?>

                                    <span
                                        class="rol rol-marketing"
                                    >
                                        📣 MARKETING
                                    </span>

                                <?php endif; ?>


                            </td>


                            <!-- ESTADO -->

                            <td>


                                <?php if (
                                    (int)$usuario["activo"] === 1
                                ): ?>

                                    <span
                                        class="estado estado-activo"
                                    >
                                        ACTIVO
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="estado estado-inactivo"
                                    >
                                        INACTIVO
                                    </span>

                                <?php endif; ?>


                            </td>


                            <!-- ÚLTIMO ACCESO -->

                            <td>

                                <?php if (
                                    !empty(
                                        $usuario["ultimo_acceso"]
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $usuario["ultimo_acceso"]
                                    ) ?>

                                <?php else: ?>

                                    Nunca

                                <?php endif; ?>

                            </td>


                            <!-- FECHA REGISTRO -->

                            <td>

                                <?= htmlspecialchars(
                                    $usuario["fecha_registro"]
                                ) ?>

                            </td>


                            <!-- ACCIONES -->

                            <td>


                                <?php
                                $es_usuario_actual =
                                    (
                                        (int)$usuario["empleado_id"]
                                        === $empleado_actual_id
                                    );
                                ?>


                                <!-- ================================
                                     CAMBIAR ESTADO
                                ================================= -->

                                <?php if (
                                    !$es_usuario_actual
                                ): ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirmarEstado(
                                            '<?= htmlspecialchars(
                                                $usuario["empleado"],
                                                ENT_QUOTES
                                            ) ?>',
                                            <?= (int)$usuario["activo"] ?>
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="marketing_id"
                                            value="<?= (int)$usuario["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="cambiar_estado_marketing"
                                            class="btn btn-estado"
                                        >

                                            <?=
                                                (int)$usuario["activo"] === 1
                                                ? "⛔ Desactivar"
                                                : "✅ Activar"
                                            ?>

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-estado"
                                        disabled
                                        style="
                                            cursor:not-allowed;
                                            opacity:.55;
                                        "
                                        title="
                                            No puedes cambiar tu propio acceso
                                        "
                                    >

                                        🔒 Tu cuenta

                                    </button>

                                <?php endif; ?>


                                <!-- ================================
                                     CAMBIAR ROL
                                ================================= -->

                                <?php if (
                                    !$es_usuario_actual
                                ): ?>

                                    <button
                                        type="button"
                                        class="btn btn-rol"
                                        onclick="mostrarRol(
                                            <?= (int)$usuario["id"] ?>
                                        )"
                                    >

                                        ✏️ Rol

                                    </button>


                                    <div
                                        id="rol-<?= (int)$usuario["id"] ?>"
                                        class="rol-box"
                                    >

                                        <form
                                            method="POST"
                                            onsubmit="return confirmarRol(
                                                '<?= htmlspecialchars(
                                                    $usuario["empleado"],
                                                    ENT_QUOTES
                                                ) ?>'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="marketing_id"
                                                value="<?= (int)$usuario["id"] ?>"
                                            >


                                            <select
                                                name="nuevo_rol_marketing"
                                                required
                                            >

                                                <option
                                                    value="MARKETING"
                                                    <?= $rol_mkt === "MARKETING"
                                                        ? "selected"
                                                        : ""
                                                    ?>
                                                >
                                                    MARKETING
                                                </option>


                                                <option
                                                    value="AUDITOR"
                                                    <?= $rol_mkt === "AUDITOR"
                                                        ? "selected"
                                                        : ""
                                                    ?>
                                                >
                                                    AUDITOR
                                                </option>


                                                <option
                                                    value="GERENCIA"
                                                    <?= $rol_mkt === "GERENCIA"
                                                        ? "selected"
                                                        : ""
                                                    ?>
                                                >
                                                    GERENCIA
                                                </option>


                                                <option
                                                    value="ADMIN"
                                                    <?= $rol_mkt === "ADMIN"
                                                        ? "selected"
                                                        : ""
                                                    ?>
                                                >
                                                    ADMIN
                                                </option>

                                            </select>


                                            <br>


                                            <button
                                                type="submit"
                                                name="cambiar_rol_marketing"
                                                class="btn-guardar"
                                            >

                                                💾 Guardar rol

                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-rol"
                                        disabled
                                        style="
                                            cursor:not-allowed;
                                            opacity:.55;
                                        "
                                        title="
                                            No puedes cambiar tu propio rol
                                        "
                                    >

                                        🔒 Rol protegido

                                    </button>

                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="12"
                            style="
                                text-align:center;
                                padding:35px;
                            "
                        >

                            No hay usuarios con acceso
                            al módulo de Marketing.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


<!-- ========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    © <?= date("Y") ?>

    Sistema de Marketing

    |

    Administración de Usuarios

    |

    <strong>
        Grupo Protec
    </strong>

</footer>


</body>

</html>


<?php

$conexion->close();

?>