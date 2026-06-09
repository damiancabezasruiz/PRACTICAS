@extends('layouts.app')

@section('contenido')
<div class="mb-4">
    <h1 class="page-title">Cursos recomendados para ti</h1>
    <p class="page-subtitle">
        Hemos analizado tu perfil y estas son las mejores coincidencias.
    </p>
</div>

@if(count($recomendaciones) > 0)
    <div class="row g-4">
        @foreach($recomendaciones as $recomendacion)
            @php
                $curso = $recomendacion['curso'];

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

            <div class="col-md-6 col-xl-4">
                <article class="course-card-dark h-100">
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

                        <div class="mb-3">
                            <span class="badge text-bg-primary">
                                {{ $recomendacion['puntuacion'] }} puntos
                            </span>
                        </div>

                        <h6 class="text-white">Motivos:</h6>

                        <ul class="text-light-emphasis">
                            @foreach($recomendacion['motivos'] as $motivo)
                                <li>{{ $motivo }}</li>
                            @endforeach
                        </ul>

                        <div class="course-bottom">
                            <span>{{ $curso->provincia }}</span>

                            <a href="{{ route('cursos.show', $curso) }}" class="course-arrow">
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@else
    <div class="alert alert-warning">
        No hemos encontrado cursos que coincidan con tu perfil.
    </div>
@endif

<div class="mt-4 d-flex gap-2">
    <a href="{{ route('orientacion.formulario') }}" class="btn btn-primary">
        Volver al cuestionario
    </a>

    <a href="{{ route('cursos.index') }}" class="btn btn-outline-light">
        Ver catálogo completo
    </a>
</div>
@endsection