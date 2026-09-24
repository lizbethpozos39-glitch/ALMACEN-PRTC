<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_gerencia_marketing();


/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$empleado_id = (int)($_SESSION['empleado_id'] ?? 0);

$nombre_usuario = empleado_actual();
$rol_usuario    = strtoupper(rol_marketing());


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$mensaje = "";
$error   = "";


/*
|--------------------------------------------------------------------------
| VALIDAR USUARIO
|--------------------------------------------------------------------------
*/

if ($usuario_id <= 0 || $empleado_id <= 0) {

    $error = "No fue posible identificar correctamente al usuario actual.";

}


/*
|--------------------------------------------------------------------------
| PROCESAR AUTORIZACIÓN / RECHAZO
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    $accion = $_POST['accion'] ?? '';

    $material_id = isset($_POST['material_id'])
        ? (int)$_POST['material_id']
        : 0;

    $comentario = trim(
        $_POST['comentario'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDAR ACCIÓN
    |--------------------------------------------------------------------------
    */

    if (
        $material_id <= 0 ||
        !in_array(
            $accion,
            ['AUTORIZADO', 'RECHAZADO'],
            true
        )
    ) {

        $error = "La solicitud no es válida.";

    } elseif (
        $accion === 'RECHAZADO' &&
        $comentario === ''
    ) {

        $error = "Debes indicar el motivo del rechazo.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | INICIAR TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        $conexion->begin_transaction();

        try {


            /*
            |--------------------------------------------------------------------------
            | BUSCAR MATERIAL
            |--------------------------------------------------------------------------
            */

            $sqlMaterial = "
                SELECT
                    id,
                    nombre,
                    estado,
                    usuario_subio
                FROM marketing_materiales
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ";


            $stmtMaterial = $conexion->prepare(
                $sqlMaterial
            );


            if (!$stmtMaterial) {

                throw new Exception(
                    "No fue posible consultar el material."
                );

            }


            $stmtMaterial->bind_param(
                "i",
                $material_id
            );


            if (!$stmtMaterial->execute()) {

                $stmtMaterial->close();

                throw new Exception(
                    "No fue posible ejecutar la consulta del material."
                );

            }


            $resultadoMaterial =
                $stmtMaterial->get_result();


            if (
                !$resultadoMaterial ||
                $resultadoMaterial->num_rows !== 1
            ) {

                $stmtMaterial->close();

                throw new Exception(
                    "El material no existe."
                );

            }


            $material =
                $resultadoMaterial->fetch_assoc();


            $stmtMaterial->close();


            /*
            |--------------------------------------------------------------------------
            | VALIDAR ESTADO
            |--------------------------------------------------------------------------
            */

            if (
                ($material['estado'] ?? '') !== 'PENDIENTE'
            ) {

                throw new Exception(
                    "Este material ya fue procesado. " .
                    "Su estado actual es: " .
                    ($material['estado'] ?? 'DESCONOCIDO')
                );

            }


            /*
            |--------------------------------------------------------------------------
            | NUEVO ESTADO
            |--------------------------------------------------------------------------
            */

            $nuevo_estado = $accion;


            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR MATERIAL
            |--------------------------------------------------------------------------
            */

            $sqlUpdate = "
                UPDATE marketing_materiales
                SET estado = ?
                WHERE id = ?
                AND estado = 'PENDIENTE'
            ";


            $stmtUpdate =
                $conexion->prepare(
                    $sqlUpdate
                );


            if (!$stmtUpdate) {

                throw new Exception(
                    "No fue posible actualizar el material."
                );

            }


            $stmtUpdate->bind_param(
                "si",
                $nuevo_estado,
                $material_id
            );


            if (!$stmtUpdate->execute()) {

                $stmtUpdate->close();

                throw new Exception(
                    "No fue posible actualizar el estado."
                );

            }


            if ($stmtUpdate->affected_rows !== 1) {

                $stmtUpdate->close();

                throw new Exception(
                    "El material ya fue procesado por otro usuario."
                );

            }


            $stmtUpdate->close();


            /*
            |--------------------------------------------------------------------------
            | REGISTRAR AUTORIZACIÓN
            |--------------------------------------------------------------------------
            */

            $sqlAutorizacion = "
                INSERT INTO marketing_autorizaciones
                (
                    material_id,
                    usuario_id,
                    accion,
                    comentario
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";


            $stmtAutorizacion =
                $conexion->prepare(
                    $sqlAutorizacion
                );


            if (!$stmtAutorizacion) {

                throw new Exception(
                    "No fue posible registrar la autorización."
                );

            }


            $stmtAutorizacion->bind_param(
                "iiss",
                $material_id,
                $usuario_id,
                $accion,
                $comentario
            );


            if (!$stmtAutorizacion->execute()) {

                $stmtAutorizacion->close();

                throw new Exception(
                    "No fue posible guardar la autorización."
                );

            }


            $stmtAutorizacion->close();


            /*
            |--------------------------------------------------------------------------
            | PREPARAR AUDITORÍA
            |--------------------------------------------------------------------------
            */

            if ($accion === 'AUTORIZADO') {

                $accion_auditoria =
                    "AUTORIZAR_MATERIAL";

                $descripcion_auditoria =
                    "Se autorizó el material: " .
                    $material['nombre'];

            } else {

                $accion_auditoria =
                    "RECHAZAR_MATERIAL";

                $descripcion_auditoria =
                    "Se rechazó el material: " .
                    $material['nombre'] .
                    ". Motivo: " .
                    $comentario;

            }


            $modulo =
                "AUTORIZACIONES";


            /*
            |--------------------------------------------------------------------------
            | REGISTRAR AUDITORÍA
            |--------------------------------------------------------------------------
            */

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


            if (!$stmtAuditoria) {

                throw new Exception(
                    "No fue posible preparar la auditoría."
                );

            }


            $stmtAuditoria->bind_param(
                "issis",
                $empleado_id,
                $accion_auditoria,
                $modulo,
                $material_id,
                $descripcion_auditoria
            );


            if (!$stmtAuditoria->execute()) {

                $stmtAuditoria->close();

                throw new Exception(
                    "No fue posible registrar la auditoría."
                );

            }


            $stmtAuditoria->close();


            /*
            |--------------------------------------------------------------------------
            | CONFIRMAR TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            $conexion->commit();


            if ($accion === 'AUTORIZADO') {

                $mensaje =
                    "El material fue autorizado correctamente.";

            } else {

                $mensaje =
                    "El material fue rechazado correctamente.";

            }

        } catch (Throwable $e) {

            $conexion->rollback();

            $error =
                $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| CONSULTAR MATERIALES PENDIENTES
|--------------------------------------------------------------------------
*/

$materiales = [];


$sqlPendientes = "
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

    WHERE m.estado = 'PENDIENTE'

    ORDER BY m.fecha_subida ASC
";


$resultadoPendientes =
    $conexion->query(
        $sqlPendientes
    );


if ($resultadoPendientes) {

    while (
        $fila =
        $resultadoPendientes->fetch_assoc()
    ) {

        $materiales[] = $fila;

    }

}


/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$total_pendientes =
    count($materiales);


/*
|--------------------------------------------------------------------------
| FUNCIONES AUXILIARES
|--------------------------------------------------------------------------
*/

function formato_bytes_autorizacion($bytes)
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
    )
    . ' '
    . $unidades[$indice];
}


function es_imagen_autorizacion($extension)
{
    return in_array(
        strtolower((string)$extension),
        [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ],
        true
    );
}


function icono_archivo_autorizacion($extension)
{
    switch (strtolower((string)$extension)) {

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
                    CONTROL DE CONTENIDO
                </div>

                <h1>
                    Autorizaciones
                </h1>

                <p>
                    Revisa y autoriza los materiales enviados
                    por el equipo de Marketing.
                </p>

            </div>


            <div class="contador-pendientes">

                <span>
                    Pendientes
                </span>

                <strong>
                    <?= $total_pendientes ?>
                </strong>

            </div>

        </section>


        <!-- =====================================================
             MENSAJES
        ====================================================== -->

        <?php if ($mensaje !== ''): ?>

            <div class="mensaje-exito">

                <span>✓</span>

                <?= htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="mensaje-error">

                <span>⚠</span>

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             INFORMACIÓN
        ====================================================== -->

        <section class="aviso">

            <div class="aviso-icono">
                ℹ️
            </div>

            <div>

                <strong>
                    Revisión de materiales
                </strong>

                <p>
                    Los materiales permanecen pendientes hasta
                    que Gerencia los autorice o rechace.
                </p>

            </div>

        </section>


        <!-- =====================================================
             LISTADO
        ====================================================== -->

        <section class="panel-autorizaciones">


            <div class="panel-titulo">

                <div>

                    <h2>
                        Materiales pendientes
                    </h2>

                    <p>
                        Ordenados del más antiguo al más reciente.
                    </p>

                </div>

            </div>


            <?php if (empty($materiales)): ?>


                <div class="sin-pendientes">

                    <div class="sin-icono">
                        ✓
                    </div>

                    <h3>
                        No hay materiales pendientes
                    </h3>

                    <p>
                        Todos los materiales enviados
                        han sido procesados.
                    </p>

                </div>


            <?php else: ?>


                <div class="lista-autorizaciones">


                    <?php foreach ($materiales as $material): ?>


                        <?php

                        $es_imagen =
                            es_imagen_autorizacion(
                                $material['extension']
                            );

                        ?>


                        <article class="tarjeta-material">


                            <!-- =================================================
                                 VISTA PREVIA
                            ================================================== -->

                            <div class="vista-previa">


                                <?php if ($es_imagen): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $material['ruta_archivo'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt="Vista previa del material"
                                    >

                                <?php else: ?>

                                    <div class="archivo-grande">

                                        <div>

                                            <?= icono_archivo_autorizacion(
                                                $material['extension']
                                            ) ?>

                                        </div>

                                        <span>

                                            <?= strtoupper(
                                                htmlspecialchars(
                                                    $material['extension'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            ) ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <a
                                    href="<?= htmlspecialchars(
                                        $material['ruta_archivo'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn-ver-archivo"
                                >
                                    👁 Abrir archivo
                                </a>

                            </div>


                            <!-- =================================================
                                 INFORMACIÓN
                            ================================================== -->

                            <div class="informacion-material">


                                <div class="material-encabezado">

                                    <div>

                                        <span class="badge-pendiente">
                                            PENDIENTE
                                        </span>

                                        <h2>

                                            <?= htmlspecialchars(
                                                $material['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h2>

                                    </div>

                                </div>


                                <?php if (
                                    !empty(
                                        $material['descripcion']
                                    )
                                ): ?>

                                    <p class="descripcion">

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $material['descripcion'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )
                                        ) ?>

                                    </p>

                                <?php endif; ?>


                                <div class="datos-material">


                                    <div class="dato">

                                        <span>
                                            CATEGORÍA
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $material['categoria']
                                                ?: 'Sin categoría',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="dato">

                                        <span>
                                            CAMPAÑA
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $material['campana']
                                                ?: 'Sin campaña',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="dato">

                                        <span>
                                            SUBIDO POR
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $material['empleado_subio']
                                                ?: 'Usuario',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="dato">

                                        <span>
                                            FECHA
                                        </span>

                                        <strong>

                                            <?= date(
                                                'd/m/Y H:i',
                                                strtotime(
                                                    $material['fecha_subida']
                                                )
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="dato">

                                        <span>
                                            ARCHIVO
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $material['nombre_archivo_original']
                                                ?: 'Sin nombre',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="dato">

                                        <span>
                                            TAMAÑO
                                        </span>

                                        <strong>

                                            <?= formato_bytes_autorizacion(
                                                $material['tamano_bytes']
                                            ) ?>

                                        </strong>

                                    </div>


                                </div>


                                <!-- =================================================
                                     ACCIONES
                                ================================================== -->

                                <div class="acciones-autorizacion">


                                    <!-- AUTORIZAR -->

                                    <form
                                        method="POST"
                                        onsubmit="return confirmarAutorizacion();"
                                    >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="AUTORIZADO"
                                        >

                                        <input
                                            type="hidden"
                                            name="material_id"
                                            value="<?= (int)$material['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-autorizar"
                                        >
                                            ✓ Autorizar
                                        </button>

                                    </form>


                                    <!-- RECHAZAR -->

                                    <button
                                        type="button"
                                        class="btn-rechazar"
                                        onclick="abrirRechazo(
                                            <?= (int)$material['id'] ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $material['nombre'],
                                                    JSON_UNESCAPED_UNICODE |
                                                    JSON_UNESCAPED_SLASHES
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        )"
                                    >
                                        ✕ Rechazar
                                    </button>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


<!-- ==========================================================
     MODAL RECHAZO
========================================================== -->

<div
    id="modalRechazo"
    class="modal"
>


    <div class="modal-contenido">


        <div class="modal-header">

            <div>

                <span class="etiqueta">
                    DEVOLVER MATERIAL
                </span>

                <h2>
                    Rechazar material
                </h2>

                <p id="nombreMaterialRechazo">
                </p>

            </div>


            <button
                type="button"
                class="modal-cerrar"
                onclick="cerrarRechazo()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            onsubmit="return validarRechazo();"
        >


            <input
                type="hidden"
                name="accion"
                value="RECHAZADO"
            >


            <input
                type="hidden"
                name="material_id"
                id="materialRechazoId"
                value=""
            >


            <div class="form-grupo">

                <label for="comentario">
                    Motivo del rechazo *
                </label>

                <textarea
                    id="comentario"
                    name="comentario"
                    rows="5"
                    maxlength="2000"
                    placeholder="Indica qué debe corregirse o modificarse..."
                    required
                ></textarea>

                <small>
                    Este comentario quedará registrado en el historial.
                </small>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-cancelar"
                    onclick="cerrarRechazo()"
                >
                    Cancelar
                </button>


                <button
                    type="submit"
                    class="btn-rechazar"
                >
                    Rechazar material
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

    display: block;

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
   CONTADOR
========================================================== */

.contador-pendientes {

    min-width: 120px;

    padding: 14px 20px;

    background: white;

    border-radius: 10px;

    border: 1px solid #e5e7eb;

    text-align: center;

    box-shadow:
        0 3px 12px rgba(0,0,0,.04);
}


.contador-pendientes span {

    display: block;

    color: #64748b;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.contador-pendientes strong {

    display: block;

    color: #c2410c;

    font-size: 26px;

    margin-top: 3px;
}


/* ==========================================================
   MENSAJES
========================================================== */

.mensaje-exito,
.mensaje-error {

    border-radius: 8px;

    padding: 13px 16px;

    margin-bottom: 18px;

    display: flex;

    gap: 10px;

    align-items: center;

    font-size: 14px;
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
   AVISO
========================================================== */

.aviso {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    background: #eff6ff;

    border: 1px solid #bfdbfe;

    color: #1e40af;

    padding: 15px 17px;

    border-radius: 9px;

    margin-bottom: 20px;
}


.aviso-icono {

    font-size: 20px;
}


.aviso strong {

    display: block;

    font-size: 13px;

    margin-bottom: 3px;
}


.aviso p {

    margin: 0;

    font-size: 12px;

    line-height: 1.5;
}


/* ==========================================================
   PANEL
========================================================== */

.panel-autorizaciones {

    background: white;

    border-radius: 11px;

    overflow: hidden;

    box-shadow:
        0 3px 12px rgba(0,0,0,.05);
}


.panel-titulo {

    padding: 20px 22px;

    border-bottom: 1px solid #e5e7eb;
}


.panel-titulo h2 {

    margin: 0 0 5px;

    font-size: 18px;

    color: #172033;
}


.panel-titulo p {

    margin: 0;

    color: #64748b;

    font-size: 12px;
}


/* ==========================================================
   LISTA
========================================================== */

.lista-autorizaciones {

    padding: 20px;
}


/* ==========================================================
   TARJETA
========================================================== */

.tarjeta-material {

    display: grid;

    grid-template-columns:
        280px 1fr;

    gap: 22px;

    border: 1px solid #e5e7eb;

    border-radius: 11px;

    padding: 16px;

    margin-bottom: 16px;

    background: #fff;
}


.tarjeta-material:last-child {

    margin-bottom: 0;
}


/* ==========================================================
   PREVISUALIZACIÓN
========================================================== */

.vista-previa {

    min-height: 240px;

    border-radius: 9px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    overflow: hidden;

    position: relative;

    display: flex;

    align-items: center;

    justify-content: center;
}


.vista-previa img {

    width: 100%;

    height: 240px;

    object-fit: contain;

    background: #f1f5f9;
}


.archivo-grande {

    text-align: center;

    color: #64748b;
}


.archivo-grande div {

    font-size: 70px;

    margin-bottom: 10px;
}


.archivo-grande span {

    font-size: 13px;

    font-weight: bold;
}


.btn-ver-archivo {

    position: absolute;

    bottom: 10px;

    left: 10px;

    right: 10px;

    text-align: center;

    padding: 9px;

    background: rgba(23,32,51,.90);

    color: white;

    text-decoration: none;

    border-radius: 6px;

    font-size: 12px;

    font-weight: bold;
}


.btn-ver-archivo:hover {

    background: rgba(23,32,51,1);
}


/* ==========================================================
   INFORMACIÓN
========================================================== */

.informacion-material {

    min-width: 0;
}


.material-encabezado {

    display: flex;

    justify-content: space-between;

    margin-bottom: 8px;
}


.badge-pendiente {

    display: inline-block;

    background: #fff7ed;

    color: #c2410c;

    border-radius: 20px;

    padding: 5px 9px;

    font-size: 10px;

    font-weight: bold;

    margin-bottom: 7px;
}


.material-encabezado h2 {

    margin: 0;

    font-size: 21px;

    color: #172033;

    word-break: break-word;
}


.descripcion {

    color: #64748b;

    font-size: 13px;

    line-height: 1.55;

    margin: 8px 0 18px;
}


/* ==========================================================
   DATOS
========================================================== */

.datos-material {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

    margin-bottom: 20px;
}


.dato {

    padding: 10px 12px;

    background: #f8fafc;

    border-radius: 7px;

    min-width: 0;
}


.dato span {

    display: block;

    color: #94a3b8;

    font-size: 9px;

    font-weight: bold;

    letter-spacing: .5px;

    margin-bottom: 4px;
}


.dato strong {

    display: block;

    color: #475569;

    font-size: 12px;

    overflow-wrap: anywhere;
}


/* ==========================================================
   ACCIONES
========================================================== */

.acciones-autorizacion {

    border-top: 1px solid #e5e7eb;

    padding-top: 15px;

    display: flex;

    gap: 9px;

    align-items: center;
}


.acciones-autorizacion form {

    margin: 0;
}


.btn-autorizar,
.btn-rechazar {

    border: none;

    padding: 10px 17px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: bold;

    cursor: pointer;
}


.btn-autorizar {

    background: #047857;

    color: white;
}


.btn-autorizar:hover {

    background: #065f46;
}


.btn-rechazar {

    background: #b91c1c;

    color: white;
}


.btn-rechazar:hover {

    background: #991b1b;
}


/* ==========================================================
   SIN PENDIENTES
========================================================== */

.sin-pendientes {

    padding: 75px 20px;

    text-align: center;
}


.sin-icono {

    width: 58px;

    height: 58px;

    margin: 0 auto 15px;

    border-radius: 50%;

    background: #ecfdf5;

    color: #047857;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 27px;

    font-weight: bold;
}


.sin-pendientes h3 {

    margin: 0 0 7px;

    font-size: 18px;

    color: #172033;
}


.sin-pendientes p {

    margin: 0;

    color: #64748b;

    font-size: 13px;
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

    max-width: 560px;

    background: white;

    border-radius: 12px;

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

    margin: 0 0 6px;

    font-size: 20px;
}


.modal-header p {

    margin: 0;

    color: #64748b;

    font-size: 12px;

    word-break: break-word;
}


.modal-cerrar {

    border: none;

    background: transparent;

    font-size: 28px;

    color: #64748b;

    cursor: pointer;
}


.modal-contenido form {

    padding: 22px 24px;
}


.form-grupo {

    margin-bottom: 15px;
}


.form-grupo label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    color: #374151;

    margin-bottom: 7px;
}


.form-grupo textarea {

    width: 100%;

    box-sizing: border-box;

    border: 1px solid #d1d5db;

    border-radius: 7px;

    padding: 11px 12px;

    font-family: Arial, sans-serif;

    font-size: 13px;

    resize: vertical;

    outline: none;
}


.form-grupo textarea:focus {

    border-color: #b91c1c;

    box-shadow:
        0 0 0 3px rgba(185,28,28,.10);
}


.form-grupo small {

    display: block;

    margin-top: 6px;

    color: #94a3b8;

    font-size: 11px;
}


/* ==========================================================
   FOOTER
========================================================== */

.modal-footer {

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    padding-top: 5px;
}


.btn-cancelar {

    border: 1px solid #d1d5db;

    background: white;

    color: #475569;

    padding: 10px 16px;

    border-radius: 7px;

    cursor: pointer;

    font-weight: bold;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 850px) {

    .tarjeta-material {

        grid-template-columns: 1fr;

    }


    .vista-previa {

        max-width: 400px;

    }

}


@media (max-width: 600px) {

    .encabezado-pagina {

        flex-direction: column;

        align-items: flex-start;

    }


    .contador-pendientes {

        width: 100%;

        box-sizing: border-box;

    }


    .datos-material {

        grid-template-columns: 1fr;

    }


    .acciones-autorizacion {

        flex-direction: column;

        align-items: stretch;

    }


    .acciones-autorizacion form {

        width: 100%;

    }


    .btn-autorizar,
    .btn-rechazar {

        width: 100%;

    }

}

</style>


<script>

/* ==========================================================
   MODAL RECHAZO
========================================================== */

function abrirRechazo(id, nombre) {

    document.getElementById(
        'materialRechazoId'
    ).value = id;


    document.getElementById(
        'nombreMaterialRechazo'
    ).textContent =
        'Material: ' + nombre;


    document.getElementById(
        'comentario'
    ).value = '';


    document.getElementById(
        'modalRechazo'
    ).classList.add('visible');


    setTimeout(function() {

        document.getElementById(
            'comentario'
        ).focus();

    }, 100);

}


/* ==========================================================
   CERRAR MODAL
========================================================== */

function cerrarRechazo() {

    document.getElementById(
        'modalRechazo'
    ).classList.remove('visible');

}


/* ==========================================================
   CLICK FUERA DEL MODAL
========================================================== */

document
    .getElementById('modalRechazo')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {

                cerrarRechazo();

            }

        }
    );


/* ==========================================================
   VALIDAR RECHAZO
========================================================== */

function validarRechazo() {

    const comentario =
        document.getElementById(
            'comentario'
        ).value.trim();


    if (comentario === '') {

        alert(
            'Debes indicar el motivo del rechazo.'
        );

        return false;

    }


    return confirm(
        '¿Deseas rechazar este material?'
    );

}


/* ==========================================================
   CONFIRMAR AUTORIZACIÓN
========================================================== */

function confirmarAutorizacion() {

    return confirm(
        '¿Confirmas que deseas AUTORIZAR este material?'
    );

}


/* ==========================================================
   ESC PARA CERRAR MODAL
========================================================== */
        
document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            cerrarRechazo();

        }

    }
);

</script>