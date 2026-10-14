<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    protected $table = 'detalles_compras';
    protected $primaryKey = 'id_detalle_compra';
    public $timestamps = false;

    protected $fillable = [
        'id_compra',
        'id_producto',
        'cantidad_recibida',
        'costo_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad_recibida' => 'integer',
        'costo_unitario'    => 'decimal:2',
        'subtotal'          => 'decimal:2',
    ];

    public function compra()
    {
        return $this->belongsTo(NotaCompra::class, 'id_compra', 'id_compra');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
