<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';

verificar_marketing();

$nombre = empleado_actual();
$rol = rol_marketing();


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$totalMateriales = 0;
$totalCampanas = 0;
$totalPendientes = 0;
$totalAutorizados = 0;


/*
|--------------------------------------------------------------------------
| TOTAL MATERIALes
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM marketing_materiales
";

$resultado = $conexion->query($sql);

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalMateriales = (int)$fila['total'];
}


/*
|--------------------------------------------------------------------------
| TOTAL CAMPAÑAS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM marketing_campanas
";

$resultado = $conexion->query($sql);

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalCampanas = (int)$fila['total'];
}


/*
|--------------------------------------------------------------------------
| MATERIAL PENDIENTE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM marketing_materiales
    WHERE estado = 'PENDIENTE'
";

$resultado = $conexion->query($sql);

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalPendientes = (int)$fila['total'];
}


/*
|--------------------------------------------------------------------------
| MATERIAL AUTORIZADO
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM marketing_materiales
    WHERE estado = 'AUTORIZADO'
";

$resultado = $conexion->query($sql);

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalAutorizados = (int)$fila['total'];
}

?>


<?php require_once __DIR__ . '/menu.php'; ?>


<div class="contenido-principal">

    <main class="contenido">


        <!-- =====================================================
             ENCABEZADO
        ====================================================== -->

        <section class="bienvenida-marketing">

            <div>

                <div class="etiqueta">
                    PANEL DE MARKETING
                </div>

                <h1>
                    Bienvenido, <?= htmlspecialchars($nombre) ?>
                </h1>

                <p>
                    Administra campañas, materiales,
                    publicaciones y autorizaciones desde un solo lugar.
                </p>

            </div>


            <div class="rol-badge">

                <?= htmlspecialchars($rol) ?>

            </div>

        </section>


        <!-- =====================================================
             TARJETAS
        ====================================================== -->

        <section class="estadisticas">


            <div class="estadistica">

                <div class="estadistica-icono">
                    📁
                </div>

                <div>

                    <div class="estadistica-numero">
                        <?= $totalMateriales ?>
                    </div>

                    <div class="estadistica-titulo">
                        Materiales
                    </div>

                </div>

            </div>


            <div class="estadistica">

                <div class="estadistica-icono">
                    📢
                </div>

                <div>

                    <div class="estadistica-numero">
                        <?= $totalCampanas ?>
                    </div>

                    <div class="estadistica-titulo">
                        Campañas
                    </div>

                </div>

            </div>


            <div class="estadistica">

                <div class="estadistica-icono">
                    ⏳
                </div>

                <div>

                    <div class="estadistica-numero">
                        <?= $totalPendientes ?>
                    </div>

                    <div class="estadistica-titulo">
                        Pendientes
                    </div>

                </div>

            </div>


            <div class="estadistica">

                <div class="estadistica-icono">
                    ✅
                </div>

                <div>

                    <div class="estadistica-numero">
                        <?= $totalAutorizados ?>
                    </div>

                    <div class="estadistica-titulo">
                        Autorizados
                    </div>

                </div>

            </div>


        </section>


        <!-- =====================================================
             ACCESOS RÁPIDOS
        ====================================================== -->

        <section class="seccion">

            <div class="seccion-titulo">

                <div>

                    <h2>
                        Accesos rápidos
                    </h2>

                    <p>
                        Accede rápidamente a las principales funciones.
                    </p>

                </div>

            </div>


            <div class="accesos">


                <a
                    href="materiales.php"
                    class="acceso"
                >

                    <span class="acceso-icono">
                        📁
                    </span>

                    <span>

                        <strong>
                            Materiales
                        </strong>

                        <small>
                            Flyers, imágenes, videos y documentos
                        </small>

                    </span>

                </a>


                <a
                    href="campanas.php"
                    class="acceso"
                >

                    <span class="acceso-icono">
                        📢
                    </span>

                    <span>

                        <strong>
                            Campañas
                        </strong>

                        <small>
                            Crear y administrar campañas
                        </small>

                    </span>

                </a>


                <a
                    href="calendario.php"
                    class="acceso"
                >

                    <span class="acceso-icono">
                        📅
                    </span>

                    <span>

                        <strong>
                            Calendario
                        </strong>

                        <small>
                            Programar contenido
                        </small>

                    </span>

                </a>


                <?php if (
                    $rol === 'ADMIN' ||
                    $rol === 'GERENCIA'
                ): ?>

                    <a
                        href="autorizaciones.php"
                        class="acceso"
                    >

                        <span class="acceso-icono">
                            ✅
                        </span>

                        <span>

                            <strong>
                                Autorizaciones
                            </strong>

                            <small>
                                Revisar materiales pendientes
                            </small>

                        </span>

                    </a>

                <?php endif; ?>


            </div>

        </section>


        <!-- =====================================================
             PRÓXIMAMENTE
        ====================================================== -->

        <section class="seccion">

            <div class="seccion-titulo">

                <div>

                    <h2>
                        Planeación de Marketing
                    </h2>

                    <p>
                        Aquí concentraremos la planeación de contenidos.
                    </p>

                </div>

            </div>


            <div class="planeacion">


                <div class="planeacion-item">

                    <span>
                        🌤️
                    </span>

                    <div>

                        <strong>
                            Temporadas
                        </strong>

                        <p>
                            Contenido recomendado según la temporada.
                        </p>

                    </div>

                </div>


                <div class="planeacion-item">

                    <span>
                        🔔
                    </span>

                    <div>

                        <strong>
                            Recordatorios
                        </strong>

                        <p>
                            Control de fechas para subir contenido.
                        </p>

                    </div>

                </div>


                <div class="planeacion-item">

                    <span>
                        📈
                    </span>

                    <div>

                        <strong>
                            Reportes
                        </strong>

                        <p>
                            Seguimiento de campañas y materiales.
                        </p>

                    </div>

                </div>


            </div>

        </section>


    </main>

</div>


<style>

/* ==========================================================
   DASHBOARD MARKETING
   ========================================================== */

