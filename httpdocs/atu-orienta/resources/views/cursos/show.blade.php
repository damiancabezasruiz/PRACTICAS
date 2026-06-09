@extends('layouts.app')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card detail-card">
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge-modern">{{ ucfirst($curso->area) }}</span>
                    <span class="badge-modern">{{ ucfirst($curso->modalidad) }}</span>
                    <span class="badge-modern">{{ ucfirst($curso->nivel) }}</span>
                </div>

                <h1 class="page-title" style="font-size: 2rem;">
                    {{ $curso->titulo }}
                </h1>

                <p class="page-subtitle mb-4">
                    {{ $curso->descripcion }}
                </p>

                <ul class="list-group info-list mb-4">
                    <li class="list-group-item">
                        <strong>Provincia:</strong> {{ $curso->provincia }}
                    </li>

                    <li class="list-group-item">
                        <strong>Ciudad:</strong> {{ $curso->ciudad }}
                    </li>

                    <li class="list-group-item">
                        <strong>Nivel:</strong> {{ ucfirst($curso->nivel) }}
                    </li>

                    <li class="list-group-item">
                        <strong>Colectivos admitidos:</strong> {{ $curso->situacion_permitida }}
                    </li>

                    <li class="list-group-item">
                        <strong>Requisitos:</strong> {{ $curso->requisitos }}
                    </li>
                </ul>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ $curso->enlace }}" target="_blank" class="btn btn-success btn-modern">
                        Solicitar información
                    </a>

                    <a href="{{ route('cursos.index') }}" class="btn btn-outline-secondary btn-modern">
                        Volver al catálogo
                    </a>

                    <a href="{{ route('cursos.asistente', $curso) }}" class="btn btn-primary btn-modern">
                        <span class="material-symbols-outlined icon-small">chat</span>
                        Orientación sobre este curso
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection