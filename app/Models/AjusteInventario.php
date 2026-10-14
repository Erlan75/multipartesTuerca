<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteInventario extends Model
{
    protected $table = 'ajustes_inventario';
    protected $primaryKey = 'id_ajuste';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_usuario',
        'cantidad_ajustada',
        'tipo_ajuste',
        'motivo',
        'fecha_ajuste',
    ];

    protected $casts = [
        'cantidad_ajustada' => 'integer',
        'fecha_ajuste'      => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
