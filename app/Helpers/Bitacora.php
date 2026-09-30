<?php

namespace App\Helpers;

use App\Models\BitacoraAuditoria;

class Bitacora
{
    /**
     * Registra una entrada en la bitácora de auditoría.
     * Se invoca desde los controladores después de cada operación CUD.
     *
     * @param string      $modulo    Módulo afectado (ej: 'Catálogo / Productos')
     * @param string      $accion    Tipo de acción (INSERT, UPDATE, DELETE, LOGIN, LOGOUT, ANULACION)
     * @param int|null    $registroId ID del registro afectado
     * @param string      $detalle   Descripción del cambio realizado
     */
    public static function registrar(string $modulo, string $accion, ?int $registroId, string $detalle): void
    {
        BitacoraAuditoria::create([
            'id_usuario'      => auth()->id(),
            'modulo_afectado' => $modulo,
            'accion'          => $accion,
            'registro_id'     => $registroId,
            'detalle_cambio'  => $detalle,
            'ip_origen'       => request()->ip(),
        ]);
    }
}
