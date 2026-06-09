<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatCursoController extends Controller
{
    public function vista(Curso $curso)
    {
        return view('cursos.asistente', compact('curso'));
    }

    public function preguntar(Request $request, Curso $curso)
    {
        $request->validate([
            'pregunta' => 'required|string|max:800',
        ]);

        $pregunta = trim($request->input('pregunta'));

        $apiKey = env('OPENAI_API_KEY');
        $modelo = env('OPENAI_MODEL', 'gpt-4.1-mini');

        if (!$apiKey || $apiKey === 'pon_aqui_tu_clave' || $apiKey === 'sk-...') {
            return response()->json([
                'respuesta' => $this->respuestaLocal($pregunta, $curso),
                'modo' => 'local',
            ]);
        }

        try {
            $contextoCurso = "
Datos del curso:
Título: {$curso->titulo}
Descripción: {$curso->descripcion}
Área: {$curso->area}
Modalidad: {$curso->modalidad}
Provincia: {$curso->provincia}
Ciudad: {$curso->ciudad}
Nivel: {$curso->nivel}
Colectivos admitidos: {$curso->situacion_permitida}
Requisitos: {$curso->requisitos}
Enlace informativo: {$curso->enlace}
";

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $modelo,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => '
Eres un orientador formativo de Grupo ATU.

Responde siempre en español.
Responde de forma clara, lógica, útil y breve.
Usa los datos del curso proporcionados.
Puedes razonar a partir de modalidad, área, descripción, nivel y requisitos.
No inventes fechas, horarios, duración, plazas, precio ni documentación si no aparecen.
Si falta un dato oficial, dilo claramente.
Si el alumno pregunta por ordenador, conexión, modalidad, requisitos, desempleo, inscripción, nivel o ubicación, responde razonando con los datos disponibles.
',
                        ],
                        [
                            'role' => 'user',
                            'content' => $contextoCurso . "\nPregunta del alumno: " . $pregunta,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'respuesta' => $this->respuestaLocal($pregunta, $curso),
                    'modo' => 'local',
                    'error_api' => $response->status(),
                ]);
            }

            $data = $response->json();

            $respuesta = $data['output'][0]['content'][0]['text']
                ?? $data['output_text']
                ?? $this->respuestaLocal($pregunta, $curso);

            return response()->json([
                'respuesta' => $respuesta,
                'modo' => 'api',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'respuesta' => $this->respuestaLocal($pregunta, $curso),
                'modo' => 'local',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function respuestaLocal(string $pregunta, Curso $curso): string
    {
        $texto = mb_strtolower($pregunta);

        $titulo = $curso->titulo;
        $descripcion = $curso->descripcion;
        $area = $curso->area;
        $modalidad = mb_strtolower($curso->modalidad ?? '');
        $nivel = $curso->nivel;
        $provincia = $curso->provincia;
        $ciudad = $curso->ciudad;
        $colectivos = mb_strtolower($curso->situacion_permitida ?? '');
        $requisitos = $curso->requisitos ?: 'No aparecen requisitos específicos en la ficha.';

        if ($this->contiene($texto, ['ordenador', 'portátil', 'portatil', 'pc', 'equipo', 'internet', 'conexión', 'conexion', 'wifi'])) {
            if ($modalidad === 'online') {
                return "Sí, al ser un curso online, es recomendable que tengas ordenador propio y conexión a internet para seguir las clases, acceder a los materiales y realizar actividades. En la ficha el requisito indicado es: {$requisitos}";
            }

            if ($modalidad === 'mixta') {
                return "Sí, es recomendable tener ordenador y conexión a internet, porque el curso es de modalidad mixta y puede tener una parte online. La parte presencial se indica en {$ciudad}, {$provincia}.";
            }

            if ($modalidad === 'presencial') {
                return "No parece imprescindible tener ordenador propio para asistir, porque el curso es presencial. Aun así, puede venirte bien para practicar en casa, sobre todo al ser un curso de {$area}. En la ficha el requisito indicado es: {$requisitos}";
            }

            return "La ficha no especifica si necesitas ordenador propio. Aun así, para un curso de {$area}, es recomendable tener acceso a un ordenador para practicar.";
        }

        if ($this->contiene($texto, ['desempleado', 'paro', 'demandante', 'trabajador', 'trabajando', 'autónomo', 'autonomo'])) {
            if (str_contains($colectivos, 'desempleado') && str_contains($colectivos, 'trabajador')) {
                return "Sí. Este curso admite tanto personas desempleadas como trabajadoras, porque en la ficha aparecen estos colectivos: {$curso->situacion_permitida}.";
            }

            if (str_contains($colectivos, 'desempleado')) {
                return "Sí, este curso está dirigido a personas desempleadas. En la ficha aparece como colectivo admitido: {$curso->situacion_permitida}.";
            }

            if (str_contains($colectivos, 'trabajador')) {
                return "Este curso está orientado a trabajadores. En la ficha aparece como colectivo admitido: {$curso->situacion_permitida}. Si estás desempleado, conviene solicitar información para confirmar si podrías acceder.";
            }

            return "La ficha indica estos colectivos admitidos: {$curso->situacion_permitida}. Si tu situación no aparece claramente, lo mejor es solicitar información.";
        }

        if ($this->contiene($texto, ['requisito', 'requisitos', 'necesario', 'experiencia', 'saber', 'conocimientos previos'])) {
            return "Para acceder a {$titulo}, los requisitos indicados son: {$requisitos}. Por el nivel {$nivel}, parece pensado para un nivel {$nivel}, salvo que la ficha indique algo más específico.";
        }

        if ($this->contiene($texto, ['online', 'presencial', 'mixta', 'modalidad', 'distancia', 'casa'])) {
            if ($modalidad === 'online') {
                return "Este curso es online, así que podrás seguirlo a distancia. Te recomiendo tener ordenador y conexión a internet.";
            }

            if ($modalidad === 'presencial') {
                return "Este curso es presencial. Se imparte en {$ciudad}, provincia de {$provincia}.";
            }

            if ($modalidad === 'mixta') {
                return "Este curso es de modalidad mixta, por lo que combina parte online y parte presencial. La ubicación indicada es {$ciudad}, {$provincia}.";
            }

            return "La modalidad indicada en la ficha es: {$curso->modalidad}.";
        }

        if ($this->contiene($texto, ['dónde', 'donde', 'ubicación', 'ubicacion', 'ciudad', 'provincia', 'lugar', 'sitio'])) {
            return "Este curso se imparte en {$ciudad}, provincia de {$provincia}.";
        }

        if ($this->contiene($texto, ['nivel', 'dificultad', 'básico', 'basico', 'medio', 'avanzado', 'difícil', 'dificil'])) {
            return "El nivel del curso es {$nivel}. Si tienes interés por {$area} y cumples los requisitos indicados, puede encajarte bien.";
        }

        if ($this->contiene($texto, ['apunto', 'inscribir', 'inscripción', 'inscripcion', 'matricular', 'solicitar', 'información', 'informacion'])) {
            return "Para apuntarte o pedir más información, vuelve a la ficha del curso y pulsa el botón “Solicitar información”. Ese botón te llevará al enlace oficial asociado a {$titulo}.";
        }

        if ($this->contiene($texto, ['sirve', 'salidas', 'trabajo', 'empleo', 'objetivo', 'futuro', 'para qué', 'para que'])) {
            return "Este curso puede ayudarte a mejorar competencias en el área de {$area}. Según la descripción: {$descripcion} Si tu objetivo está relacionado con esa área, puede ser una buena opción.";
        }

        if ($this->contiene($texto, ['duración', 'duracion', 'horario', 'fecha', 'empieza', 'plazas', 'precio', 'gratis', 'gratuito'])) {
            return "Ese dato no aparece en la ficha que tengo disponible. Para confirmar duración, fechas, horarios, plazas o precio, lo mejor es pulsar “Solicitar información” y consultarlo directamente con ATU.";
        }

        return "Puedo ayudarte a valorar si {$titulo} encaja contigo. Según la ficha, es un curso de {$area}, modalidad {$curso->modalidad}, nivel {$nivel}, en {$ciudad}, {$provincia}. Puedes preguntarme por requisitos, modalidad, ubicación, colectivos admitidos, inscripción o si necesitas ordenador.";
    }

    private function contiene(string $texto, array $palabras): bool
    {
        foreach ($palabras as $palabra) {
            if (str_contains($texto, mb_strtolower($palabra))) {
                return true;
            }
        }

        return false;
    }
}