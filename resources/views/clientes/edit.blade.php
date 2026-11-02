@extends('layouts.app')
@section('title', 'Editar Cliente — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes y Talleres</a></li>
    <li class="breadcrumb-item active">Editar Cliente</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-light">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-pencil-square me-2" style="color:#007AFF;"></i>Editar Ficha Comercial: {{ $cliente->nombre_razon_social }}
                </h5>
                <a href="{{ route('clientes.index') }}" class="btn btn-sm btn-outline-tuerca">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('clientes.update', $cliente->id_cliente) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label-dark">Nombre Completo o Razón Social <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_razon_social" class="form-control form-control-dark"
                                   value="{{ old('nombre_razon_social', $cliente->nombre_razon_social) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">CI / NIT</label>
                            <input type="text" name="ci_nit" class="form-control form-control-dark"
                                   value="{{ old('ci_nit', $cliente->ci_nit) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">Teléfono / WhatsApp</label>
                            <input type="text" name="telefono" class="form-control form-control-dark"
                                   value="{{ old('telefono', $cliente->telefono) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">Categoría / Segmento <span class="text-danger">*</span></label>
                            <select name="tipo_cliente" class="form-select form-select-dark" required>
                                <option value="Particular" {{ old('tipo_cliente', $cliente->tipo_cliente) === 'Particular' ? 'selected' : '' }}>Particular</option>
                                <option value="Taller/Mecánico" {{ old('tipo_cliente', $cliente->tipo_cliente) === 'Taller/Mecánico' ? 'selected' : '' }}>Taller / Mecánico Aliado</option>
                                <option value="Empresa/Flota" {{ old('tipo_cliente', $cliente->tipo_cliente) === 'Empresa/Flota' ? 'selected' : '' }}>Empresa / Flota</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-dark">Límite de Descuento Comercial (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="0" max="50" name="descuento_frecuente"
                                       class="form-control form-control-dark"
                                       value="{{ old('descuento_frecuente', $cliente->descuento_frecuente) }}" required>
                                <span class="input-group-text" style="border-radius:0 10px 10px 0;font-size:0.85rem;">%</span>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:#E2E8F0;">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;">Cancelar</a>
                        <button type="submit" class="btn btn-tuerca">
                            <i class="bi bi-check2-circle me-1"></i>Actualizar Ficha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
