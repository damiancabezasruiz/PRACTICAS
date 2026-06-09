<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\RecomendadorController;
use App\Http\Controllers\ChatCursoController;

Route::get('/', [CursoController::class, 'index'])->name('inicio');

Route::get('/cursos', [CursoController::class, 'index'])->name('cursos.index');

Route::get('/cursos/{curso}', [CursoController::class, 'show'])->name('cursos.show');

Route::get('/orientacion', [RecomendadorController::class, 'formulario'])->name('orientacion.formulario');

Route::post('/recomendaciones', [RecomendadorController::class, 'recomendar'])->name('orientacion.recomendar');

Route::post('/cursos/{curso}/chat', [ChatCursoController::class, 'preguntar'])->name('cursos.chat');

Route::get('/cursos/{curso}/asistente', [ChatCursoController::class, 'vista']) ->name('cursos.asistente');

