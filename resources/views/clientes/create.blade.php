@extends('layouts.app')
@section('title', 'Nuevo Cliente o Taller — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes y Talleres</a></li>
    <li class="breadcrumb-item active">Nuevo Registro</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-light">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-person-plus-fill me-2" style="color:#007AFF;"></i>Registrar Cliente o Taller Aliado
                </h5>
                <a href="{{ route('clientes.index') }}" class="btn btn-sm btn-outline-tuerca">
                    <i class="bi bi-arrow-left me-1"></i>Volver al Listado
                </a>
            </div>
            <div class="card-body p-4">
                @if(isset($usuarioWeb) && $usuarioWeb)
                    <div class="alert alert-info d-flex align-items-center gap-2 mb-4" style="border-radius:12px;font-size:0.85rem;">
                        <i class="bi bi-globe2 fs-5"></i>
                        <div>
                            Vinculando datos desde cuenta web registrada: <strong>{{ $usuarioWeb->nombre_completo }}</strong> ({{ $usuarioWeb->correo }}).
                        </div>
                    </div>
                @endif

                <form action="{{ route('clientes.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label-dark">Nombre Completo o Razón Social <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_razon_social" class="form-control form-control-dark"
                                   value="{{ old('nombre_razon_social', $usuarioWeb->nombre_completo ?? '') }}"
                                   placeholder="Ej: Taller Mecánico Especializado El Pistón / Juan Pérez" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">CI / NIT</label>
                            <input type="text" name="ci_nit" class="form-control form-control-dark"
                                   value="{{ old('ci_nit') }}"
                                   placeholder="Ej: 4587123018 o S/N">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">Teléfono / WhatsApp</label>
                            <input type="text" name="telefono" class="form-control form-control-dark"
                                   value="{{ old('telefono', $usuarioWeb->telefono ?? '') }}"
                                   placeholder="Ej: 77012345">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">Categoría / Segmento <span class="text-danger">*</span></label>
                            <select name="tipo_cliente" id="tipoClienteSelect" class="form-select form-select-dark" required onchange="ajustarDescuentoSugerido(this.value)">
                                <option value="Particular" {{ old('tipo_cliente') === 'Particular' ? 'selected' : '' }}>Particular</option>
                                <option value="Taller/Mecánico" {{ old('tipo_cliente') === 'Taller/Mecánico' ? 'selected' : '' }}>Taller / Mecánico Aliado</option>
                                <option value="Empresa/Flota" {{ old('tipo_cliente') === 'Empresa/Flota' ? 'selected' : '' }}>Empresa / Flota</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">Límite de Descuento Comercial (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="0" max="50" name="descuento_frecuente" id="descuentoInput"
                                       class="form-control form-control-dark"
                                       value="{{ old('descuento_frecuente', '0.00') }}" required>
                                <span class="input-group-text" style="border-radius:0 10px 10px 0;font-size:0.85rem;">%</span>
                            </div>
                            <small class="text-muted" style="font-size:0.72rem;">Tope máximo autorizado (0% a 50%).</small>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:#E2E8F0;">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;">Cancelar</a>
                        <button type="submit" class="btn btn-tuerca">
                            <i class="bi bi-check2-circle me-1"></i>Guardar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function ajustarDescuentoSugerido(tipo) {
        const input = document.getElementById('descuentoInput');
        if (tipo === 'Taller/Mecánico' && parseFloat(input.value) === 0) {
            input.value = '10.00';
        } else if (tipo === 'Empresa/Flota' && parseFloat(input.value) === 0) {
            input.value = '12.00';
        } else if (tipo === 'Particular') {
            input.value = '0.00';
        }
    }
</script>
@endpush

