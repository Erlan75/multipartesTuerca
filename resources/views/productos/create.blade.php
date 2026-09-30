@extends('layouts.app')
@section('title', 'Nuevo Producto — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('productos.index') }}">Productos</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
<div class="card-dark">
    <div class="card-header">
        <h5><i class="bi bi-plus-square me-2"></i>Nuevo Producto / Repuesto</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('productos.store') }}">
            @csrf
            <div class="row g-3">
                {{-- Identificación --}}
                <div class="col-12"><h6 style="color:var(--tuerca-gold);border-bottom:1px solid rgba(240,165,0,0.2);padding-bottom:0.4rem;">Identificación</h6></div>
                <div class="col-md-3">
                    <label class="form-label-dark">Código Interno *</label>
                    <input type="text" name="codigo_interno" class="form-control form-control-dark" value="{{ old('codigo_interno') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label-dark">Código OEM *</label>
                    <input type="text" name="codigo_oem" class="form-control form-control-dark" value="{{ old('codigo_oem') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label-dark">Nombre de la Pieza *</label>
                    <input type="text" name="nombre_pieza" class="form-control form-control-dark" value="{{ old('nombre_pieza') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-dark">Categoría *</label>
                    <select name="id_categoria" class="form-select form-select-dark" required>
                        <option value="">Seleccionar...</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id_categoria }}" {{ old('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-dark">Marca Fabricante</label>
                    <input type="text" name="marca_fabricante" class="form-control form-control-dark" value="{{ old('marca_fabricante') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label-dark">Procedencia</label>
                    <input type="text" name="procedencia" class="form-control form-control-dark" value="{{ old('procedencia') }}" placeholder="Ej: Japón, Brasil...">
                </div>

                {{-- Precios --}}
                <div class="col-12 mt-3"><h6 style="color:var(--tuerca-gold);border-bottom:1px solid rgba(240,165,0,0.2);padding-bottom:0.4rem;">Precios y Medida</h6></div>
                <div class="col-md-3">
                    <label class="form-label-dark">Precio Venta Sugerido (Bs.) *</label>
                    <input type="number" step="0.01" name="precio_venta_sugerido" class="form-control form-control-dark" value="{{ old('precio_venta_sugerido') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label-dark">Margen Ganancia (%) *</label>
                    <input type="number" step="0.01" name="margen_ganancia" class="form-control form-control-dark" value="{{ old('margen_ganancia', '0.00') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label-dark">Unidad de Medida *</label>
                    <select name="unidad_medida" class="form-select form-select-dark" required>
                        @foreach(['Unidad', 'Juego', 'Par', 'Kit', 'Metro', 'Litro', 'Galón'] as $u)
                            <option value="{{ $u }}" {{ old('unidad_medida', 'Unidad') == $u ? 'selected' : '' }}>{{ $u }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Stock --}}
                <div class="col-12 mt-3"><h6 style="color:var(--tuerca-gold);border-bottom:1px solid rgba(240,165,0,0.2);padding-bottom:0.4rem;">Control de Stock</h6></div>
                <div class="col-md-3">
                    <label class="form-label-dark">Stock Mínimo *</label>
                    <input type="number" name="stock_minimo" class="form-control form-control-dark" value="{{ old('stock_minimo', 3) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label-dark">Stock de Seguridad *</label>
                    <input type="number" name="stock_seguridad" class="form-control form-control-dark" value="{{ old('stock_seguridad', 5) }}" required>
                </div>

                {{-- Ubicación --}}
                <div class="col-12 mt-3"><h6 style="color:var(--tuerca-gold);border-bottom:1px solid rgba(240,165,0,0.2);padding-bottom:0.4rem;">Ubicación en Almacén</h6></div>
                <div class="col-md-3">
                    <label class="form-label-dark">Pasillo</label>
                    <input type="text" name="pasillo" class="form-control form-control-dark" value="{{ old('pasillo') }}" placeholder="Ej: P1">
                </div>
                <div class="col-md-3">
                    <label class="form-label-dark">Estante</label>
                    <input type="text" name="estante" class="form-control form-control-dark" value="{{ old('estante') }}" placeholder="Ej: E-01">
                </div>
                <div class="col-md-3">
                    <label class="form-label-dark">Nivel / Gaveta</label>
                    <input type="text" name="nivel_gaveta" class="form-control form-control-dark" value="{{ old('nivel_gaveta') }}" placeholder="Ej: G-01">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-gold"><i class="bi bi-check-lg me-1"></i>Crear Producto</button>
                <a href="{{ route('productos.index') }}" class="btn btn-outline-tuerca">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
