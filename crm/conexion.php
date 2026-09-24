<?php
// ==========================================================
// CONEXIÓN A BASE DE DATOS - SISTEMA COMERCIAL
// ==========================================================

// Configuración de la base de datos
$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "comercial_db";

// Crear conexión
$conexion = new mysqli(
    $host,
    $usuario,
    $password,
    $base_datos
);

// Verificar conexión
if ($conexion->connect_error) {
    die(
        "Error de conexión con la base de datos: " .
        $conexion->connect_error
    );
}

// Configurar UTF-8
$conexion->set_charset("utf8mb4");

?>