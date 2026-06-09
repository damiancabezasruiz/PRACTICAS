@extends('layouts.app')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="mb-4">
            <h1 class="page-title">Cuestionario de orientación</h1>
            <p class="page-subtitle">
                Completa este formulario y te recomendaremos los cursos más adecuados para ti.
            </p>
        </div>

        <div class="card form-card">
            <div class="card-body p-4">
                <form action="{{ route('orientacion.recomendar') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Situación laboral</label>
                        <select name="situacion_laboral" class="form-select" required>
                            <option value="">Selecciona una opción</option>
                            <option value="desempleado">Desempleado</option>
                            <option value="trabajador">Trabajador</option>
                            <option value="autonomo">Autónomo</option>
                            <option value="erte">ERE/ERTE</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comunidad Autónoma</label>
                        <select name="comunidad" id="comunidad" class="form-select">
                            <option value="">Todas las Comunidades</option>

                            @foreach($comunidades as $comunidad)
                                <option value="{{ $comunidad->nombre }}">
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

                    <div class="mb-3">
                        <label class="form-label">Modalidad preferida</label>
                        <select name="modalidad" class="form-select" required>
                            <option value="">Selecciona una modalidad</option>
                            <option value="online">Online</option>
                            <option value="presencial">Presencial</option>
                            <option value="mixta">Mixta</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Área de interés</label>
                        <select name="area_interes" class="form-select" required>
                            <option value="">Selecciona un área</option>
                            <option value="informatica">Informática</option>
                            <option value="administracion">Administración</option>
                            <option value="logistica">Logística</option>
                               <option value="electricidad">Electricidad</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nivel</label>
                        <select name="nivel" class="form-select" required>
                            <option value="">Selecciona tu nivel</option>
                            <option value="basico">Básico</option>
                            <option value="medio">Medio</option>
                            <option value="avanzado">Avanzado</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Objetivo profesional</label>
                        <select name="objetivo" class="form-select" required>
                            <option value="">Selecciona un objetivo</option>
                            <option value="encontrar_empleo">Encontrar empleo</option>
                            <option value="mejorar_cv">Mejorar mi currículum</option>
                            <option value="cambiar_sector">Cambiar de sector</option>
                            <option value="actualizar_conocimientos">Actualizar conocimientos</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-modern">
                            Ver recomendaciones
                        </button>

                        <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary btn-modern">
                            Volver al catálogo
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const datosLocalizacion = {!! json_encode($comunidades ?? []) !!};

    const comunidadSelect = document.getElementById('comunidad');
    const provinciaSelect = document.getElementById('provincia');
    const ciudadSelect = document.getElementById('ciudad');

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