<?php

/*
|--------------------------------------------------------------------------
| LOGIN - MÓDULO MARKETING
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';


/*
|--------------------------------------------------------------------------
| SI YA TIENE SESIÓN DE MARKETING
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['marketing_acceso']) &&
    $_SESSION['marketing_acceso'] === true
) {
    header("Location: index.php");
    exit;
}


$error = "";


/*
|--------------------------------------------------------------------------
| PROCESAR LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {

        $error = "Ingresa tu usuario y contraseña.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | BUSCAR USUARIO
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                u.id AS usuario_id,
                u.empleado_id,
                u.usuario,
                u.password_hash,
                u.activo AS usuario_activo,

                e.nombre AS nombre_empleado,
                e.activo AS empleado_activo

            FROM usuarios u

            INNER JOIN empleados e
                ON e.id = u.empleado_id

            WHERE u.usuario = ?

            LIMIT 1
        ";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {

            $error = "Error interno al consultar el usuario.";

        } else {

            $stmt->bind_param("s", $usuario);
            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows !== 1) {

                $error = "Usuario o contraseña incorrectos.";

            } else {

                $datos = $resultado->fetch_assoc();


                /*
                |--------------------------------------------------------------------------
                | VERIFICAR CONTRASEÑA
                |--------------------------------------------------------------------------
                */

                if (!password_verify($password, $datos['password_hash'])) {

                    $error = "Usuario o contraseña incorrectos.";

                } elseif ((int)$datos['usuario_activo'] !== 1) {

                    $error = "El usuario no está activo.";

                } elseif ((int)$datos['empleado_activo'] !== 1) {

                    $error = "El empleado no está activo.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | BUSCAR PERMISO DE MARKETING
                    |--------------------------------------------------------------------------
                    */

                    $sqlMarketing = "
                        SELECT
                            id,
                            rol_marketing,
                            activo

                        FROM marketing_usuarios

                        WHERE empleado_id = ?

                        LIMIT 1
                    ";

                    $stmtMarketing = $conexion->prepare($sqlMarketing);

                    if (!$stmtMarketing) {

                        $error = "Error al verificar los permisos de Marketing.";

                    } else {

                        $stmtMarketing->bind_param(
                            "i",
                            $datos['empleado_id']
                        );

                        $stmtMarketing->execute();

                        $resultadoMarketing =
                            $stmtMarketing->get_result();


                        /*
                        |--------------------------------------------------------------------------
                        | NO TIENE ACCESO
                        |--------------------------------------------------------------------------
                        */

                        if ($resultadoMarketing->num_rows !== 1) {

                            $error = "
                                No tienes acceso al módulo de Marketing.
                                <br>
                                Solicita autorización al administrador.
                            ";

                        } else {

                            $marketing =
                                $resultadoMarketing->fetch_assoc();


                            /*
                            |--------------------------------------------------------------------------
                            | VERIFICAR ESTADO
                            |--------------------------------------------------------------------------
                            */

                            if ((int)$marketing['activo'] !== 1) {

                                $error = "
                                    Tu acceso al módulo de Marketing
                                    está desactivado.
                                ";

                            } else {

                                /*
                                |--------------------------------------------------------------------------
                                | LOGIN CORRECTO
                                |--------------------------------------------------------------------------
                                */

                                session_regenerate_id(true);


                                $_SESSION['usuario_id'] =
                                    (int)$datos['usuario_id'];

                                $_SESSION['empleado_id'] =
                                    (int)$datos['empleado_id'];

                                $_SESSION['nombre_usuario'] =
                                    $datos['usuario'];

                                $_SESSION['nombre_empleado'] =
                                    $datos['nombre_empleado'];

                                $_SESSION['rol_marketing'] =
                                    $marketing['rol_marketing'];

                                $_SESSION['marketing_acceso'] =
                                    true;


                                /*
                                |--------------------------------------------------------------------------
                                | ACTUALIZAR ÚLTIMO ACCESO
                                |--------------------------------------------------------------------------
                                */

                                $sqlAcceso = "
                                    UPDATE usuarios
                                    SET ultimo_acceso = NOW()
                                    WHERE id = ?
                                ";

                                $stmtAcceso =
                                    $conexion->prepare($sqlAcceso);

                                if ($stmtAcceso) {

                                    $stmtAcceso->bind_param(
                                        "i",
                                        $datos['usuario_id']
                                    );

                                    $stmtAcceso->execute();

                                    $stmtAcceso->close();
                                }


                                /*
                                |--------------------------------------------------------------------------
                                | REGISTRAR AUDITORÍA
                                |--------------------------------------------------------------------------
                                */

                                $accion = "LOGIN";
                                $modulo = "MARKETING";
                                $descripcion =
                                    "Inicio de sesión en Marketing";

                                $sqlAuditoria = "
                                    INSERT INTO marketing_auditoria
                                    (
                                        empleado_id,
                                        accion,
                                        modulo,
                                        descripcion
                                    )
                                    VALUES
                                    (?, ?, ?, ?)
                                ";

                                $stmtAuditoria =
                                    $conexion->prepare($sqlAuditoria);

                                if ($stmtAuditoria) {

                                    $stmtAuditoria->bind_param(
                                        "isss",
                                        $datos['empleado_id'],
                                        $accion,
                                        $modulo,
                                        $descripcion
                                    );

                                    $stmtAuditoria->execute();

                                    $stmtAuditoria->close();
                                }


                                /*
                                |--------------------------------------------------------------------------
                                | ENTRAR AL DASHBOARD
                                |--------------------------------------------------------------------------
                                */

                                header("Location: index.php");
                                exit;
                            }
                        }

                        $stmtMarketing->close();
                    }
                }
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

    <title>Marketing - Acceso</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            font-family: Arial, Helvetica, sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f4f6f9,
                    #e9edf3
                );
        }


        .login-container {

            width: 100%;

            max-width: 430px;

            padding: 20px;
        }


        .login-box {

            background: #ffffff;

            border-radius: 14px;

            padding: 40px;

            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.12);
        }


        .logo {

            text-align: center;

            margin-bottom: 25px;
        }


        .logo-icon {

            width: 70px;

            height: 70px;

            margin: auto;

            border-radius: 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #1f4e79;

            color: white;

            font-size: 32px;
        }


        h1 {

            text-align: center;

            margin: 15px 0 5px;

            color: #1f2937;

            font-size: 27px;
        }


        .subtitulo {

            text-align: center;

            color: #6b7280;

            margin-bottom: 30px;

            font-size: 14px;
        }


        label {

            display: block;

            margin-bottom: 7px;

            color: #374151;

            font-weight: bold;

            font-size: 14px;
        }


        input {

            width: 100%;

            padding: 13px 14px;

            margin-bottom: 18px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            outline: none;
        }


        input:focus {

            border-color: #1f4e79;

            box-shadow:
                0 0 0 3px
                rgba(31, 78, 121, 0.12);
        }


        button {

            width: 100%;

            border: none;

            border-radius: 8px;

            padding: 14px;

            background: #1f4e79;

            color: white;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;
        }


        button:hover {

            background: #173a5c;
        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;

            border-radius: 8px;

            padding: 12px;

            margin-bottom: 20px;

            text-align: center;

            font-size: 14px;
        }


        .footer {

            text-align: center;

            margin-top: 25px;

            color: #9ca3af;

            font-size: 12px;
        }

    </style>

</head>


<body>


<div class="login-container">

    <div class="login-box">


        <div class="logo">

            <div class="logo-icon">
                📣
            </div>

            <h1>
                Marketing
            </h1>

            <div class="subtitulo">
                Portal de Marketing
            </div>

        </div>


        <?php if ($error !== ""): ?>

            <div class="error">
                <?= $error ?>
            </div>

        <?php endif; ?>


        <form method="POST" autocomplete="off">


            <label for="usuario">
                Usuario
            </label>

            <input
                type="text"
                id="usuario"
                name="usuario"
                placeholder="Ingresa tu usuario"
                autocomplete="username"
                required
                value="<?= htmlspecialchars($usuario ?? '') ?>"
            >


            <label for="password">
                Contraseña
            </label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Ingresa tu contraseña"
                autocomplete="current-password"
                required
            >


            <button type="submit">
                Ingresar a Marketing
            </button>

        </form>


        <div class="footer">
            Grupo Protec · Sistema de Marketing
        </div>


    </div>

</div>


</body>

</html>