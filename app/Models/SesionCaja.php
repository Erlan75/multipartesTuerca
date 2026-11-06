<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesionCaja extends Model
{
    protected $table = 'sesiones_cajas';
    protected $primaryKey = 'id_sesion';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario_cajero',
        'fecha_hora_apertura',
        'monto_inicial',
        'fecha_hora_cierre',
        'monto_esperado_sistema',
        'monto_real_contado',
        'diferencia',
        'estado',
    ];

    protected $casts = [
        'fecha_hora_apertura'    => 'datetime',
        'fecha_hora_cierre'      => 'datetime',
        'monto_inicial'          => 'decimal:2',
        'monto_esperado_sistema' => 'decimal:2',
        'monto_real_contado'     => 'decimal:2',
        'diferencia'             => 'decimal:2',
    ];

    public function cajero()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_cajero', 'id_usuario');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoCaja::class, 'id_sesion', 'id_sesion');
    }

    public function ventas()
    {
        return $this->hasMany(NotaVenta::class, 'id_sesion_caja', 'id_sesion');
    }
}

