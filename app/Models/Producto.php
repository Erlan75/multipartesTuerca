<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';
    public $timestamps = false;

    protected $fillable = [
        'id_categoria',
        'codigo_interno',
        'codigo_oem',
        'nombre_pieza',
        'marca_fabricante',
        'procedencia',
        'precio_venta_sugerido',
        'margen_ganancia',
        'unidad_medida',
        'stock_actual',
        'stock_minimo',
        'stock_seguridad',
        'pasillo',
        'estante',
        'nivel_gaveta',
        'estado',
    ];

    protected $casts = [
        'precio_venta_sugerido' => 'decimal:2',
        'margen_ganancia' => 'decimal:2',
        'estado' => 'boolean',
    ];

    // ─── Relaciones ───
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function codigosAlternativos()
    {
        return $this->hasMany(CodigoAlternativo::class, 'id_producto', 'id_producto');
    }

    public function compatibilidades()
    {
        return $this->hasMany(Compatibilidad::class, 'id_producto', 'id_producto');
    }

    public function vehiculos()
    {
        return $this->belongsToMany(
            Vehiculo::class,
            'compatibilidades',
            'id_producto',
            'id_vehiculo'
        )->withPivot('id_compatibilidad', 'notas_posicion');
    }

    public function ajustes()
    {
        return $this->hasMany(AjusteInventario::class, 'id_producto', 'id_producto');
    }

    public function detallesCompras()
    {
        return $this->hasMany(DetalleCompra::class, 'id_producto', 'id_producto');
    }
}
