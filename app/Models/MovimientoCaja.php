<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_cajas';
    protected $primaryKey = 'id_movimiento';
    public $timestamps = false;

    protected $fillable = [
        'id_sesion',
        'tipo',
        'monto',
        'motivo',
        'hora',
    ];

    protected $casts = [
        'hora'  => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionCaja::class, 'id_sesion', 'id_sesion');
    }
}

