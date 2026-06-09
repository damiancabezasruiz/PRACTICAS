<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>ATU Orienta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Fuente Inter --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Iconos --}}
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    {{-- CSS propio --}}
    <link rel="stylesheet" href="{{ asset('css/atu.css') }}">
</head>

<body class="atu-body">
    <header class="atu-header">
        <div class="container-fluid px-4">
            <div class="top-mini-bar d-none d-lg-flex justify-content-between align-items-center">
                <div class="d-flex gap-4 small-info">
                    <span>
                        <span class="material-symbols-outlined icon-small">call</span>
                        663852990
                    </span>

                    <span>
                        <span class="material-symbols-outlined icon-small">mail</span>
                        info@grupoatu.com
                    </span>

                    <span>
                        <span class="material-symbols-outlined icon-small">location_on</span>
                        ¿Dónde estamos?
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <input type="text" class="search-top" placeholder="Buscar cursos...">

                    <a href="{{ route('orientacion.formulario') }}" class="btn btn-top-student">
                        Espacio del Alumno
                    </a>
                </div>
            </div>

            <div class="main-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-4">
                    <a href="{{ route('inicio') }}" class="atu-logo-img">
                        <img src="{{ asset('img/logo.jpg') }}" alt="Logo ATU">
                    </a>

                    <nav class="main-menu d-none d-lg-flex gap-3">
                        <a href="{{ route('cursos.index') }}">Cursos</a>
                        <a href="{{ route('orientacion.formulario') }}">Orientación</a>
                        <a href="#">Empresas</a>
                        <a href="#">Oposiciones</a>
                        <a href="#">Blog</a>
                    </nav>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <a href="{{ route('orientacion.formulario') }}" class="btn btn-warning fw-bold rounded-pill px-3">
                        Buscar mi curso
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="sub-header-dark">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div class="breadcrumb-dark">
                <span>Inicio</span>
                <span class="material-symbols-outlined icon-small">chevron_right</span>
                <span class="active">Cursos</span>
            </div>

            @php
                $vistaActual = request('vista', 'compacta');
                $ordenActual = request('orden', 'relevancia');
            @endphp

            <div class="d-none d-md-flex gap-2">
                <a href="{{ route('cursos.index', array_merge(request()->query(), ['vista' => 'grid'])) }}"
                   class="view-btn {{ $vistaActual === 'grid' ? 'active' : '' }}">
                    <span class="material-symbols-outlined">grid_view</span>
                </a>

                <a href="{{ route('cursos.index', array_merge(request()->query(), ['vista' => 'lista'])) }}"
                   class="view-btn {{ $vistaActual === 'lista' ? 'active' : '' }}">
                    <span class="material-symbols-outlined">view_list</span>
                </a>

                <a href="{{ route('cursos.index', array_merge(request()->query(), ['vista' => 'compacta'])) }}"
                   class="view-btn {{ $vistaActual === 'compacta' ? 'active' : '' }}">
                    <span class="material-symbols-outlined">apps</span>
                </a>

                <div class="dropdown">
                    <button class="sort-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <span class="material-symbols-outlined icon-small">sort</span>
                        {{ $ordenActual === 'titulo' ? 'Título' : ($ordenActual === 'modalidad' ? 'Modalidad' : 'Relevancia') }}
                    </button>

                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item"
                               href="{{ route('cursos.index', array_merge(request()->query(), ['orden' => 'relevancia'])) }}">
                                Relevancia
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item"
                               href="{{ route('cursos.index', array_merge(request()->query(), ['orden' => 'titulo'])) }}">
                                Título
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item"
                               href="{{ route('cursos.index', array_merge(request()->query(), ['orden' => 'modalidad'])) }}">
                                Modalidad
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <main class="catalog-wrapper">
        <div class="container-fluid px-4 py-4">
            @yield('contenido')
        </div>
    </main>

    <footer class="atu-footer-dark">
        <div class="container-fluid px-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="footer-logo-img">
                <img src="{{ asset('img/logo.jpg') }}" alt="Logo ATU">
            </div>

            <div class="footer-links d-flex flex-wrap gap-3">
                <a href="#">Aviso legal</a>
                <a href="#">Privacidad</a>
                <a href="#">Cookies</a>
                <a href="#">Contacto</a>
                <a href="#">Sedes</a>
            </div>
        </div>
    </footer>

    {{-- Bootstrap JS para el desplegable de ordenación --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>