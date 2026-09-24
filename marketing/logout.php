<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CERRAR SESIÓN DE MARKETING
|--------------------------------------------------------------------------
|
| Importante:
| No destruimos toda la sesión porque Inventario
| podría estar utilizando la misma sesión.
|
|--------------------------------------------------------------------------
*/

unset($_SESSION['marketing_acceso']);
unset($_SESSION['rol_marketing']);


/*
|--------------------------------------------------------------------------
| REGRESAR AL LOGIN
|--------------------------------------------------------------------------
*/

header("Location: login.php");
exit;

?>