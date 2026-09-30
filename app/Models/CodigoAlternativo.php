<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodigoAlternativo extends Model
{
    protected $table = 'codigos_alternativos';
    protected $primaryKey = 'id_codigo_alt';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'codigo_referencia',
        'marca_alternativa',
    ];

    // ─── Relaciones ───
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
