<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compatibilidad extends Model
{
    protected $table = 'compatibilidades';
    protected $primaryKey = 'id_compatibilidad';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_vehiculo',
        'notas_posicion',
    ];

    // ─── Relaciones ───
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo', 'id_vehiculo');
    }
}
