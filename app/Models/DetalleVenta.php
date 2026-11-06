<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $table = 'detalles_ventas';
    protected $primaryKey = 'id_detalle_venta';
    public $timestamps = false;

    protected $fillable = [
        'id_venta',
        'id_producto',
        'cantidad_vendida',
        'precio_unitario_cobrado',
        'subtotal',
    ];

    protected $casts = [
        'precio_unitario_cobrado' => 'decimal:2',
        'subtotal'                => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(NotaVenta::class, 'id_venta', 'id_venta');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}

