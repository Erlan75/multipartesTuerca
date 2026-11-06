@extends('layouts.app')
@section('title', 'Cartera de Clientes y Talleres — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Clientes y Talleres</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-people-fill me-2" style="color:#007AFF;"></i>Cartera de Clientes Particulares y Talleres
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Directorio comercial de propietarios particulares, talleres mecánicos aliados y empresas con flotas vehiculares.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('clientes.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-person-plus-fill me-1"></i>Nuevo Cliente / Taller
        </a>
        <a href="{{ route('cotizaciones.create') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.15rem;font-size:0.85rem;">
            <i class="bi bi-file-earmark-plus me-1"></i>Emitir Cotización
        </a>
    </div>
</div>

{{-- ═══ TARJETAS RESUMEN ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Total Cartera</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ $stats->total_clientes }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#FFFBEB;color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-tools"></i>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Talleres Aliados</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ $stats->total_talleres }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#F5F3FF;color:#7C3AED;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Empresas / Flotas</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ $stats->total_empresas }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-person-check"></i>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Particulares</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ $stats->total_particulares }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ BARRA DE FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('clientes.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Búsqueda Comercial</label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="Nombre, razón social, CI/NIT o teléfono/WhatsApp..."
                           value="{{ request('buscar') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Tipo de Cliente</label>
                <select name="tipo_cliente" class="form-select form-select-dark">
                    <option value="">Todos los segmentos</option>
                    <option value="Particular" {{ in_array(request('tipo_cliente'), ['Particular', 'PARTICULAR']) ? 'selected' : '' }}>Particular</option>
                    <option value="Taller/Mecánico" {{ in_array(request('tipo_cliente'), ['Taller/Mecánico', 'TALLER']) ? 'selected' : '' }}>Taller / Mecánico Aliado</option>
                    <option value="Empresa/Flota" {{ in_array(request('tipo_cliente'), ['Empresa/Flota', 'EMPRESA']) ? 'selected' : '' }}>Empresa / Flota</option>
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Beneficio</label>
                <select name="con_descuento" class="form-select form-select-dark">
                    <option value="">Todos</option>
                    <option value="1" {{ request('con_descuento') === '1' ? 'selected' : '' }}>Con Descuento Autorizado</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="{{ route('clientes.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE CLIENTES ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:900px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Cliente / Razón Social</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">CI / NIT</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Teléfono / WhatsApp</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Segmento</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Límite Descuento</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:2px solid #E2E8F0;">Historial Comercial</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cl)
                    @php
                        $webMatch = $usuariosWeb->first(function($u) use ($cl) {
                            return mb_strtolower(trim($u->nombre_completo)) === mb_strtolower(trim($cl->nombre_razon_social))
                                || (!empty($cl->telefono) && $cl->telefono !== '00000000' && trim((string)$u->telefono) === trim((string)$cl->telefono));
                        });
                    @endphp
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:0.75rem 1rem;">
                            <div class="d-flex align-items-center gap-2">
                                <span style="font-weight:600;color:#1E293B;font-size:0.87rem;">{{ $cl->nombre_razon_social }}</span>
                                @if($webMatch)
                                    <span class="badge" style="background:#EFF6FF;color:#007AFF;border:1px solid #BFDBFE;font-size:0.65rem;" title="Vinculado con cuenta web: {{ $webMatch->correo }}">
                                        <i class="bi bi-globe2 me-1"></i>Cuenta Web
                                    </span>
                                @endif
                            </div>
                            <div style="font-size:0.72rem;color:#94A3B8;">ID #{{ $cl->id_cliente }}</div>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-family:monospace;font-size:0.83rem;color:#475569;font-weight:600;">
                                {{ $cl->ci_nit ?: 'S/N' }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.83rem;">
                            @if($cl->telefono && $cl->telefono !== '00000000')
                                <a href="https://wa.me/591{{ preg_replace('/[^0-9]/', '', $cl->telefono) }}" target="_blank" style="color:#059669;text-decoration:none;font-weight:500;">
                                    <i class="bi bi-whatsapp me-1"></i>{{ $cl->telefono }}
                                </a>
                            @else
                                <span style="color:#94A3B8;">Sin teléfono</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            @if($cl->tipo_cliente === 'Taller/Mecánico')
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;">
                                    <i class="bi bi-tools"></i> Taller / Mecánico
                                </span>
                            @elseif($cl->tipo_cliente === 'Empresa/Flota')
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#F5F3FF;color:#7C3AED;border:1px solid #DDD6FE;">
                                    <i class="bi bi-building"></i> Empresa / Flota
                                </span>
                            @else
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#F1F5F9;color:#475569;border:1px solid #E2E8F0;">
                                    <i class="bi bi-person"></i> Particular
                                </span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($cl->descuento_frecuente > 0)
                                <span class="badge-activo">Hasta {{ number_format($cl->descuento_frecuente, 0) }}%</span>
                            @else
                                <span style="color:#94A3B8;font-size:0.78rem;">0% (Estándar)</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
                            <div style="font-weight:700;font-size:0.86rem;color:#1E293B;font-family:monospace;">
                                Bs. {{ number_format($cl->total_consumido ?? 0, 2) }}
                            </div>
                            <div style="font-size:0.72rem;color:#64748B;">
                                {{ (int)$cl->ventas_completadas_count }} compras · {{ (int)$cl->cotizaciones_count }} cotiz.
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('clientes.show', $cl->id_cliente) }}" class="btn btn-sm btn-outline-tuerca" title="Ver historial de compras y cotizaciones">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                <a href="{{ route('cotizaciones.create', ['id_cliente' => $cl->id_cliente]) }}" class="btn btn-sm btn-outline-secondary" title="Crear cotización para este cliente">
                                    <i class="bi bi-file-earmark-text"></i>
                                </a>
                                <a href="{{ route('clientes.edit', $cl->id_cliente) }}" class="btn btn-sm btn-outline-tuerca" title="Editar ficha comercial">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-people fs-2 d-block mb-2 text-muted"></i>
                            No se encontraron clientes o talleres con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($clientes->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $clientes->links() }}
        </div>
    @endif
</div>
@endsection

