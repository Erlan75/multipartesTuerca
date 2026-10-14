@extends('layouts.app')
@section('title', 'Editar Proveedor — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('proveedores.index') }}">Proveedores</a></li>
    <li class="breadcrumb-item active">Editar Proveedor</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
                    <i class="bi bi-pencil-square me-2" style="color:#007AFF;"></i>Editar Proveedor: {{ $proveedor->razon_social }}
                </h4>
                <p style="color:#64748B;font-size:0.85rem;margin:0;">
                    Actualice los datos comerciales y de contacto del distribuidor mayorista.
                </p>
            </div>
            <a href="{{ route('proveedores.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger mb-4" style="border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i>Por favor verifique los siguientes datos:</div>
                <ul class="mb-0 ps-3" style="font-size:0.85rem;">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-light p-4">
            <form action="{{ route('proveedores.update', $proveedor->id_proveedor) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Razón Social / Nombre Comercial <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="razon_social" class="form-control form-control-dark @error('razon_social') is-invalid @enderror"
                               value="{{ old('razon_social', $proveedor->razon_social) }}" required>
                        @error('razon_social')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            NIT / Identificación Tributaria
                        </label>
                        <input type="text" name="nit" class="form-control form-control-dark @error('nit') is-invalid @enderror"
                               value="{{ old('nit', $proveedor->nit) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Nombre de Contacto / Asesor Comercial
                        </label>
                        <input type="text" name="contacto_nombre" class="form-control form-control-dark"
                               value="{{ old('contacto_nombre', $proveedor->contacto_nombre) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Condición Comercial <span class="text-danger">*</span>
                        </label>
                        <select name="condicion_comercial" class="form-select form-select-dark" required>
                            @php $cc = old('condicion_comercial', $proveedor->condicion_comercial); @endphp
                            <option value="Contado" {{ $cc == 'Contado' ? 'selected' : '' }}>Contado</option>
                            <option value="Crédito 15 días" {{ $cc == 'Crédito 15 días' ? 'selected' : '' }}>Crédito 15 días</option>
                            <option value="Crédito 30 días" {{ $cc == 'Crédito 30 días' ? 'selected' : '' }}>Crédito 30 días</option>
                            <option value="Crédito 60 días" {{ $cc == 'Crédito 60 días' ? 'selected' : '' }}>Crédito 60 días</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Teléfono / WhatsApp
                        </label>
                        <input type="text" name="telefono" class="form-control form-control-dark"
                               value="{{ old('telefono', $proveedor->telefono) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Correo Electrónico
                        </label>
                        <input type="email" name="correo" class="form-control form-control-dark"
                               value="{{ old('correo', $proveedor->correo) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Dirección de Almacén o Tienda
                        </label>
                        <input type="text" name="direccion" class="form-control form-control-dark"
                               value="{{ old('direccion', $proveedor->direccion) }}">
                    </div>

                    <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2" style="border-color:#F1F5F9 !important;">
                        <a href="{{ route('proveedores.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-tuerca" style="border-radius:10px;font-size:0.85rem;padding:0.5rem 1.4rem;">
                            <i class="bi bi-arrow-repeat me-1"></i>Actualizar Proveedor
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
