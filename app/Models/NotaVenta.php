<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaVenta extends Model
{
    protected $table = 'notas_ventas';
    protected $primaryKey = 'id_venta';
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_usuario_vendedor',
        'id_sesion_caja',
        'fecha_hora',
        'total_venta',
        'descuento_total',
        'metodo_pago',
        'estado',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_hora'      => 'datetime',
        'total_venta'     => 'decimal:2',
        'descuento_total' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function vendedor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_vendedor', 'id_usuario');
    }

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class, 'id_sesion_caja', 'id_sesion');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }
}
