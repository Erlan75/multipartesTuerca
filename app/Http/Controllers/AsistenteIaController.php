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
     * Paso 1: Procesa consultas analíticas Read-Only o prepara acciones transaccionales (CU39).
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
                'texto_pantalla' => 'No fue posible procesar la consulta en este momento. Intenta consultar por repuesto, código OEM, rotación, rentabilidad o registrar una operación.',
                'texto_voz'      => 'No fue posible procesar la consulta en este momento.',
                'datos_resumen'  => [],
            ]);
        }
    }

    /**
     * Paso 2: Confirma o cancela una acción transaccional preparada mediante action_token,
     * ejecutando directamente el procedimiento almacenado Smart-DB en PostgreSQL.
     */
    public function confirmar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_token' => 'required|string|max:100',
            'cancelar'     => 'nullable|boolean',
        ]);

        $token = $validated['action_token'];
        $sessionKey = "ai_pending_action.{$token}";
        $payload = session()->pull($sessionKey);

        if (!is_array($payload)) {
            $msg = 'La solicitud de confirmación ya fue procesada o expiró. Vuelve a indicar la operación si deseas repetirla.';
            return response()->json([
                'ok'             => false,
                'tipo'           => 'error',
                'origen'         => 'smartdb',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ], 422);
        }

        if (!empty($validated['cancelar'])) {
            $msg = 'Operación cancelada. No se realizó ningún cambio en la base de datos.';
            return response()->json([
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ]);
        }

        $usuario = Auth::user();
        if (!$usuario || (int) ($payload['user_id'] ?? 0) !== (int) $usuario->id_usuario) {
            $msg = 'El token de confirmación no corresponde al usuario autenticado actual.';
            return response()->json([
                'ok'             => false,
                'tipo'           => 'restriccion',
                'origen'         => 'smartdb',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ], 403);
        }

        // Expiración de seguridad: 10 minutos
        if ((now()->timestamp - (int) ($payload['created_at'] ?? 0)) > 600) {
            $msg = 'El borrador de confirmación expiró por seguridad (más de 10 minutos). Por favor vuelve a dictar o escribir la operación.';
            return response()->json([
                'ok'             => false,
                'tipo'           => 'error',
                'origen'         => 'smartdb',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ], 422);
        }

        $usuario->loadMissing('rol');
        $rolId = (int) $usuario->id_rol;
        $nombreRol = $usuario->rol->nombre_rol ?? 'Usuario';

        $resultado = $this->assistantService->ejecutarAccionConfirmada(
            $payload,
            (int) $usuario->id_usuario,
            $rolId,
            $nombreRol,
            $request->ip() ?? '127.0.0.1'
        );

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
    }
}
