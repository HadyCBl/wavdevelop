<!-- ESTE ES MENU PARA LOS INDICADOS  -->

<nav class="sidebar ">
    <header>
        <div class="image-text">
            <span class="image">
                <img src="../includes/img/logomicro.png" alt="">
            </span>

            <div class="text logo-text">
                <span class="name">COOPERANAME</span>
                <span class="profession">SOTECPRO</span>
            </div>
        </div>

        <i class='bx bx-chevron-right toggle'></i>
    </header>
<!--aqui inicia el menu-->
    <div class="menu-bar">
        <div class="menu">
        <li class="search-box">
        </li>
        <ul class="menu-links">
            <li class="nav-link">
                <a  onclick="printdiv('proteccion', '#cuadro', 'perlas_crud', '0')">
                <i class="fa-solid fa-p fa-xl" id="ico2"></i>
                    <span class="text nav-text" id="txtmenu"> Proteccion </span>
                </a>
            </li>
            <li class="nav-link">
            <a  onclick="printdiv('estructura', '#cuadro', 'perlas_crud', '0')">
            <i class="fa-solid fa-e fa-xl" id="ico2"></i>
                    <span class="text nav-text" id="txtmenu">Estructura</span>
                </a>
            </li>
            <li class="nav-link">
            <a  onclick="printdiv('activos', '#cuadro', 'perlas_crud', '0')">
            <i class="fa-solid fa-a fa-xl" id="ico2"></i>
                    <span class="text nav-text" id="txtmenu">Activos</span>
                </a>
            </li>
            <li class="nav-link">
            <a  onclick="printdiv('rendimiento', '#cuadro', 'perlas_crud', '0')">
            <i class="fa-solid fa-r fa-xl" id="ico2"></i>
                    <span class="text nav-text" id="txtmenu">Rendimiento</span>
                </a>
            </li>
            <li class="nav-link">
            <a  onclick="printdiv('liquidez', '#cuadro', 'perlas_crud', '0')">
            <i class="fa-solid fa-l fa-xl" id="ico2"></i>
                    <span class="text nav-text" id="txtmenu">Liquidez</span>
                </a>
            </li>
            <li class="nav-link">
            <a  onclick="printdiv('señales', '#cuadro', 'perlas_crud', '0')">
            <i class="fa-solid fa-s fa-xl" id="ico2"></i>
                    <span class="text nav-text" id="txtmenu">Señales</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="bottom-content">
        <li class="">
            <a href="../login.php">
                <i class='bx bx-log-out icon'></i>
                <span class="text nav-text">Cerrar Session</span>
            </a>
        </li>
        <li class="mode">
            <div class="sun-moon">
                <i class='bx bx-moon icon moon'></i>
                <i class='bx bx-sun icon sun'></i>
            </div>
            <span class="mode-text text">Modo Oscuro</span>
            <div class="toggle-switch">
                <span class="switch"></span>
            </div>
        </li>
        </div>
    </div>
<!---aqui finaliza el menu -->
</nav>