<?php

/*
|--------------------------------------------------------------------------
| SEGURIDAD - MÓDULO MARKETING
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SESIÓN GENERAL
|--------------------------------------------------------------------------
*/

function verificar_sesion()
{
    if (
        !isset($_SESSION['usuario_id']) ||
        !isset($_SESSION['empleado_id'])
    ) {
        header("Location: login.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER ROL DE MARKETING
|--------------------------------------------------------------------------
*/

function rol_marketing()
{
    return $_SESSION['rol_marketing'] ?? '';
}


/*
|--------------------------------------------------------------------------
| VERIFICAR ACCESO A MARKETING
|--------------------------------------------------------------------------
*/

function verificar_marketing()
{
    verificar_sesion();

    if (
        !isset($_SESSION['marketing_acceso']) ||
        $_SESSION['marketing_acceso'] !== true
    ) {
        http_response_code(403);

        die("
            <div style='
                font-family: Arial;
                text-align:center;
                margin-top:100px;
            '>
                <h1>⛔ Acceso denegado</h1>

                <p>
                    No tienes permisos para acceder al módulo de Marketing.
                </p>

                <a href='login.php'>
                    Regresar al inicio
                </a>
            </div>
        ");
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR ADMIN DE MARKETING
|--------------------------------------------------------------------------
*/

function verificar_admin_marketing()
{
    verificar_marketing();

    if (rol_marketing() !== 'ADMIN') {

        http_response_code(403);

        die("
            <div style='
                font-family: Arial;
                text-align:center;
                margin-top:100px;
            '>

                <h1>⛔ Acceso denegado</h1>

                <p>
                    Esta sección requiere permisos de
                    administrador de Marketing.
                </p>

                <a href='index.php'>
                    Regresar al Marketing
                </a>

            </div>
        ");
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR GERENCIA
|--------------------------------------------------------------------------
| Pueden acceder:
| ADMIN
| GERENCIA
|--------------------------------------------------------------------------
*/

function verificar_gerencia_marketing()
{
    verificar_marketing();

    if (
        rol_marketing() !== 'ADMIN' &&
        rol_marketing() !== 'GERENCIA'
    ) {

        http_response_code(403);

        die("
            <div style='
                font-family: Arial;
                text-align:center;
                margin-top:100px;
            '>

                <h1>⛔ Acceso denegado</h1>

                <p>
                    Esta sección requiere permisos de
                    Gerencia.
                </p>

                <a href='index.php'>
                    Regresar al Marketing
                </a>

            </div>
        ");
    }
}


/*
|--------------------------------------------------------------------------
| INFORMACIÓN DEL USUARIO
|--------------------------------------------------------------------------
*/

function usuario_actual()
{
    return $_SESSION['nombre_usuario'] ?? '';
}


function empleado_actual()
{
    return $_SESSION['nombre_empleado'] ?? '';
}


function empleado_id_actual()
{
    return $_SESSION['empleado_id'] ?? 0;
}

?>