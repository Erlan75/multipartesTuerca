@extends('layouts.app')
@section('title', 'Bitácora de Auditoría — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Bitácora de Auditoría</li>
@endsection

@section('content')
<div class="card-dark">
    <div class="card-header">
        <h5><i class="bi bi-journal-text me-2"></i>Bitácora de Auditoría</h5>
    </div>
    <div class="card-body p-3">
        {{-- Filtros --}}
        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label-dark">Módulo</label>
                    <select name="modulo" class="form-select form-select-dark">
                        <option value="">Todos</option>
                        @foreach($modulos as $m)
                            <option value="{{ $m }}" {{ request('modulo') == $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-dark">Acción</label>
                    <select name="accion" class="form-select form-select-dark">
                        <option value="">Todas</option>
                        @foreach($acciones as $a)
                            <option value="{{ $a }}" {{ request('accion') == $a ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-dark">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-dark" value="{{ request('fecha_desde') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label-dark">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-dark" value="{{ request('fecha_hasta') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-tuerca" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                    <a href="{{ route('auditoria.index') }}" class="btn btn-outline-tuerca">
                        <i class="bi bi-x-lg"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Módulo</th>
                        <th>Acción</th>
                        <th>Reg. ID</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bitacoras as $b)
                    <tr>
                        <td>{{ $b->id_bitacora }}</td>
                        <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($b->fecha_hora)->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $b->usuario->nombre_completo ?? '—' }}</td>
                        <td><span class="badge bg-secondary" style="font-size:0.72rem;">{{ $b->modulo_afectado }}</span></td>
                        <td>
                            @php
                                $badgeColor = match($b->accion) {
                                    'INSERT' => 'success',
                                    'UPDATE' => 'warning',
                                    'DELETE' => 'danger',
                                    'ANULACION' => 'danger',
                                    'LOGIN' => 'info',
                                    'LOGOUT' => 'secondary',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badgeColor }}">{{ $b->accion }}</span>
                        </td>
                        <td>{{ $b->registro_id ?? '—' }}</td>
                        <td style="max-width:300px;font-size:0.78rem;word-break:break-word;">{{ Str::limit($b->detalle_cambio, 120) }}</td>
                        <td><code style="font-size:0.75rem;">{{ $b->ip_origen }}</code></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay registros de auditoría.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $bitacoras->links() }}</div>
    </div>
</div>
@endsection
