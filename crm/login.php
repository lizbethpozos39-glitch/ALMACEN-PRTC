<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

$mensaje = "";


/*
|--------------------------------------------------------------------------
| SI YA HAY SESIÓN, ENVIAR AL SISTEMA
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['comercial_usuario_id']) &&
    !empty($_SESSION['comercial_usuario_id'])
) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| PROCESAR LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {

        $mensaje = "Ingresa usuario y contraseña.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | BUSCAR USUARIO
        |
        | conexion.php ya está conectado a comercial_db
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                u.id,
                u.empleado_id,
                u.usuario,
                u.password_hash,
                u.rol,
                u.activo,

                e.nombre,
                e.apellido_paterno,
                e.apellido_materno,
                e.puesto,
                e.area,
                e.activo AS empleado_activo

            FROM usuarios u

            INNER JOIN empleados e
                ON e.id = u.empleado_id

            WHERE u.usuario = ?
              AND u.activo = 1
              AND e.activo = 1

            LIMIT 1
        ";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {

            $mensaje =
                "Error al consultar el usuario: " .
                $conexion->error;

        } else {

            $stmt->bind_param(
                "s",
                $usuario
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            /*
            |--------------------------------------------------------------------------
            | USUARIO ENCONTRADO
            |--------------------------------------------------------------------------
            */

            if ($resultado->num_rows === 1) {

                $datos = $resultado->fetch_assoc();


                /*
                |--------------------------------------------------------------------------
                | VERIFICAR CONTRASEÑA
                |--------------------------------------------------------------------------
                */

                if (
                    password_verify(
                        $password,
                        $datos['password_hash']
                    )
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | REGENERAR SESIÓN
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | DATOS DE SESIÓN
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['comercial_usuario_id'] =
                        (int)$datos['id'];

                    $_SESSION['comercial_empleado_id'] =
                        (int)$datos['empleado_id'];

                    $_SESSION['comercial_usuario'] =
                        $datos['usuario'];

                    $_SESSION['comercial_rol'] =
                        strtoupper($datos['rol']);

                    $_SESSION['comercial_nombre'] =
                        trim(
                            $datos['nombre'] . ' ' .
                            $datos['apellido_paterno'] . ' ' .
                            ($datos['apellido_materno'] ?? '')
                        );

                    $_SESSION['comercial_puesto'] =
                        $datos['puesto'] ?? '';

                    $_SESSION['comercial_area'] =
                        $datos['area'] ?? '';


                    /*
                    |--------------------------------------------------------------------------
                    | ACTUALIZAR ÚLTIMO ACCESO
                    |--------------------------------------------------------------------------
                    */

                    $stmt_acceso = $conexion->prepare("
                        UPDATE usuarios
                        SET ultimo_acceso = NOW()
                        WHERE id = ?
                    ");

                    if ($stmt_acceso) {

                        $usuario_id =
                            (int)$datos['id'];

                        $stmt_acceso->bind_param(
                            "i",
                            $usuario_id
                        );

                        $stmt_acceso->execute();

                        $stmt_acceso->close();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | REGISTRAR LOGIN EN AUDITORÍA
                    |--------------------------------------------------------------------------
                    */

                    $usuario_auditoria =
                        $datos['usuario'];

                    $accion =
                        "LOGIN";

                    $modulo =
                        "Sistema";

                    $descripcion =
                        "Inicio de sesión en Sistema Comercial";

                    $ip =
                        $_SERVER['REMOTE_ADDR'] ?? '';


                    $stmt_auditoria = $conexion->prepare("
                        INSERT INTO auditoria
                        (
                            usuario,
                            accion,
                            modulo,
                            registro_id,
                            descripcion,
                            ip
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            NULL,
                            ?,
                            ?
                        )
                    ");

                    if ($stmt_auditoria) {

                        $stmt_auditoria->bind_param(
                            "sssss",
                            $usuario_auditoria,
                            $accion,
                            $modulo,
                            $descripcion,
                            $ip
                        );

                        $stmt_auditoria->execute();

                        $stmt_auditoria->close();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CERRAR CONSULTA
                    |--------------------------------------------------------------------------
                    */

                    $stmt->close();


                    /*
                    |--------------------------------------------------------------------------
                    | ENTRAR AL SISTEMA
                    |--------------------------------------------------------------------------
                    */

                    header("Location: index.php");
                    exit;

                } else {

                    $mensaje =
                        "Usuario o contraseña incorrectos.";
                }

            } else {

                $mensaje =
                    "Usuario o contraseña incorrectos.";
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Acceso | Sistema Comercial
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f9;
        }


        .login-contenedor {

            width: 100%;

            max-width: 420px;

            padding: 20px;
        }


        .login {

            background: white;

            padding: 35px;

            border-radius: 12px;

            box-shadow:
                0 4px 20px rgba(0,0,0,.08);

            border:
                1px solid #e5e7eb;
        }


        .logo {

            text-align: center;

            margin-bottom: 28px;
        }


        .logo h1 {

            margin: 0;

            color: #1f2937;

            font-size: 27px;
        }


        .logo p {

            color: #6b7280;

            font-size: 14px;

            margin: 8px 0 0;
        }


        .campo {

            margin-bottom: 18px;
        }


        .campo label {

            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: bold;

            color: #374151;
        }


        .campo input {

            width: 100%;

            padding: 12px;

            border:
                1px solid #d1d5db;

            border-radius: 7px;

            font-size: 14px;
        }


        .campo input:focus {

            outline: none;

            border-color: #2563eb;
        }


        .boton {

            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 7px;

            background: #2563eb;

            color: white;

            font-weight: bold;

            cursor: pointer;

            font-size: 14px;
        }


        .boton:hover {

            background: #1d4ed8;
        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            padding: 11px;

            border-radius: 6px;

            margin-bottom: 18px;

            font-size: 13px;

            text-align: center;
        }


        .pie {

            text-align: center;

            color: #9ca3af;

            font-size: 11px;

            margin-top: 22px;
        }

    </style>

</head>


<body>


<div class="login-contenedor">


    <div class="login">


        <div class="logo">

            <h1>
                Sistema Comercial
            </h1>

            <p>
                Acceso al sistema
            </p>

        </div>


        <?php if ($mensaje !== ''): ?>

            <div class="error">

                <?= htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="campo">

                <label>
                    Usuario
                </label>

                <input
                    type="text"
                    name="usuario"
                    autocomplete="username"
                    required
                    autofocus
                >

            </div>


            <div class="campo">

                <label>
                    Contraseña
                </label>

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="boton"
            >
                Iniciar sesión
            </button>


        </form>


        <div class="pie">

            Sistema Comercial

        </div>


    </div>


</div>


</body>

</html>