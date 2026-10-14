<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaCompra extends Model
{
    protected $table = 'notas_compras';
    protected $primaryKey = 'id_compra';
    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_usuario_receptor',
        'numero_factura_nota',
        'fecha_compra',
        'monto_total',
        'tipo_pago',
        'estado_pago',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'monto_total'  => 'decimal:2',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    public function usuarioReceptor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_receptor', 'id_usuario');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class, 'id_compra', 'id_compra');
    }
}
