<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Comunidad;
use Illuminate\Http\Request;

class RecomendadorController extends Controller
{
    public function formulario()
    {
        $comunidades = Comunidad::with('provincias.localidades')
            ->orderBy('nombre')
            ->get();

        return view('orientacion.formulario', compact('comunidades'));
    }

    public function recomendar(Request $request)
    {
        $request->validate([
            'situacion_laboral' => 'required',
            'comunidad' => 'nullable',
            'provincia' => 'nullable',
            'ciudad' => 'nullable',
            'modalidad' => 'required',
            'area_interes' => 'required',
            'nivel' => 'required',
            'objetivo' => 'required',
        ]);

        $perfil = $request->all();

        $cursos = Curso::where('activo', true)->get();

        $provinciasComunidad = [];

        if ($request->filled('comunidad')) {
            $comunidad = Comunidad::with('provincias')
                ->where('nombre', $request->comunidad)
                ->first();

            if ($comunidad) {
                $provinciasComunidad = $comunidad->provincias
                    ->pluck('nombre')
                    ->toArray();
            }
        }

        $recomendaciones = [];

        foreach ($cursos as $curso) {
            $puntuacion = 0;
            $motivos = [];

            if ($request->filled('modalidad') && $perfil['modalidad'] === $curso->modalidad) {
                $puntuacion += 30;
                $motivos[] = 'Coincide con tu modalidad preferida';
            }

            if ($request->filled('provincia') && $perfil['provincia'] === $curso->provincia) {
                $puntuacion += 25;
                $motivos[] = 'Se imparte en la provincia que has elegido';
            } elseif ($request->filled('comunidad') && in_array($curso->provincia, $provinciasComunidad)) {
                $puntuacion += 15;
                $motivos[] = 'Pertenece a la comunidad autónoma seleccionada';
            }

            if ($request->filled('ciudad') && $perfil['ciudad'] === $curso->ciudad) {
                $puntuacion += 20;
                $motivos[] = 'Coincide con tu localidad preferida';
            }

            $situacionesPermitidas = explode(',', $curso->situacion_permitida);

            if (in_array($perfil['situacion_laboral'], $situacionesPermitidas)) {
                $puntuacion += 25;
                $motivos[] = 'Es adecuado para tu situación laboral';
            }

            if ($perfil['area_interes'] === $curso->area) {
                $puntuacion += 20;
                $motivos[] = 'Coincide con tu área de interés';
            }

            if ($perfil['nivel'] === $curso->nivel) {
                $puntuacion += 10;
                $motivos[] = 'El nivel del curso encaja contigo';
            }

            if ($puntuacion > 0) {
                $recomendaciones[] = [
                    'curso' => $curso,
                    'puntuacion' => $puntuacion,
                    'motivos' => $motivos,
                ];
            }
        }

        usort($recomendaciones, function ($a, $b) {
            return $b['puntuacion'] <=> $a['puntuacion'];
        });

        $recomendaciones = array_slice($recomendaciones, 0, 5);

        return view('orientacion.resultados', compact('recomendaciones', 'perfil'));
    }
}