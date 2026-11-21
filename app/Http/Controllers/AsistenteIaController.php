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
     * Paso 1: Procesa consultas analíticas o guía paso a paso una operación comercial por voz o texto.
     */
    public function consultar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consulta' => 'required|string|min:1|max:500',
        ], [
            'consulta.required' => 'Escríbeme o díctame qué necesitas y con gusto te ayudo.',
            'consulta.min'      => 'Dime un poquito más para poder ayudarte.',
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
            $msg = 'Uy, tuve un pequeño inconveniente para procesar eso. ¿Me lo puedes repetir con otras palabras?';
            return response()->json([
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ]);
        }
    }

    /**
     * Paso 2: Confirma o cancela una operación preparada tras revisión del usuario.
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

        // Limpiar cualquier borrador conversacional residual al confirmar o cancelar
        $this->assistantService->limpiarBorradorOperacion();

        if (!is_array($payload)) {
            $msg = 'Ese resumen ya fue procesado o pasó mucho tiempo desde que lo armamos. Si quieres, dímelo de nuevo y lo preparamos en un momento.';
            return response()->json([
                'ok'             => false,
                'tipo'           => 'error',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ], 422);
        }

        if (!empty($validated['cancelar'])) {
            $msg = '¡Listo, cancelado! No se guardó ningún cambio. ¿Te ayudo con otra cosa?';
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
            $msg = 'Esta operación fue preparada desde otra cuenta. Por seguridad, vuelve a pedirla desde tu sesión.';
            return response()->json([
                'ok'             => false,
                'tipo'           => 'restriccion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $msg,
                'datos_resumen'  => [],
            ], 403);
        }

        // Expiración de seguridad: 10 minutos
        if ((now()->timestamp - (int) ($payload['created_at'] ?? 0)) > 600) {
            $msg = 'Pasaron más de 10 minutos desde que preparamos este resumen, así que lo cerré por seguridad. Dime de nuevo y lo armamos enseguida.';
            return response()->json([
                'ok'             => false,
                'tipo'           => 'error',
                'origen'         => 'local',
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

    /**
     * Reinicia la conversación y borra cualquier borrador pendiente en sesión.
     */
    public function limpiar(): JsonResponse
    {
        $this->assistantService->limpiarConversacionCompleta();
        $msg = '¡Listo! Empezamos una charla nueva desde cero. ¿En qué te ayudo hoy?';

        return response()->json([
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $msg,
            'texto_voz'      => $msg,
            'datos_resumen'  => [],
        ]);
    }
}
