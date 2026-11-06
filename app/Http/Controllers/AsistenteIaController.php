<?php

namespace App\Http\Controllers;

use App\Services\AiSqlAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AsistenteIaController extends Controller
{
    public function __construct(
        private readonly AiSqlAssistantService $assistantService
    ) {}

    /**
     * Procesa consultas multimodales por voz o texto del Asistente Inteligente (Voice-to-SQL).
     */
    public function consultar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consulta' => 'required|string|min:2|max:500',
        ], [
            'consulta.required' => 'Por favor escribe o dicta una consulta para el asistente.',
            'consulta.min'      => 'La consulta debe tener al menos 2 caracteres.',
        ]);

        try {
            $usuario = Auth::user();
            if ($usuario) {
                $usuario->loadMissing('rol');
            }

            $respuesta = $this->assistantService->procesarConsulta(
                $validated['consulta'],
                $usuario
            );

            return response()->json($respuesta);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => 'local',
                'texto_pantalla' => 'No fue posible procesar la consulta en este momento. Intenta consultar por nombre de repuesto, código OEM, vehículo, ventas del día o cuentas por pagar.',
                'texto_voz'      => 'No fue posible procesar la consulta en este momento. Intenta consultar por nombre de repuesto, código O E M, vehículo, ventas del día o cuentas por pagar.',
                'datos_resumen'  => [],
            ]);
        }
    }
}
