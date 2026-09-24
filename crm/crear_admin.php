<?php

require_once __DIR__ . '/conexion.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {

        $mensaje = "Completa usuario y contraseña.";

    } elseif (strlen($password) < 6) {

        $mensaje = "La contraseña debe tener al menos 6 caracteres.";

    } else {

        // Buscar empleado
        $stmt = $conexion->prepare("
            SELECT id
            FROM empleados
            WHERE nombre = 'Lizbeth'
              AND apellido_paterno = 'Pozos'
              AND activo = 1
            LIMIT 1
        ");

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows !== 1) {

            $mensaje = "No se encontró el empleado Lizbeth Pozos.";

        } else {

            $empleado = $resultado->fetch_assoc();

            $empleado_id = (int)$empleado['id'];

            // Verificar que el usuario no exista
            $stmt2 = $conexion->prepare("
                SELECT id
                FROM usuarios
                WHERE usuario = ?
                LIMIT 1
            ");

            $stmt2->bind_param("s", $usuario);
            $stmt2->execute();

            $resultado2 = $stmt2->get_result();

            if ($resultado2->num_rows > 0) {

                $mensaje = "Ese nombre de usuario ya existe.";

            } else {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $rol = "ADMIN";

                $activo = 1;

                $stmt3 = $conexion->prepare("
                    INSERT INTO usuarios
                    (
                        empleado_id,
                        usuario,
                        password_hash,
                        rol,
                        activo
                    )
                    VALUES
                    (?, ?, ?, ?, ?)
                ");

                $stmt3->bind_param(
                    "isssi",
                    $empleado_id,
                    $usuario,
                    $password_hash,
                    $rol,
                    $activo
                );

                if ($stmt3->execute()) {

                    $mensaje =
                        "Usuario ADMIN creado correctamente. " .
                        "Ya puedes iniciar sesión.";

                } else {

                    $mensaje =
                        "Error al crear usuario: " .
                        $stmt3->error;
                }

                $stmt3->close();
            }

            $stmt2->close();
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Crear administrador</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.contenedor {
    width: 400px;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
}

h2 {
    margin-top: 0;
    color: #1f2937;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 6px;
    font-weight: bold;
}

input {
    width: 100%;
    padding: 11px;
    box-sizing: border-box;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

button {
    width: 100%;
    margin-top: 20px;
    padding: 12px;
    border: none;
    border-radius: 6px;
    background: #2563eb;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

.mensaje {
    margin-bottom: 15px;
    padding: 10px;
    background: #eef2ff;
    border-radius: 6px;
}

</style>

</head>

<body>

<div class="contenedor">

<h2>Crear usuario administrador</h2>

<?php if ($mensaje !== ''): ?>

<div class="mensaje">
    <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<form method="POST">

<label>Usuario</label>

<input
    type="text"
    name="usuario"
    value="Lizbeth"
    required
>

<label>Contraseña</label>

<input
    type="password"
    name="password"
    required
>

<button type="submit">
    Crear administrador
</button>

</form>

</div>

</body>

</html>