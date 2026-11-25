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
        'imagen_url',
        'estado',
    ];

    protected $casts = [
        'precio_venta_sugerido' => 'decimal:2',
        'margen_ganancia' => 'decimal:2',
        'estado' => 'boolean',
    ];

    protected $appends = [
        'foto_url',
        'foto_fallback_url',
    ];

    /**
     * Resuelve la ruta local relativa de la fotografía de estudio según la categoría o nombre de la pieza.
     */
    public function resolverImagenPorCategoria(): string
    {
        $nombreCategoria = '';
        if ($this->relationLoaded('categoria') && $this->categoria) {
            $nombreCategoria = mb_strtolower((string) $this->categoria->nombre);
        }

        $nombrePieza = mb_strtolower((string) ($this->nombre_pieza ?? ''));
        $texto = trim($nombreCategoria . ' ' . $nombrePieza);

        if (preg_match('/(aceite|lubricante|refrigerante|coolant|5w-30|10w-30|10w-40|atf|dot\s*4|liquido|líquido)/u', $texto)) {
            return 'images/repuestos/lubricantes.jpg';
        }

        if (preg_match('/(freno|pastilla|disco de freno|zapata|balata|cilindro|caliper)/u', $texto)) {
            return 'images/repuestos/frenos.jpg';
        }

        if (preg_match('/(encendido|inyecc|bujía|bujia|bobina|inyector|bomba de gasolina|cuerpo de aceleraci)/u', $texto)) {
            return 'images/repuestos/encendido.jpg';
        }

        if (preg_match('/(suspensi|direcci|amortiguador|muñón|munon|rótula|rotula|terminal|bieleta|buje|cremallera|axial|bandeja)/u', $texto)) {
            return 'images/repuestos/suspension.jpg';
        }

        if (preg_match('/(filtro)/u', $texto)) {
            return 'images/repuestos/filtros.jpg';
        }

        if (preg_match('/(eléctric|electric|iluminac|sensor|alternador|arrancador|relé|rele|foco|led|halógeno|halogeno|batería|bateria|bocina|farol|optica|óptica)/u', $texto)) {
            return 'images/repuestos/electrico_iluminacion.jpg';
        }

        // Respaldo por ID de categoría cuando no se cargó la relación
        return match ((int) ($this->id_categoria ?? 0)) {
            1 => 'images/repuestos/frenos.jpg',
            2 => 'images/repuestos/encendido.jpg',
            3 => 'images/repuestos/suspension.jpg',
            4 => 'images/repuestos/filtros.jpg',
            7, 8 => 'images/repuestos/electrico_iluminacion.jpg',
            default => 'images/repuestos/motor_transmision.jpg',
        };
    }

    /**
     * URL de respaldo garantizada según categoría.
     */
    public function getFotoFallbackUrlAttribute(): string
    {
        return asset($this->resolverImagenPorCategoria());
    }

    /**
     * URL pública principal de la fotografía del producto.
     */
    public function getFotoUrlAttribute(): string
    {
        $raw = trim((string) ($this->imagen_url ?? ''));
        if ($raw !== '') {
            return str_starts_with($raw, 'http') ? $raw : asset(ltrim($raw, '/'));
        }

        return $this->foto_fallback_url;
    }

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
