@extends('layouts.app')

@section('contenido')
<div class="row">
    <aside class="col-lg-3 mb-4">
        <div class="filter-panel">
            <div class="filter-heading">
                <h3>
                    <span class="material-symbols-outlined icon-small">filter_list</span>
                    Filtros
                </h3>

                <a href="{{ route('cursos.index') }}">Limpiar todo</a>
            </div>

            <form action="{{ route('cursos.index') }}" method="GET" id="form-filtros">
                <input type="hidden" name="vista" value="{{ request('vista', 'compacta') }}">
                <input type="hidden" name="orden" value="{{ request('orden', 'relevancia') }}">

                <div class="mb-3">
                    <label class="form-label">Comunidad Autónoma</label>
                    <select name="comunidad" id="comunidad" class="form-select">
                        <option value="">Todas las Comunidades</option>

                        @foreach($comunidades as $comunidad)
                            <option value="{{ $comunidad->nombre }}"
                                {{ request('comunidad') == $comunidad->nombre ? 'selected' : '' }}>
                                {{ $comunidad->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Provincia</label>
                    <select name="provincia" id="provincia" class="form-select">
                        <option value="">Todas las Provincias</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Localidad</label>
                    <select name="ciudad" id="ciudad" class="form-select">
                        <option value="">Todas</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Modalidad</label>
                    <select name="modalidad" class="form-select">
                        <option value="">Todas</option>

                        <option value="online" {{ request('modalidad') == 'online' ? 'selected' : '' }}>
                            Online
                        </option>

                        <option value="presencial" {{ request('modalidad') == 'presencial' ? 'selected' : '' }}>
                            Presencial
                        </option>

                        <option value="mixta" {{ request('modalidad') == 'mixta' ? 'selected' : '' }}>
                            Mixta
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    Aplicar filtros
                </button>
            </form>

            <div class="mt-4 pt-3 border-top" style="border-color: rgba(255,255,255,0.08)!important;">
                <small class="text-light-emphasis">
                    Filtros activos:
                    {{ collect(request()->only(['comunidad', 'provincia', 'ciudad', 'modalidad']))->filter()->count() }}
                </small>
            </div>
        </div>
    </aside>

    <section class="col-lg-9">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h1 class="section-title">Cursos disponibles</h1>
                <p class="section-subtitle">
                    Encuentra formación gratuita según tu perfil, ubicación y modalidad.
                </p>
            </div>

            <span class="badge rounded-pill text-bg-primary px-3 py-2">
                {{ count($cursos) }} cursos
            </span>
        </div>

        @if($cursos->count() == 0)
            <div class="alert alert-warning">
                No hay cursos que coincidan con los filtros seleccionados.
            </div>
        @endif

        @php
            $vista = request('vista', 'compacta');

            if ($vista === 'lista') {
                $columnaCurso = 'col-12';
            } elseif ($vista === 'grid') {
                $columnaCurso = 'col-md-6';
            } else {
                $columnaCurso = 'col-md-6 col-xl-4';
            }
        @endphp

        <div class="row g-4 course-grid">
            @foreach($cursos as $curso)
                @php
                    $imagenes = [
                        1 => 'img/1.jpg',
                        2 => 'img/2.jpg',
                        3 => 'img/3.jpg',
                        4 => 'img/4.jpg',
                        5 => 'img/5.jpg',
                    ];

                    $imagen = $imagenes[$curso->id] ?? 'img/1.jpg';

                    if ($curso->modalidad === 'online') {
                        $tipo = 'Especialidad';
                        $icono = 'school';
                    } else {
                        $tipo = 'Certificado';
                        $icono = 'verified';
                    }
                @endphp

                <div class="{{ $columnaCurso }}">
                    <article class="course-card-dark {{ $vista === 'lista' ? 'course-card-list' : '' }}">
                        <div class="course-thumb">
                            <img src="{{ asset($imagen) }}" alt="{{ $curso->titulo }}">

                            <span class="course-badge-dark">
                                <span class="material-symbols-outlined icon-small">{{ $icono }}</span>
                                {{ $tipo }}
                            </span>
                        </div>

                        <div class="course-content">
                            <div class="course-code-dark">
                                {{ strtoupper($curso->area) }}
                            </div>

                            <h3 class="course-title-dark">
                                {{ $curso->titulo }}
                            </h3>

                            <p class="course-type-dark">
                                {{ ucfirst($curso->modalidad) }} · Nivel {{ ucfirst($curso->nivel) }}
                            </p>

                            <p class="course-description-dark">
                                {{ $curso->descripcion }}
                            </p>

                            <div class="course-bottom">
                                <span>
                                    {{ $curso->provincia }}
                                </span>

                                <a href="{{ route('cursos.show', $curso) }}" class="course-arrow">
                                    <span class="material-symbols-outlined">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>
</div>

<script>
    const datosLocalizacion = {!! json_encode($comunidades ?? []) !!};

    const comunidadSelect = document.getElementById('comunidad');
    const provinciaSelect = document.getElementById('provincia');
    const ciudadSelect = document.getElementById('ciudad');

    const provinciaSeleccionada = {!! json_encode(request('provincia')) !!};
    const ciudadSeleccionada = {!! json_encode(request('ciudad')) !!};

    function cargarProvincias() {
        const comunidadNombre = comunidadSelect.value;

        provinciaSelect.innerHTML = '<option value="">Todas las Provincias</option>';
        ciudadSelect.innerHTML = '<option value="">Todas</option>';

        if (!comunidadNombre) {
            return;
        }

        const comunidad = datosLocalizacion.find(item => item.nombre === comunidadNombre);

        if (!comunidad) {
            return;
        }

        comunidad.provincias.forEach(function (provincia) {
            const option = document.createElement('option');
            option.value = provincia.nombre;
            option.textContent = provincia.nombre;

            if (provincia.nombre === provinciaSeleccionada) {
                option.selected = true;
            }

            provinciaSelect.appendChild(option);
        });
    }

    function cargarLocalidades() {
        const comunidadNombre = comunidadSelect.value;
        const provinciaNombre = provinciaSelect.value;

        ciudadSelect.innerHTML = '<option value="">Todas</option>';

        if (!comunidadNombre || !provinciaNombre) {
            return;
        }

        const comunidad = datosLocalizacion.find(item => item.nombre === comunidadNombre);

        if (!comunidad) {
            return;
        }

        const provincia = comunidad.provincias.find(item => item.nombre === provinciaNombre);

        if (!provincia) {
            return;
        }

        provincia.localidades.forEach(function (localidad) {
            const option = document.createElement('option');
            option.value = localidad.nombre;
            option.textContent = localidad.nombre;

            if (localidad.nombre === ciudadSeleccionada) {
                option.selected = true;
            }

            ciudadSelect.appendChild(option);
        });
    }

    comunidadSelect.addEventListener('change', function () {
        cargarProvincias();
    });

    provinciaSelect.addEventListener('change', function () {
        cargarLocalidades();
    });

    cargarProvincias();
    cargarLocalidades();
</script>
@endsection