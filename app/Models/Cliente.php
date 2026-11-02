<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'id_cliente';
    public $timestamps = false;

    protected $fillable = [
        'nombre_razon_social',
        'ci_nit',
        'telefono',
        'tipo_cliente',
        'descuento_frecuente',
    ];

    protected $casts = [
        'descuento_frecuente' => 'decimal:2',
    ];

    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class, 'id_cliente', 'id_cliente');
    }

    public function ventas()
    {
        return $this->hasMany(NotaVenta::class, 'id_cliente', 'id_cliente');
    }
}
