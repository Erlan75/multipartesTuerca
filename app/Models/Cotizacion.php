<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    protected $primaryKey = 'id_cotizacion';
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_usuario',
        'fecha_emision',
        'fecha_vencimiento',
        'monto_total',
        'estado',
    ];

    protected $casts = [
        'fecha_emision'     => 'datetime',
        'fecha_vencimiento' => 'date',
        'monto_total'       => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCotizacion::class, 'id_cotizacion', 'id_cotizacion');
    }

    /**
     * Retorna el estado visual normalizado: VIGENTE, VENCIDA, CONVERTIDA, ANULADA.
     */
    public function getEstadoVisualAttribute(): string
    {
        if ($this->estado === 'Concretada/Facturada') {
            return 'CONVERTIDA';
        }
        if ($this->estado === 'Anulada') {
            return 'ANULADA';
        }
        if ($this->estado === 'Vencida') {
            return 'VENCIDA';
        }

        $hoy = Carbon::today();
        $vencimiento = Carbon::parse($this->fecha_vencimiento)->startOfDay();
        if ($hoy->gt($vencimiento)) {
            return 'VENCIDA';
        }

        return 'VIGENTE';
    }

    /**
     * Retorna los días restantes (>= 0) o vencidos (< 0) como entero limpio.
     */
    public function getDiasRestantesAttribute(): int
    {
        $hoy = Carbon::today();
        $vencimiento = Carbon::parse($this->fecha_vencimiento)->startOfDay();
        return (int) round($hoy->diffInDays($vencimiento, false));
    }
}
