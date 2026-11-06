<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCotizacion extends Model
{
    protected $table = 'detalles_cotizaciones';
    protected $primaryKey = 'id_detalle_cot';
    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_producto',
        'cantidad',
        'precio_unitario_cotizado',
        'subtotal',
    ];

    protected $casts = [
        'precio_unitario_cotizado' => 'decimal:2',
        'subtotal'                 => 'decimal:2',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id_cotizacion');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}

