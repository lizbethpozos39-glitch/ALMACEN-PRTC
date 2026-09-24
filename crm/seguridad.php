<?php

/*
|--------------------------------------------------------------------------
| SEGURIDAD SISTEMA COMERCIAL
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SESIÓN
|--------------------------------------------------------------------------
*/

function verificar_sesion()
{
    if (
        !isset($_SESSION['comercial_usuario_id']) ||
        empty($_SESSION['comercial_usuario_id'])
    ) {
        header("Location: login.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER USUARIO ACTUAL
|--------------------------------------------------------------------------
*/

function usuario_actual()
{
    return $_SESSION['comercial_usuario'] ?? 'Usuario';
}


/*
|--------------------------------------------------------------------------
| OBTENER ID DEL USUARIO
|--------------------------------------------------------------------------
*/

function usuario_id_actual()
{
    return $_SESSION['comercial_usuario_id'] ?? null;
}


/*
|--------------------------------------------------------------------------
| OBTENER EMPLEADO
|--------------------------------------------------------------------------
*/

function empleado_id_actual()
{
    return $_SESSION['comercial_empleado_id'] ?? null;
}


/*
|--------------------------------------------------------------------------
| OBTENER ROL
|--------------------------------------------------------------------------
*/

function rol_actual()
{
    return strtoupper(
        $_SESSION['comercial_rol'] ?? ''
    );
}


/*
|--------------------------------------------------------------------------
| VERIFICAR ADMIN
|--------------------------------------------------------------------------
*/

function verificar_admin()
{
    verificar_sesion();

    if (rol_actual() !== 'ADMIN') {

        http_response_code(403);

        die("
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <title>Acceso denegado</title>
                <style>
                    body{
                        font-family:Arial;
                        background:#f4f6f9;
                        text-align:center;
                        padding:80px 20px;
                    }

                    .box{
                        max-width:500px;
                        margin:auto;
                        background:white;
                        padding:40px;
                        border-radius:10px;
                        box-shadow:0 2px 10px rgba(0,0,0,.08);
                    }

                    h1{
                        color:#dc2626;
                    }

                    a{
                        display:inline-block;
                        margin-top:20px;
                        padding:10px 18px;
                        background:#2563eb;
                        color:white;
                        text-decoration:none;
                        border-radius:6px;
                    }
                </style>
            </head>

            <body>

                <div class='box'>

                    <h1>Acceso denegado</h1>

                    <p>
                        No tienes permisos para acceder a esta sección.
                    </p>

                    <a href='index.php'>
                        Regresar al sistema
                    </a>

                </div>

            </body>
            </html>
        ");

    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR GERENCIA O ADMIN
|--------------------------------------------------------------------------
*/

function verificar_gerencia()
{
    verificar_sesion();

    $rol = rol_actual();

    if (
        $rol !== 'ADMIN' &&
        $rol !== 'GERENCIA'
    ) {

        http_response_code(403);

        die("Acceso denegado.");
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR ACCESO COMERCIAL
|--------------------------------------------------------------------------
*/

function verificar_acceso_comercial()
{
    verificar_sesion();

    $roles_permitidos = [
        'ADMIN',
        'GERENCIA',
        'USUARIO',
        'CONSULTA'
    ];

    if (
        !in_array(
            rol_actual(),
            $roles_permitidos,
            true
        )
    ) {

        http_response_code(403);

        die("No tienes autorización para utilizar el sistema Comercial.");
    }
}


/*
|--------------------------------------------------------------------------
| CERRAR SESIÓN
|--------------------------------------------------------------------------
*/

function cerrar_sesion_comercial()
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: login.php");
    exit;
}