<?php

namespace App\Helpers;

use App\Models\BitacoraAuditoria;

class Bitacora
{
    /**
     * Registra una entrada en la bitácora de auditoría.
     * Se invoca desde los controladores después de cada operación.
     *
     * @param string      $modulo     Módulo afectado (ej: 'Seguridad y Autenticación', 'Perfil de Usuario')
     * @param string      $accion     Tipo de acción (INSERT, UPDATE, DELETE, LOGIN, LOGOUT, LOGIN_FALLIDO, CUENTA_BLOQUEADA, etc.)
     * @param int|null    $registroId ID del registro afectado
     * @param string      $detalle    Descripción del cambio o evento
     * @param int|null    $idUsuario  ID del usuario que realiza o recibe la acción (opcional, usa auth()->id() si es null)
     */
    public static function registrar(
        string $modulo,
        string $accion,
        ?int $registroId,
        string $detalle,
        ?int $idUsuario = null
    ): void {
        BitacoraAuditoria::create([
            'id_usuario'      => $idUsuario ?? auth()->id(),
            'fecha_hora'      => now(),
            'modulo_afectado' => $modulo,
            'accion'          => strtoupper($accion),
            'registro_id'     => $registroId,
            'detalle_cambio'  => $detalle,
            'ip_origen'       => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
