<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraAuditoria extends Model
{
    protected $table = 'bitacoras_auditorias';
    protected $primaryKey = 'id_bitacora';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha_hora',
        'modulo_afectado',
        'accion',
        'registro_id',
        'detalle_cambio',
        'ip_origen',
    ];

    // ─── Relaciones ───
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
