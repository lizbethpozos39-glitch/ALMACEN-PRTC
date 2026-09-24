<?php

/*
|--------------------------------------------------------------------------
| CONEXIÓN A BASE DE DATOS - MARKETING
|--------------------------------------------------------------------------
| Marketing utiliza la misma base de datos de la empresa
| que el sistema de Inventario.
|--------------------------------------------------------------------------
*/

$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "inventario_db";

$conexion = new mysqli(
    $host,
    $usuario,
    $password,
    $base_datos
);

if ($conexion->connect_error) {
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");

?>