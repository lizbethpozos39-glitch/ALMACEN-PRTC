<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();


/*
|--------------------------------------------------------------------------
| ID DEL MATERIAL
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($id <= 0) {

    http_response_code(400);

    die("Material no válido.");
}


/*
|--------------------------------------------------------------------------
| BUSCAR MATERIAL
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        nombre,
        nombre_archivo_original,
        ruta_archivo,
        tipo_archivo,
        estado
    FROM marketing_materiales
    WHERE id = ?
    LIMIT 1
";


$stmt = $conexion->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    die("Error interno.");
}


$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$resultado = $stmt->get_result();


if ($resultado->num_rows !== 1) {

    $stmt->close();

    http_response_code(404);

    die("Material no encontrado.");
}


$material =
    $resultado->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| CONSTRUIR RUTA FÍSICA
|--------------------------------------------------------------------------
*/

$ruta_relativa =
    $material['ruta_archivo'];


/*
|--------------------------------------------------------------------------
| PROTECCIÓN DE RUTA
|--------------------------------------------------------------------------
*/

if (
    strpos(
        $ruta_relativa,
        'uploads/marketing/'
    ) !== 0
) {

    http_response_code(403);

    die("Ruta de archivo no válida.");
}


$ruta_fisica =
    __DIR__ .
    '/' .
    $ruta_relativa;


/*
|--------------------------------------------------------------------------
| VERIFICAR ARCHIVO
|--------------------------------------------------------------------------
*/

if (!is_file($ruta_fisica)) {

    http_response_code(404);

    die("El archivo no existe en el servidor.");
}


/*
|--------------------------------------------------------------------------
| REGISTRAR DESCARGA
|--------------------------------------------------------------------------
*/

$empleado_id =
    (int)($_SESSION['empleado_id'] ?? 0);


$accion =
    "DESCARGAR_MATERIAL";

$modulo =
    "MATERIALES";

$descripcion =
    "Se descargó el material: " .
    $material['nombre'];


$sqlAuditoria = "
    INSERT INTO marketing_auditoria
    (
        empleado_id,
        accion,
        modulo,
        referencia_id,
        descripcion
    )
    VALUES
    (?, ?, ?, ?, ?)
";


$stmtAuditoria =
    $conexion->prepare(
        $sqlAuditoria
    );


if ($stmtAuditoria) {

    $stmtAuditoria->bind_param(
        "issis",
        $empleado_id,
        $accion,
        $modulo,
        $id,
        $descripcion
    );

    $stmtAuditoria->execute();

    $stmtAuditoria->close();
}


/*
|--------------------------------------------------------------------------
| DESCARGAR
|--------------------------------------------------------------------------
*/

$nombre_descarga =
    basename(
        $material['nombre_archivo_original']
    );


$mime =
    $material['tipo_archivo'];


$tamano =
    filesize(
        $ruta_fisica
    );


header(
    'Content-Description: File Transfer'
);

header(
    'Content-Type: ' . $mime
);

header(
    'Content-Disposition: attachment; filename="' .
    str_replace(
        '"',
        '',
        $nombre_descarga
    ) .
    '"'
);

header(
    'Content-Length: ' . $tamano
);

header(
    'Cache-Control: no-cache, must-revalidate'
);

header(
    'Pragma: public'
);


readfile(
    $ruta_fisica
);

exit;

?>