.bienvenida-marketing {

    background: white;

    border-radius: 12px;

    padding: 25px 28px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.06);
}


.etiqueta {

    font-size: 11px;

    font-weight: bold;

    color: #245784;

    letter-spacing: 1px;

    margin-bottom: 6px;
}


.bienvenida-marketing h1 {

    margin: 0 0 8px;

    font-size: 27px;

    color: #172033;
}


.bienvenida-marketing p {

    margin: 0;

    color: #64748b;

    font-size: 14px;
}


.rol-badge {

    background: #eef4f9;

    color: #245784;

    padding: 9px 15px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    white-space: nowrap;
}


/* ==========================================================
   ESTADÍSTICAS
   ========================================================== */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.estadistica {

    background: white;

    border-radius: 11px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 15px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.05);
}


.estadistica-icono {

    width: 48px;

    height: 48px;

    border-radius: 10px;

    background: #eef4f9;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;
}


.estadistica-numero {

    font-size: 25px;

    font-weight: bold;

    color: #172033;
}


.estadistica-titulo {

    color: #64748b;

    font-size: 13px;

    margin-top: 2px;
}


/* ==========================================================
   SECCIONES
   ========================================================== */

.seccion {

    background: white;

    border-radius: 12px;

    padding: 24px;

    margin-bottom: 22px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.05);
}


.seccion-titulo {

    margin-bottom: 18px;
}


.seccion-titulo h2 {

    margin: 0 0 5px;

    font-size: 19px;
}


.seccion-titulo p {

    margin: 0;

    color: #64748b;

    font-size: 13px;
}


/* ==========================================================
   ACCESOS
   ========================================================== */

.accesos {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 14px;
}


.acceso {

    display: flex;

    align-items: center;

    gap: 15px;

    padding: 16px;

    border: 1px solid #e5e7eb;

    border-radius: 9px;

    text-decoration: none;

    color: #1f2937;

    transition: .2s;
}


.acceso:hover {

    border-color: #245784;

    background: #f8fafc;

    transform: translateY(-1px);
}


.acceso-icono {

    font-size: 27px;
}


.acceso strong {

    display: block;

    font-size: 15px;

    margin-bottom: 4px;
}


.acceso small {

    display: block;

    color: #64748b;

    font-size: 12px;
}


/* ==========================================================
   PLANEACIÓN
   ========================================================== */

.planeacion {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;
}


.planeacion-item {

    border: 1px solid #e5e7eb;

    border-radius: 9px;

    padding: 18px;

    display: flex;

    gap: 13px;

    align-items: flex-start;
}


.planeacion-item > span {

    font-size: 25px;
}


.planeacion-item strong {

    font-size: 14px;
}


.planeacion-item p {

    color: #64748b;

    font-size: 12px;

    margin: 5px 0 0;

    line-height: 1.4;
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 1000px) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .planeacion {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 700px) {

    .accesos {

        grid-template-columns: 1fr;
    }

    .bienvenida-marketing {

        align-items: flex-start;

        flex-direction: column;
    }
}


@media (max-width: 500px) {

    .estadisticas {

        grid-template-columns: 1fr;
    }

    .contenido {

        padding: 18px;
    }
}

</style>