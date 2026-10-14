@extends('layouts.app')
@section('title', 'Nuevo Proveedor — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('proveedores.index') }}">Proveedores</a></li>
    <li class="breadcrumb-item active">Nuevo Proveedor</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
                    <i class="bi bi-building-add me-2" style="color:#007AFF;"></i>Registrar Proveedor / Distribuidor (CU19)
                </h4>
                <p style="color:#64748B;font-size:0.85rem;margin:0;">
                    Complete la información comercial para habilitar recepciones de compra y créditos.
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
            <form action="{{ route('proveedores.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Razón Social / Nombre Comercial <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="razon_social" class="form-control form-control-dark @error('razon_social') is-invalid @enderror"
                               value="{{ old('razon_social') }}" placeholder="Ej: Importadora Japonesa S.R.L." required>
                        @error('razon_social')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            NIT / Identificación Tributaria
                        </label>
                        <input type="text" name="nit" class="form-control form-control-dark @error('nit') is-invalid @enderror"
                               value="{{ old('nit') }}" placeholder="Ej: 1028491028">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Nombre de Contacto / Asesor Comercial
                        </label>
                        <input type="text" name="contacto_nombre" class="form-control form-control-dark"
                               value="{{ old('contacto_nombre') }}" placeholder="Ej: Carlos Mendoza">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Condición Comercial <span class="text-danger">*</span>
                        </label>
                        <select name="condicion_comercial" class="form-select form-select-dark" required>
                            <option value="Contado" {{ old('condicion_comercial') == 'Contado' ? 'selected' : '' }}>Contado</option>
                            <option value="Crédito 15 días" {{ old('condicion_comercial') == 'Crédito 15 días' ? 'selected' : '' }}>Crédito 15 días</option>
                            <option value="Crédito 30 días" {{ old('condicion_comercial', 'Crédito 30 días') == 'Crédito 30 días' ? 'selected' : '' }}>Crédito 30 días</option>
                            <option value="Crédito 60 días" {{ old('condicion_comercial') == 'Crédito 60 días' ? 'selected' : '' }}>Crédito 60 días</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Teléfono / WhatsApp
                        </label>
                        <input type="text" name="telefono" class="form-control form-control-dark"
                               value="{{ old('telefono') }}" placeholder="Ej: +591 70012345">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Correo Electrónico
                        </label>
                        <input type="email" name="correo" class="form-control form-control-dark"
                               value="{{ old('correo') }}" placeholder="ventas@importadora.com">
                    </div>

                    <div class="col-12">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Dirección de Almacén o Tienda
                        </label>
                        <input type="text" name="direccion" class="form-control form-control-dark"
                               value="{{ old('direccion') }}" placeholder="Ej: Av. Brasil #1240, 2do Anillo, Santa Cruz">
                    </div>

                    <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2" style="border-color:#F1F5F9 !important;">
                        <a href="{{ route('proveedores.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-tuerca" style="border-radius:10px;font-size:0.85rem;padding:0.5rem 1.4rem;">
                            <i class="bi bi-save me-1"></i>Guardar Proveedor
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
