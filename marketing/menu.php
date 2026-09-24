<?php

require_once __DIR__ . '/seguridad.php';

verificar_marketing();

$rol = rol_marketing();
$nombre = empleado_actual();

$pagina_actual = basename($_SERVER['PHP_SELF']);

?>

<style>

/* ==========================================================
   MENU MARKETING
   ========================================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #1f2937;
}


/* ==========================================================
   BARRA SUPERIOR
   ========================================================== */

.topbar {

    position: fixed;

    top: 0;
    left: 0;
    right: 0;

    height: 64px;

    background: #245784;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 25px;

    z-index: 1000;

    box-shadow:
        0 2px 8px rgba(0,0,0,.15);
}


.topbar-left {

    display: flex;

    align-items: center;

    gap: 12px;

    font-size: 21px;

    font-weight: bold;
}


.logo-marketing {

    font-size: 27px;
}


.topbar-right {

    display: flex;

    align-items: center;

    gap: 18px;

    font-size: 14px;
}


.usuario-info {

    text-align: right;

    line-height: 1.3;
}


.usuario-nombre {

    font-weight: bold;

    white-space: nowrap;
}


.usuario-rol {

    font-size: 11px;

    opacity: .85;

    text-transform: uppercase;
}


.btn-salir-top {

    background: #dc2626;

    color: white;

    text-decoration: none;

    padding: 9px 14px;

    border-radius: 6px;

    font-size: 13px;

    font-weight: bold;

    transition: .2s;
}


.btn-salir-top:hover {

    background: #b91c1c;
}


/* ==========================================================
   MENU LATERAL
   ========================================================== */

.sidebar {

    position: fixed;

    top: 64px;

    left: 0;

    bottom: 0;

    width: 245px;

    background: #ffffff;

    border-right: 1px solid #e5e7eb;

    overflow-y: auto;

    z-index: 900;
}


.sidebar-titulo {

    padding: 22px 20px 10px;

    color: #6b7280;

    font-size: 11px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .8px;
}


.menu {

    list-style: none;

    margin: 0;

    padding: 0 10px 20px;
}


.menu li {

    margin-bottom: 4px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 11px 13px;

    border-radius: 7px;

    color: #374151;

    text-decoration: none;

    font-size: 14px;

    transition: .2s;
}


.menu a:hover {

    background: #eef4f9;

    color: #245784;
}


.menu a.activo {

    background: #e6f0f8;

    color: #245784;

    font-weight: bold;
}


.menu-icono {

    width: 23px;

    text-align: center;

    font-size: 17px;
}


/* ==========================================================
   SEPARADORES
   ========================================================== */

.menu-separador {

    margin: 14px 10px 8px;

    border-top: 1px solid #e5e7eb;
}


/* ==========================================================
   CONTENIDO
   ========================================================== */

.contenido-principal {

    margin-left: 245px;

    padding-top: 64px;

    min-height: 100vh;
}


.contenido {

    padding: 30px;
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 800px) {

    .sidebar {

        width: 210px;
    }

    .contenido-principal {

        margin-left: 210px;
    }

    .topbar {

        padding: 0 15px;
    }

    .usuario-info {

        display: none;
    }

}


@media (max-width: 600px) {

    .sidebar {

        position: relative;

        top: 64px;

        width: 100%;

        height: auto;

        border-right: none;
    }

    .contenido-principal {

        margin-left: 0;

        padding-top: 64px;
    }

}

</style>


<!-- ==========================================================
     BARRA SUPERIOR
     ========================================================== -->

<header class="topbar">


    <div class="topbar-left">

        <span class="logo-marketing">
            📣
        </span>

        <span>
            Marketing
        </span>

    </div>


    <div class="topbar-right">


        <div class="usuario-info">

            <div class="usuario-nombre">
                <?= htmlspecialchars($nombre) ?>
            </div>

            <div class="usuario-rol">
                <?= htmlspecialchars($rol) ?>
            </div>

        </div>


        <a
            href="logout.php"
            class="btn-salir-top"
        >
            Salir
        </a>


    </div>

</header>


<!-- ==========================================================
     MENU LATERAL
     ========================================================== -->

<aside class="sidebar">


    <div class="sidebar-titulo">
        Panel de control
    </div>


    <ul class="menu">


        <!-- DASHBOARD -->

        <li>

            <a
                href="index.php"
                class="<?= $pagina_actual === 'index.php' ? 'activo' : '' ?>"
            >

                <span class="menu-icono">
                    📊
                </span>

                Dashboard

            </a>

        </li>


        <!-- MATERIALES -->

        <li>

            <a
                href="materiales.php"
                class="<?= $pagina_actual === 'materiales.php' ? 'activo' : '' ?>"
            >

                <span class="menu-icono">
                    📁
                </span>

                Materiales

            </a>

        </li>


        <!-- CAMPAÑAS -->

        <li>

            <a
                href="campanas.php"
                class="<?= $pagina_actual === 'campanas.php' ? 'activo' : '' ?>"
            >

                <span class="menu-icono">
                    📢
                </span>

                Campañas

            </a>

        </li>


        <!-- CALENDARIO -->

        <li>

            <a
                href="calendario.php"
                class="<?= $pagina_actual === 'calendario.php' ? 'activo' : '' ?>"
            >

                <span class="menu-icono">
                    📅
                </span>

                Calendario

            </a>

        </li>


        <?php if (
            $rol === 'ADMIN' ||
            $rol === 'GERENCIA'
        ): ?>


            <li>

                <a
                    href="autorizaciones.php"
                    class="<?= $pagina_actual === 'autorizaciones.php' ? 'activo' : '' ?>"
                >

                    <span class="menu-icono">
                        ✅
                    </span>

                    Autorizaciones

                </a>

            </li>


        <?php endif; ?>


        <div class="menu-separador"></div>


        <!-- TEMPORADAS -->

        <li>

            <a
                href="temporadas.php"
                class="<?= $pagina_actual === 'temporadas.php' ? 'activo' : '' ?>"
            >

                <span class="menu-icono">
                    🌤️
                </span>

                Temporadas

            </a>

        </li>


        <!-- RECORDATORIOS -->

        <li>

            <a
                href="recordatorios.php"
                class="<?= $pagina_actual === 'recordatorios.php' ? 'activo' : '' ?>"
            >

                <span class="menu-icono">
                    🔔
                </span>

                Recordatorios

            </a>

        </li>


        <?php if ($rol === 'AUDITOR'): ?>


            <li>

                <a
                    href="historial.php"
                    class="<?= $pagina_actual === 'historial.php' ? 'activo' : '' ?>"
                >

                    <span class="menu-icono">
                        🔎
                    </span>

                    Historial

                </a>

            </li>


        <?php endif; ?>


        <?php if ($rol === 'ADMIN'): ?>


            <div class="menu-separador"></div>


            <!-- USUARIOS -->

            <li>

                <a
                    href="usuarios.php"
                    class="<?= $pagina_actual === 'usuarios.php' ? 'activo' : '' ?>"
                >

                    <span class="menu-icono">
                        👥
                    </span>

                    Usuarios

                </a>

            </li>


        <?php endif; ?>


        <?php if (
            $rol === 'ADMIN' ||
            $rol === 'GERENCIA' ||
            $rol === 'AUDITOR'
        ): ?>


            <li>

                <a
                    href="reportes.php"
                    class="<?= $pagina_actual === 'reportes.php' ? 'activo' : '' ?>"
                >

                    <span class="menu-icono">
                        📈
                    </span>

                    Reportes

                </a>

            </li>


        <?php endif; ?>


    </ul>


</aside>