<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Curso;
use App\Models\Comunidad;

class CursoController extends Controller
{
 public function index(Request $request)
{
    $query = Curso::where('activo', true);

    if ($request->filled('provincia')) {
        $query->where('provincia', $request->provincia);
    }

    if ($request->filled('ciudad')) {
        $query->where('ciudad', $request->ciudad);
    }

    if ($request->filled('modalidad')) {
        $query->where('modalidad', $request->modalidad);
    }

    if ($request->orden === 'titulo') {
        $query->orderBy('titulo', 'asc');
    } elseif ($request->orden === 'modalidad') {
        $query->orderBy('modalidad', 'asc');
    } else {
        $query->orderBy('id', 'asc');
    }

    $cursos = $query->get();

    $comunidades = Comunidad::with('provincias.localidades')
        ->orderBy('nombre')
        ->get();

    return view('cursos.index', compact('cursos', 'comunidades'));
}
    public function show(Curso $curso)
    {
        return view('cursos.show', compact('curso'));
    }
}