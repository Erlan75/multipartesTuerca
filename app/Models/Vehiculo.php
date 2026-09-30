<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';
    protected $primaryKey = 'id_vehiculo';
    public $timestamps = false;

    protected $fillable = [
        'marca',
        'modelo',
        'anio_inicio',
        'anio_fin',
        'motor_cilindrada',
        'combustible',
    ];

    // ─── Relaciones ───
    public function compatibilidades()
    {
        return $this->hasMany(Compatibilidad::class, 'id_vehiculo', 'id_vehiculo');
    }

    public function productos()
    {
        return $this->belongsToMany(
            Producto::class,
            'compatibilidades',
            'id_vehiculo',
            'id_producto'
        )->withPivot('id_compatibilidad', 'notas_posicion');
    }

    // ─── Accessors ───
    public function getDescripcionCompletaAttribute()
    {
        return "{$this->marca} {$this->modelo} ({$this->anio_inicio}-{$this->anio_fin}) {$this->motor_cilindrada}";
    }
}
