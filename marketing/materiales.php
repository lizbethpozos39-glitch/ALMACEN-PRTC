<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();


/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);
$nombre = empleado_actual();
$rol = rol_marketing();


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$mensaje = "";
$error = "";

$busqueda = trim($_GET['buscar'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';


/*
|--------------------------------------------------------------------------
| CREAR CARPETA DE ARCHIVOS
|--------------------------------------------------------------------------
*/

$directorio_upload = __DIR__ . '/uploads/marketing/';

if (!is_dir($directorio_upload)) {

    mkdir(
        $directorio_upload,
        0755,
        true
    );
}


/*
|--------------------------------------------------------------------------
| CARGAR CATEGORÍAS
|--------------------------------------------------------------------------
*/

$categorias = [];

$sqlCategorias = "
    SELECT
        id,
        nombre
    FROM marketing_categorias
    WHERE activo = 1
    ORDER BY nombre ASC
";

$resultCategorias = $conexion->query($sqlCategorias);

if ($resultCategorias) {

    while ($fila = $resultCategorias->fetch_assoc()) {

        $categorias[] = $fila;
    }
}


/*
|--------------------------------------------------------------------------
| CARGAR CAMPAÑAS
|--------------------------------------------------------------------------
*/

$campanas = [];

$sqlCampanas = "
    SELECT
        id,
        nombre,
        estado
    FROM marketing_campanas
    WHERE estado NOT IN ('FINALIZADA', 'CANCELADA')
    ORDER BY
        CASE
            WHEN estado = 'ACTIVA' THEN 1
            WHEN estado = 'PENDIENTE' THEN 2
            ELSE 3
        END,
        nombre ASC
";

$resultCampanas = $conexion->query($sqlCampanas);

if ($resultCampanas) {

    while ($fila = $resultCampanas->fetch_assoc()) {

        $campanas[] = $fila;
    }
}


/*
|--------------------------------------------------------------------------
| PROCESAR SUBIDA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | SUBIR MATERIAL
    |--------------------------------------------------------------------------
    */

    if ($accion === 'subir_material') {

        $nombre_material = trim(
            $_POST['nombre'] ?? ''
        );

        $descripcion = trim(
            $_POST['descripcion'] ?? ''
        );

        $campana_id = !empty($_POST['campana_id'])
            ? (int)$_POST['campana_id']
            : null;

        $categoria_id = !empty($_POST['categoria_id'])
            ? (int)$_POST['categoria_id']
            : null;


        /*
        |--------------------------------------------------------------------------
        | VALIDAR NOMBRE
        |--------------------------------------------------------------------------
        */

        if ($nombre_material === '') {

            $error = "Debes ingresar el nombre del material.";

        } elseif (mb_strlen($nombre_material) > 200) {

            $error = "El nombre del material no puede superar 200 caracteres.";

        } elseif (
            !isset($_FILES['archivo']) ||
            $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE
        ) {

            $error = "Debes seleccionar un archivo.";

        } else {

            $archivo = $_FILES['archivo'];


            /*
            |--------------------------------------------------------------------------
            | VALIDAR ERROR DE SUBIDA
            |--------------------------------------------------------------------------
            */

            if ($archivo['error'] !== UPLOAD_ERR_OK) {

                $error = "Ocurrió un error al subir el archivo.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | LÍMITE DE TAMAÑO
                |--------------------------------------------------------------------------
                |
                | 20 MB
                |
                */

                $maximo_bytes = 20 * 1024 * 1024;

                if ($archivo['size'] > $maximo_bytes) {

                    $error = "El archivo supera el límite permitido de 20 MB.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | OBTENER EXTENSIÓN
                    |--------------------------------------------------------------------------
                    */

                    $nombre_original =
                        basename($archivo['name']);

                    $extension =
                        strtolower(
                            pathinfo(
                                $nombre_original,
                                PATHINFO_EXTENSION
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | EXTENSIONES PERMITIDAS
                    |--------------------------------------------------------------------------
                    */

                    $extensiones_permitidas = [

                        'jpg',
                        'jpeg',
                        'png',
                        'webp',

                        'pdf',

                        'doc',
                        'docx',

                        'xls',
                        'xlsx',

                        'ppt',
                        'pptx',

                        'mp4'

                    ];


                    if (
                        !in_array(
                            $extension,
                            $extensiones_permitidas,
                            true
                        )
                    ) {

                        $error =
                            "Tipo de archivo no permitido.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | VALIDAR MIME REAL
                        |--------------------------------------------------------------------------
                        */

                        $finfo =
                            new finfo(FILEINFO_MIME_TYPE);

                        $mime =
                            $finfo->file(
                                $archivo['tmp_name']
                            );


                        $mimes_permitidos = [

                            'jpg' =>
                                [
                                    'image/jpeg'
                                ],

                            'jpeg' =>
                                [
                                    'image/jpeg'
                                ],

                            'png' =>
                                [
                                    'image/png'
                                ],

                            'webp' =>
                                [
                                    'image/webp'
                                ],

                            'pdf' =>
                                [
                                    'application/pdf'
                                ],

                            'doc' =>
                                [
                                    'application/msword'
                                ],

                            'docx' =>
                                [
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                                ],

                            'xls' =>
                                [
                                    'application/vnd.ms-excel'
                                ],

                            'xlsx' =>
                                [
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                ],

                            'ppt' =>
                                [
                                    'application/vnd.ms-powerpoint'
                                ],

                            'pptx' =>
                                [
                                    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                                ],

                            'mp4' =>
                                [
                                    'video/mp4'
                                ]

                        ];


                        if (
                            !isset($mimes_permitidos[$extension]) ||
                            !in_array(
                                $mime,
                                $mimes_permitidos[$extension],
                                true
                            )
                        ) {

                            $error =
                                "El tipo real del archivo no coincide con su extensión.";

                        } else {


                            /*
                            |--------------------------------------------------------------------------
                            | NOMBRE ÚNICO
                            |--------------------------------------------------------------------------
                            */

                            $nombre_unico =
                                date('Ymd_His') .
                                '_' .
                                bin2hex(
                                    random_bytes(8)
                                ) .
                                '.' .
                                $extension;


                            $ruta_fisica =
                                $directorio_upload .
                                $nombre_unico;


                            /*
                            |--------------------------------------------------------------------------
                            | RUTA PARA BASE DE DATOS
                            |--------------------------------------------------------------------------
                            */

                            $ruta_bd =
                                'uploads/marketing/' .
                                $nombre_unico;


                            /*
                            |--------------------------------------------------------------------------
                            | MOVER ARCHIVO
                            |--------------------------------------------------------------------------
                            */

                            if (
                                !move_uploaded_file(
                                    $archivo['tmp_name'],
                                    $ruta_fisica
                                )
                            ) {

                                $error =
                                    "No fue posible guardar el archivo.";

                            } else {


                                /*
                                |--------------------------------------------------------------------------
                                | INSERTAR MATERIAL
                                |--------------------------------------------------------------------------
                                */

                                $sqlInsert = "
                                    INSERT INTO marketing_materiales
                                    (
                                        campana_id,
                                        categoria_id,
                                        nombre,
                                        descripcion,
                                        nombre_archivo,
                                        nombre_archivo_original,
                                        ruta_archivo,
                                        tipo_archivo,
                                        extension,
                                        tamano_bytes,
                                        estado,
                                        usuario_subio
                                    )
                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        'PENDIENTE',
                                        ?
                                    )
                                ";


                                $stmt =
                                    $conexion->prepare(
                                        $sqlInsert
                                    );


                                if (!$stmt) {

                                    @unlink($ruta_fisica);

                                    $error =
                                        "Error al preparar el registro del material.";

                                } else {

                                    $stmt->bind_param(
                                        "iissssssssi",
                                        $campana_id,
                                        $categoria_id,
                                        $nombre_material,
                                        $descripcion,
                                        $nombre_unico,
                                        $nombre_original,
                                        $ruta_bd,
                                        $mime,
                                        $extension,
                                        $archivo['size'],
                                        $usuario_id
                                    );


                                    if ($stmt->execute()) {

                                        $material_id =
                                            $conexion->insert_id;


                                        /*
                                        |--------------------------------------------------------------------------
                                        | AUDITORÍA
                                        |--------------------------------------------------------------------------
                                        */

                                        $accion_auditoria =
                                            "SUBIR_MATERIAL";

                                        $modulo =
                                            "MATERIALES";

                                        $descripcion_auditoria =
                                            "Se subió el material: " .
                                            $nombre_material;


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
                                            (
                                                ?,
                                                ?,
                                                ?,
                                                ?,
                                                ?
                                            )
                                        ";


                                        $stmtAuditoria =
                                            $conexion->prepare(
                                                $sqlAuditoria
                                            );


                                        if ($stmtAuditoria) {

                                            $stmtAuditoria->bind_param(
                                                "issis",
                                                $empleado_id,
                                                $accion_auditoria,
                                                $modulo,
                                                $material_id,
                                                $descripcion_auditoria
                                            );

                                            $stmtAuditoria->execute();

                                            $stmtAuditoria->close();
                                        }


                                        $stmt->close();


                                        /*
                                        |--------------------------------------------------------------------------
                                        | MENSAJE
                                        |--------------------------------------------------------------------------
                                        */

                                        $mensaje =
                                            "Material subido correctamente. " .
                                            "Quedó en estado PENDIENTE de autorización.";


                                    } else {

                                        @unlink($ruta_fisica);

                                        $error =
                                            "No fue posible registrar el material.";

                                        $stmt->close();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CONSULTAR MATERIALES
|--------------------------------------------------------------------------
*/

$materiales = [];


$sqlMateriales = "
    SELECT

        m.id,

        m.nombre,

        m.descripcion,

        m.nombre_archivo_original,

        m.ruta_archivo,

        m.tipo_archivo,

        m.extension,

        m.tamano_bytes,

        m.estado,

        m.fecha_subida,

        m.fecha_actualizacion,

        c.nombre AS categoria,

        ca.nombre AS campana,

        e.nombre AS empleado_subio

    FROM marketing_materiales m

    LEFT JOIN marketing_categorias c
        ON c.id = m.categoria_id

    LEFT JOIN marketing_campanas ca
        ON ca.id = m.campana_id

    LEFT JOIN usuarios u
        ON u.id = m.usuario_subio

    LEFT JOIN empleados e
        ON e.id = u.empleado_id

    WHERE 1 = 1
";


$parametros = [];
$tipos = "";


/*
|--------------------------------------------------------------------------
| BUSCAR
|--------------------------------------------------------------------------
*/

if ($busqueda !== '') {

    $sqlMateriales .= "
        AND (
            m.nombre LIKE ?
            OR m.descripcion LIKE ?
            OR m.nombre_archivo_original LIKE ?
            OR c.nombre LIKE ?
            OR ca.nombre LIKE ?
        )
    ";

    $texto_busqueda = '%' . $busqueda . '%';

    $parametros[] = $texto_busqueda;
    $parametros[] = $texto_busqueda;
    $parametros[] = $texto_busqueda;
    $parametros[] = $texto_busqueda;
    $parametros[] = $texto_busqueda;

    $tipos .= "sssss";
}


/*
|--------------------------------------------------------------------------
| FILTRO ESTADO
|--------------------------------------------------------------------------
*/

$estados_validos = [
    'BORRADOR',
    'PENDIENTE',
    'AUTORIZADO',
    'RECHAZADO',
    'ARCHIVADO'
];

if (
    $filtro_estado !== '' &&
    in_array(
        $filtro_estado,
        $estados_validos,
        true
    )
) {

    $sqlMateriales .= "
        AND m.estado = ?
    ";

    $parametros[] = $filtro_estado;

    $tipos .= "s";
}


$sqlMateriales .= "
    ORDER BY m.fecha_subida DESC
";


$stmtMateriales =
    $conexion->prepare(
        $sqlMateriales
    );


if ($stmtMateriales) {

    if (!empty($parametros)) {

        $stmtMateriales->bind_param(
            $tipos,
            ...$parametros
        );
    }

    $stmtMateriales->execute();

    $resultadoMateriales =
        $stmtMateriales->get_result();


    while (
        $fila =
        $resultadoMateriales->fetch_assoc()
    ) {

        $materiales[] = $fila;
    }

    $stmtMateriales->close();
}


/*
|--------------------------------------------------------------------------
| FUNCIONES VISUALES
|--------------------------------------------------------------------------
*/

function formato_bytes($bytes)
{
    $bytes = (int)$bytes;

    if ($bytes <= 0) {
        return '0 B';
    }

    $unidades = [
        'B',
        'KB',
        'MB',
        'GB'
    ];

    $indice = 0;

    while (
        $bytes >= 1024 &&
        $indice < count($unidades) - 1
    ) {

        $bytes /= 1024;

        $indice++;
    }

    return number_format(
        $bytes,
        $indice === 0 ? 0 : 2
    ) .
    ' ' .
    $unidades[$indice];
}


function clase_estado($estado)
{
    switch ($estado) {

        case 'AUTORIZADO':
            return 'estado-autorizado';

        case 'PENDIENTE':
            return 'estado-pendiente';

        case 'RECHAZADO':
            return 'estado-rechazado';

        case 'BORRADOR':
            return 'estado-borrador';

        case 'ARCHIVADO':
            return 'estado-archivado';

        default:
            return '';
    }
}


function icono_archivo($extension)
{
    switch (strtolower($extension)) {

        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'webp':
            return '🖼️';

        case 'pdf':
            return '📕';

        case 'doc':
        case 'docx':
            return '📘';

        case 'xls':
        case 'xlsx':
            return '📗';

        case 'ppt':
        case 'pptx':
            return '📙';

        case 'mp4':
            return '🎬';

        default:
            return '📄';
    }
}

?>


<?php require_once __DIR__ . '/menu.php'; ?>


<div class="contenido-principal">

    <main class="contenido">


        <!-- =====================================================
             ENCABEZADO
        ====================================================== -->

        <section class="encabezado-pagina">

            <div>

                <div class="etiqueta">
                    GESTIÓN DE CONTENIDO
                </div>

                <h1>
                    Materiales de Marketing
                </h1>

                <p>
                    Administra flyers, imágenes, documentos,
                    presentaciones y otros materiales publicitarios.
                </p>

            </div>


            <button
                type="button"
                class="btn-principal"
                onclick="abrirModal()"
            >
                + Subir material
            </button>

        </section>


        <!-- =====================================================
             MENSAJES
        ====================================================== -->

        <?php if ($mensaje !== ''): ?>

            <div class="mensaje-exito">
                <span>✓</span>

                <?= htmlspecialchars($mensaje) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="mensaje-error">
                <span>⚠</span>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             FILTROS
        ====================================================== -->

        <section class="panel-filtros">

            <form
                method="GET"
                class="form-filtros"
            >

                <div class="campo-busqueda">

                    <label>
                        Buscar material
                    </label>

                    <input
                        type="text"
                        name="buscar"
                        placeholder="Nombre, campaña, categoría..."
                        value="<?= htmlspecialchars($busqueda) ?>"
                    >

                </div>


                <div class="campo-estado">

                    <label>
                        Estado
                    </label>

                    <select name="estado">

                        <option value="">
                            Todos
                        </option>

                        <?php foreach ($estados_validos as $estado): ?>

                            <option
                                value="<?= $estado ?>"
                                <?= $filtro_estado === $estado ? 'selected' : '' ?>
                            >
                                <?= $estado ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="botones-filtro">

                    <button
                        type="submit"
                        class="btn-buscar"
                    >
                        🔍 Buscar
                    </button>


                    <a
                        href="materiales.php"
                        class="btn-limpiar"
                    >
                        Limpiar
                    </a>

                </div>

            </form>

        </section>


        <!-- =====================================================
             LISTADO
        ====================================================== -->

        <section class="panel-materiales">


            <div class="panel-titulo">

                <div>

                    <h2>
                        Biblioteca de materiales
                    </h2>

                    <p>
                        <?= count($materiales) ?>
                        material(es) encontrado(s)
                    </p>

                </div>

            </div>


            <?php if (empty($materiales)): ?>


                <div class="sin-materiales">

                    <div class="sin-materiales-icono">
                        📁
                    </div>

                    <h3>
                        No hay materiales
                    </h3>

                    <p>
                        Todavía no se han cargado materiales
                        que coincidan con los filtros.
                    </p>

                    <button
                        type="button"
                        class="btn-principal"
                        onclick="abrirModal()"
                    >
                        + Subir primer material
                    </button>

                </div>


            <?php else: ?>


                <div class="tabla-contenedor">

                    <table class="tabla-materiales">

                        <thead>

                            <tr>

                                <th>
                                    Material
                                </th>

                                <th>
                                    Categoría
                                </th>

                                <th>
                                    Campaña
                                </th>

                                <th>
                                    Estado
                                </th>

                                <th>
                                    Subido por
                                </th>

                                <th>
                                    Fecha
                                </th>

                                <th>
                                    Acciones
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($materiales as $material): ?>


                            <tr>


                                <!-- MATERIAL -->

                                <td>

                                    <div class="material-info">


                                        <?php
                                        $es_imagen =
                                            in_array(
                                                strtolower($material['extension']),
                                                [
                                                    'jpg',
                                                    'jpeg',
                                                    'png',
                                                    'webp'
                                                ],
                                                true
                                            );
                                        ?>


                                        <?php if ($es_imagen): ?>

                                            <div class="miniatura">

                                                <img
                                                    src="<?= htmlspecialchars($material['ruta_archivo']) ?>"
                                                    alt="Vista previa"
                                                >

                                            </div>

                                        <?php else: ?>

                                            <div class="icono-archivo">

                                                <?= icono_archivo(
                                                    $material['extension']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $material['nombre']
                                                ) ?>
                                            </strong>


                                            <small>
                                                <?= htmlspecialchars(
                                                    $material['nombre_archivo_original']
                                                ) ?>
                                            </small>


                                            <small>
                                                <?= formato_bytes(
                                                    $material['tamano_bytes']
                                                ) ?>
                                            </small>

                                        </div>


                                    </div>

                                </td>


                                <!-- CATEGORÍA -->

                                <td>

                                    <?php if (
                                        !empty($material['categoria'])
                                    ): ?>

                                        <span class="texto-secundario">
                                            <?= htmlspecialchars(
                                                $material['categoria']
                                            ) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="sin-dato">
                                            Sin categoría
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- CAMPAÑA -->

                                <td>

                                    <?php if (
                                        !empty($material['campana'])
                                    ): ?>

                                        <span class="texto-secundario">
                                            <?= htmlspecialchars(
                                                $material['campana']
                                            ) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="sin-dato">
                                            Sin campaña
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ESTADO -->

                                <td>

                                    <span
                                        class="estado <?= clase_estado(
                                            $material['estado']
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $material['estado']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- USUARIO -->

                                <td>

                                    <span class="texto-secundario">

                                        <?= htmlspecialchars(
                                            $material['empleado_subio']
                                            ?: 'Usuario'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- FECHA -->

                                <td>

                                    <span class="fecha">

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $material['fecha_subida']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACCIONES -->

                                <td>

                                    <div class="acciones">


                                        <a
                                            href="descargar_material.php?id=<?= (int)$material['id'] ?>"
                                            class="btn-accion descargar"
                                        >
                                            ⬇ Descargar
                                        </a>


                                        <?php if ($es_imagen): ?>

                                            <a
                                                href="<?= htmlspecialchars($material['ruta_archivo']) ?>"
                                                target="_blank"
                                                class="btn-accion ver"
                                            >
                                                👁 Ver
                                            </a>

                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


<!-- ==========================================================
     MODAL SUBIR MATERIAL
========================================================== -->

<div
    id="modalMaterial"
    class="modal"
>


    <div class="modal-contenido">


        <div class="modal-header">

            <div>

                <h2>
                    Subir nuevo material
                </h2>

                <p>
                    El material quedará pendiente de autorización.
                </p>

            </div>


            <button
                type="button"
                class="modal-cerrar"
                onclick="cerrarModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <input
                type="hidden"
                name="accion"
                value="subir_material"
            >


            <!-- NOMBRE -->

            <div class="form-grupo">

                <label for="nombre">
                    Nombre del material *
                </label>

                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    maxlength="200"
                    placeholder="Ej. Flyer promoción septiembre"
                    required
                >

            </div>


            <!-- DESCRIPCIÓN -->

            <div class="form-grupo">

                <label for="descripcion">
                    Descripción
                </label>

                <textarea
                    id="descripcion"
                    name="descripcion"
                    rows="3"
                    placeholder="Describe brevemente el material..."
                ></textarea>

            </div>


            <!-- CATEGORÍA -->

            <div class="form-grid">


                <div class="form-grupo">

                    <label for="categoria_id">
                        Categoría
                    </label>

                    <select
                        id="categoria_id"
                        name="categoria_id"
                    >

                        <option value="">
                            Seleccionar categoría
                        </option>


                        <?php foreach ($categorias as $categoria): ?>

                            <option
                                value="<?= (int)$categoria['id'] ?>"
                            >
                                <?= htmlspecialchars(
                                    $categoria['nombre']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CAMPAÑA -->

                <div class="form-grupo">

                    <label for="campana_id">
                        Campaña
                    </label>

                    <select
                        id="campana_id"
                        name="campana_id"
                    >

                        <option value="">
                            Seleccionar campaña
                        </option>


                        <?php foreach ($campanas as $campana): ?>

                            <option
                                value="<?= (int)$campana['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $campana['nombre']
                                ) ?>

                                — <?= htmlspecialchars(
                                    $campana['estado']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


            </div>


            <!-- ARCHIVO -->

            <div class="form-grupo">

                <label>
                    Archivo *
                </label>


                <label
                    for="archivo"
                    class="zona-archivo"
                    id="zonaArchivo"
                >

                    <div class="zona-icono">
                        📎
                    </div>

                    <strong>
                        Selecciona un archivo
                    </strong>

                    <span>
                        JPG, PNG, WEBP, PDF, Word, Excel,
                        PowerPoint o MP4
                    </span>

                    <small>
                        Tamaño máximo: 20 MB
                    </small>

                    <input
                        type="file"
                        id="archivo"
                        name="archivo"
                        accept="
                            .jpg,
                            .jpeg,
                            .png,
                            .webp,
                            .pdf,
                            .doc,
                            .docx,
                            .xls,
                            .xlsx,
                            .ppt,
                            .pptx,
                            .mp4
                        "
                        required
                    >

                </label>


                <div
                    id="nombreArchivo"
                    class="nombre-archivo"
                ></div>

            </div>


            <!-- BOTONES -->

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-cancelar"
                    onclick="cerrarModal()"
                >
                    Cancelar
                </button>


                <button
                    type="submit"
                    class="btn-principal"
                >
                    Subir material
                </button>

            </div>


        </form>

    </div>

</div>


<style>

/* ==========================================================
   ENCABEZADO
========================================================== */

.encabezado-pagina {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}


.etiqueta {

    color: #245784;

    font-size: 11px;

    font-weight: bold;

    letter-spacing: 1px;

    margin-bottom: 6px;
}


.encabezado-pagina h1 {

    margin: 0 0 7px;

    font-size: 27px;

    color: #172033;
}


.encabezado-pagina p {

    margin: 0;

    color: #64748b;

    font-size: 14px;
}


/* ==========================================================
   BOTONES
========================================================== */

.btn-principal {

    border: none;

    background: #245784;

    color: white;

    padding: 12px 18px;

    border-radius: 7px;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;

    white-space: nowrap;
}


.btn-principal:hover {

    background: #1b4264;
}


.btn-buscar {

    border: none;

    background: #245784;

    color: white;

    padding: 10px 16px;

    border-radius: 7px;

    cursor: pointer;

    font-weight: bold;
}


.btn-limpiar {

    padding: 10px 16px;

    border-radius: 7px;

    border: 1px solid #d1d5db;

    color: #475569;

    background: white;

    text-decoration: none;

    font-size: 13px;
}


/* ==========================================================
   MENSAJES
========================================================== */

.mensaje-exito,
.mensaje-error {

    border-radius: 8px;

    padding: 13px 16px;

    margin-bottom: 20px;

    font-size: 14px;

    display: flex;

    align-items: center;

    gap: 10px;
}


.mensaje-exito {

    background: #ecfdf5;

    border: 1px solid #a7f3d0;

    color: #065f46;
}


.mensaje-error {

    background: #fef2f2;

    border: 1px solid #fecaca;

    color: #991b1b;
}


/* ==========================================================
   FILTROS
========================================================== */

.panel-filtros {

    background: white;

    border-radius: 11px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.05);
}


.form-filtros {

    display: grid;

    grid-template-columns:
        1fr 220px auto;

    gap: 15px;

    align-items: end;
}


.form-filtros label,
.form-grupo label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    color: #374151;

    margin-bottom: 7px;
}


.form-filtros input,
.form-filtros select,
.form-grupo input,
.form-grupo select,
.form-grupo textarea {

    width: 100%;

    border: 1px solid #d1d5db;

    border-radius: 7px;

    padding: 11px 12px;

    font-family: Arial, sans-serif;

    font-size: 14px;

    outline: none;

    background: white;
}


.form-filtros input:focus,
.form-filtros select:focus,
.form-grupo input:focus,
.form-grupo select:focus,
.form-grupo textarea:focus {

    border-color: #245784;

    box-shadow:
        0 0 0 3px rgba(36,87,132,.10);
}


.botones-filtro {

    display: flex;

    gap: 8px;
}


/* ==========================================================
   PANEL
========================================================== */

.panel-materiales {

    background: white;

    border-radius: 11px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.05);

    overflow: hidden;
}


.panel-titulo {

    padding: 20px 22px;

    border-bottom: 1px solid #e5e7eb;
}


.panel-titulo h2 {

    margin: 0 0 4px;

    font-size: 18px;
}


.panel-titulo p {

    margin: 0;

    color: #64748b;

    font-size: 12px;
}


/* ==========================================================
   TABLA
========================================================== */

.tabla-contenedor {

    overflow-x: auto;
}


.tabla-materiales {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}


.tabla-materiales th {

    background: #f8fafc;

    color: #475569;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .4px;

    text-align: left;

    padding: 13px 15px;

    border-bottom: 1px solid #e5e7eb;
}


.tabla-materiales td {

    padding: 13px 15px;

    border-bottom: 1px solid #eef0f3;

    vertical-align: middle;

    font-size: 13px;
}


.tabla-materiales tbody tr:hover {

    background: #fafcff;
}


.material-info {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 230px;
}


.material-info strong {

    display: block;

    color: #1f2937;

    margin-bottom: 3px;
}


.material-info small {

    display: block;

    color: #94a3b8;

    font-size: 11px;

    margin-top: 2px;
}


.miniatura {

    width: 54px;

    height: 54px;

    border-radius: 7px;

    overflow: hidden;

    border: 1px solid #e5e7eb;

    flex-shrink: 0;

    background: #f8fafc;
}


.miniatura img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}


.icono-archivo {

    width: 54px;

    height: 54px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 7px;

    background: #f1f5f9;

    font-size: 25px;

    flex-shrink: 0;
}


.texto-secundario {

    color: #475569;
}


.sin-dato {

    color: #94a3b8;

    font-size: 12px;
}


.fecha {

    color: #64748b;

    white-space: nowrap;

    font-size: 12px;
}


/* ==========================================================
   ESTADOS
========================================================== */

.estado {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: bold;

    white-space: nowrap;
}


.estado-pendiente {

    background: #fff7ed;

    color: #c2410c;
}


.estado-autorizado {

    background: #ecfdf5;

    color: #047857;
}


.estado-rechazado {

    background: #fef2f2;

    color: #b91c1c;
}


.estado-borrador {

    background: #f1f5f9;

    color: #475569;
}


.estado-archivado {

    background: #ede9fe;

    color: #6d28d9;
}


/* ==========================================================
   ACCIONES
========================================================== */

.acciones {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}


.btn-accion {

    padding: 6px 9px;

    border-radius: 6px;

    text-decoration: none;

    font-size: 11px;

    font-weight: bold;

    white-space: nowrap;
}


.btn-accion.descargar {

    background: #eef4f9;

    color: #245784;
}


.btn-accion.ver {

    background: #f1f5f9;

    color: #475569;
}


.btn-accion:hover {

    opacity: .8;
}


/* ==========================================================
   SIN MATERIALES
========================================================== */

.sin-materiales {

    text-align: center;

    padding: 70px 20px;
}


.sin-materiales-icono {

    font-size: 45px;

    margin-bottom: 12px;
}


.sin-materiales h3 {

    margin: 0 0 7px;

    font-size: 18px;
}


.sin-materiales p {

    color: #64748b;

    font-size: 13px;

    margin-bottom: 20px;
}


/* ==========================================================
   MODAL
========================================================== */

.modal {

    display: none;

    position: fixed;

    inset: 0;

    background: rgba(15,23,42,.55);

    z-index: 3000;

    align-items: center;

    justify-content: center;

    padding: 20px;
}


.modal.visible {

    display: flex;
}


.modal-contenido {

    width: 100%;

    max-width: 650px;

    max-height: 90vh;

    overflow-y: auto;

    background: white;

    border-radius: 13px;

    box-shadow:
        0 20px 50px rgba(0,0,0,.25);
}


.modal-header {

    padding: 22px 24px;

    border-bottom: 1px solid #e5e7eb;

    display: flex;

    justify-content: space-between;

    gap: 15px;
}


.modal-header h2 {

    margin: 0 0 5px;

    font-size: 20px;
}


.modal-header p {

    margin: 0;

    color: #64748b;

    font-size: 12px;
}


.modal-cerrar {

    border: none;

    background: transparent;

    font-size: 28px;

    color: #64748b;

    cursor: pointer;

    line-height: 1;

    height: 30px;
}


.modal-contenido form {

    padding: 22px 24px;
}


.form-grupo {

    margin-bottom: 17px;
}


.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


/* ==========================================================
   ZONA DE ARCHIVO
========================================================== */

.zona-archivo {

    border: 2px dashed #cbd5e1;

    border-radius: 10px;

    padding: 28px 20px;

    text-align: center;

    cursor: pointer;

    display: block !important;

    transition: .2s;
}


.zona-archivo:hover {

    border-color: #245784;

    background: #f8fafc;
}


.zona-archivo.archivo-seleccionado {

    border-color: #245784;

    background: #f8fafc;
}


.zona-icono {

    font-size: 32px;

    margin-bottom: 8px;
}


.zona-archivo strong {

    display: block;

    color: #334155;

    margin-bottom: 5px;
}


.zona-archivo span {

    display: block;

    color: #64748b;

    font-size: 12px;

    line-height: 1.5;
}


.zona-archivo small {

    display: block;

    margin-top: 5px;

    color: #94a3b8;

    font-size: 11px;
}


.zona-archivo input {

    display: none;
}


.nombre-archivo {

    margin-top: 8px;

    color: #245784;

    font-size: 12px;

    font-weight: bold;
}


/* ==========================================================
   FOOTER MODAL
========================================================== */

.modal-footer {

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    padding-top: 8px;
}


.btn-cancelar {

    border: 1px solid #d1d5db;

    background: white;

    color: #475569;

    padding: 11px 16px;

    border-radius: 7px;

    cursor: pointer;

    font-weight: bold;
}


.btn-cancelar:hover {

    background: #f8fafc;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 900px) {

    .form-filtros {

        grid-template-columns:
            1fr 1fr;
    }

    .botones-filtro {

        grid-column: 1 / -1;
    }

}


@media (max-width: 700px) {

    .encabezado-pagina {

        flex-direction: column;

        align-items: flex-start;
    }

    .form-filtros {

        grid-template-columns: 1fr;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 500px) {

    .modal {

        padding: 10px;
    }

    .modal-contenido form {

        padding: 18px;
    }

}

</style>


<script>

/* ==========================================================
   MODAL
========================================================== */

function abrirModal() {

    document
        .getElementById('modalMaterial')
        .classList.add('visible');

}


function cerrarModal() {

    document
        .getElementById('modalMaterial')
        .classList.remove('visible');

}


/* ==========================================================
   CERRAR AL HACER CLICK FUERA
========================================================== */

document
    .getElementById('modalMaterial')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {

                cerrarModal();

            }

        }
    );


/* ==========================================================
   MOSTRAR NOMBRE DEL ARCHIVO
========================================================== */

document
    .getElementById('archivo')
    .addEventListener(
        'change',
        function() {

            const nombre =
                document.getElementById(
                    'nombreArchivo'
                );

            const zona =
                document.getElementById(
                    'zonaArchivo'
                );


            if (this.files.length > 0) {

                nombre.textContent =
                    'Archivo seleccionado: ' +
                    this.files[0].name;

                zona.classList.add(
                    'archivo-seleccionado'
                );

            } else {

                nombre.textContent = '';

                zona.classList.remove(
                    'archivo-seleccionado'
                );

            }

        }
    );

</script>