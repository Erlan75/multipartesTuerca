@extends('layouts.app')
@section('title', 'Vehículos — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Catálogo Vehicular</li>
@endsection

@section('content')
<div class="card-dark">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-truck me-2"></i>Catálogo Vehicular</h5>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalVehiculo" onclick="limpiarModalV()">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Vehículo
        </button>
    </div>
    <div class="card-body p-3">
        {{-- Búsqueda --}}
        <form method="GET" class="mb-3">
            <div class="input-group" style="max-width:400px;">
                <input type="text" name="buscar" class="form-control form-control-dark"
                       placeholder="Buscar por marca, modelo o motor..."
                       value="{{ request('buscar') }}">
                <button class="btn btn-tuerca" type="submit"><i class="bi bi-search"></i></button>
                @if(request('buscar'))
                    <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-tuerca"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Año Inicio</th>
                        <th>Año Fin</th>
                        <th>Motor</th>
                        <th>Combustible</th>
                        <th>Compatib.</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehiculos as $v)
                    <tr>
                        <td>{{ $v->id_vehiculo }}</td>
                        <td><strong style="color:var(--tuerca-gold);">{{ $v->marca }}</strong></td>
                        <td>{{ $v->modelo }}</td>
                        <td>{{ $v->anio_inicio }}</td>
                        <td>{{ $v->anio_fin }}</td>
                        <td>{{ $v->motor_cilindrada ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $v->combustible }}</span></td>
                        <td><span class="badge bg-secondary">{{ $v->compatibilidades_count }}</span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-tuerca" title="Editar"
                                        onclick="editarVehiculo({{ $v->id_vehiculo }}, '{{ addslashes($v->marca) }}', '{{ addslashes($v->modelo) }}', {{ $v->anio_inicio }}, {{ $v->anio_fin }}, '{{ addslashes($v->motor_cilindrada) }}', '{{ $v->combustible }}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('vehiculos.destroy', $v->id_vehiculo) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar {{ $v->marca }} {{ $v->modelo }}? Se borrarán sus compatibilidades.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-tuerca" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron vehículos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $vehiculos->links() }}</div>
    </div>
</div>

{{-- Modal Crear/Editar Vehículo --}}
<div class="modal fade" id="modalVehiculo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-content-dark">
            <form id="formVehiculo" method="POST" action="{{ route('vehiculos.store') }}">
                @csrf
                <div id="methodFieldV"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVTitle">Nuevo Vehículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-dark">Marca *</label>
                            <input type="text" name="marca" id="v_marca" class="form-control form-control-dark" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Modelo *</label>
                            <input type="text" name="modelo" id="v_modelo" class="form-control form-control-dark" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Año Inicio *</label>
                            <input type="number" name="anio_inicio" id="v_anio_inicio" class="form-control form-control-dark" min="1950" max="2040" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Año Fin *</label>
                            <input type="number" name="anio_fin" id="v_anio_fin" class="form-control form-control-dark" min="1950" max="2040" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Motor / Cilindrada</label>
                            <input type="text" name="motor_cilindrada" id="v_motor" class="form-control form-control-dark" placeholder="Ej: 1.8L 2ZR-FE">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Combustible *</label>
                            <select name="combustible" id="v_combustible" class="form-select form-select-dark" required>
                                <option value="Gasolina">Gasolina</option>
                                <option value="Diésel">Diésel</option>
                                <option value="GNV">GNV</option>
                                <option value="Híbrido">Híbrido</option>
                                <option value="Eléctrico">Eléctrico</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-tuerca" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold" id="btnSubmitV">Crear Vehículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function limpiarModalV() {
    document.getElementById('formVehiculo').action = '{{ route("vehiculos.store") }}';
    document.getElementById('methodFieldV').innerHTML = '';
    document.getElementById('modalVTitle').textContent = 'Nuevo Vehículo';
    document.getElementById('btnSubmitV').textContent = 'Crear Vehículo';
    document.getElementById('v_marca').value = '';
    document.getElementById('v_modelo').value = '';
    document.getElementById('v_anio_inicio').value = '';
    document.getElementById('v_anio_fin').value = '';
    document.getElementById('v_motor').value = '';
    document.getElementById('v_combustible').value = 'Gasolina';
}

function editarVehiculo(id, marca, modelo, anioI, anioF, motor, combustible) {
    document.getElementById('formVehiculo').action = '/vehiculos/' + id;
    document.getElementById('methodFieldV').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('modalVTitle').textContent = 'Editar Vehículo';
    document.getElementById('btnSubmitV').textContent = 'Guardar Cambios';
    document.getElementById('v_marca').value = marca;
    document.getElementById('v_modelo').value = modelo;
    document.getElementById('v_anio_inicio').value = anioI;
    document.getElementById('v_anio_fin').value = anioF;
    document.getElementById('v_motor').value = motor;
    document.getElementById('v_combustible').value = combustible;
    new bootstrap.Modal(document.getElementById('modalVehiculo')).show();
}
</script>
@endpush
@endsection
