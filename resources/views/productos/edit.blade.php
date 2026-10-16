@extends('layouts.app')
@section('title', 'Editar Producto — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('productos.index') }}">Productos</a></li>
    <li class="breadcrumb-item active">{{ $producto->codigo_interno }}</li>
@endsection

@section('content')
{{-- Tabs de navegación --}}
<ul class="nav nav-tabs mb-3" style="border-bottom-color:rgba(233,69,96,0.15);">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tabDatos"
           style="color:var(--tuerca-gold);background:transparent;border-color:rgba(233,69,96,0.2);font-size:0.85rem;">
            <i class="bi bi-pencil-square me-1"></i>Datos del Producto
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabCodigos"
           style="color:var(--tuerca-muted);background:transparent;border-color:transparent;font-size:0.85rem;">
            <i class="bi bi-upc me-1"></i>Códigos Alternativos ({{ $producto->codigosAlternativos->count() }})
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabCompat"
           style="color:var(--tuerca-muted);background:transparent;border-color:transparent;font-size:0.85rem;">
            <i class="bi bi-truck me-1"></i>Compatibilidades ({{ $producto->compatibilidades->count() }})
        </a>
    </li>
</ul>

<div class="tab-content">
    {{-- TAB 1: Datos del Producto --}}
    <div class="tab-pane fade show active" id="tabDatos">
        <div class="card-dark">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-box-seam me-2"></i>{{ $producto->nombre_pieza }}</h6>
                <span class="badge bg-secondary">Stock Actual: {{ $producto->stock_actual }}</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('productos.update', $producto->id_producto) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label-dark">Código Interno *</label>
                            <input type="text" name="codigo_interno" class="form-control form-control-dark"
                                   value="{{ old('codigo_interno', $producto->codigo_interno) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Código OEM *</label>
                            <input type="text" name="codigo_oem" class="form-control form-control-dark"
                                   value="{{ old('codigo_oem', $producto->codigo_oem) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Nombre de la Pieza *</label>
                            <input type="text" name="nombre_pieza" class="form-control form-control-dark"
                                   value="{{ old('nombre_pieza', $producto->nombre_pieza) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Categoría *</label>
                            <select name="id_categoria" class="form-select form-select-dark" required>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat->id_categoria }}" {{ old('id_categoria', $producto->id_categoria) == $cat->id_categoria ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Marca Fabricante</label>
                            <input type="text" name="marca_fabricante" class="form-control form-control-dark"
                                   value="{{ old('marca_fabricante', $producto->marca_fabricante) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Procedencia</label>
                            <input type="text" name="procedencia" class="form-control form-control-dark"
                                   value="{{ old('procedencia', $producto->procedencia) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Precio Venta (Bs.) *</label>
                            <input type="number" step="0.01" name="precio_venta_sugerido" class="form-control form-control-dark"
                                   value="{{ old('precio_venta_sugerido', $producto->precio_venta_sugerido) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Margen (%) *</label>
                            <input type="number" step="0.01" name="margen_ganancia" class="form-control form-control-dark"
                                   value="{{ old('margen_ganancia', $producto->margen_ganancia) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Unidad Medida *</label>
                            <select name="unidad_medida" class="form-select form-select-dark" required>
                                @foreach(['Unidad', 'Juego', 'Par', 'Kit', 'Metro', 'Litro', 'Galón'] as $u)
                                    <option value="{{ $u }}" {{ old('unidad_medida', $producto->unidad_medida) == $u ? 'selected' : '' }}>{{ $u }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-dark">Stock Mínimo *</label>
                            <input type="number" name="stock_minimo" class="form-control form-control-dark"
                                   value="{{ old('stock_minimo', $producto->stock_minimo) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-dark">Stock Seguridad *</label>
                            <input type="number" name="stock_seguridad" class="form-control form-control-dark"
                                   value="{{ old('stock_seguridad', $producto->stock_seguridad) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-dark">Pasillo</label>
                            <input type="text" name="pasillo" class="form-control form-control-dark"
                                   value="{{ old('pasillo', $producto->pasillo) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-dark">Estante</label>
                            <input type="text" name="estante" class="form-control form-control-dark"
                                   value="{{ old('estante', $producto->estante) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-dark">Gaveta</label>
                            <input type="text" name="nivel_gaveta" class="form-control form-control-dark"
                                   value="{{ old('nivel_gaveta', $producto->nivel_gaveta) }}">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-gold"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
                        <a href="{{ route('productos.index') }}" class="btn btn-outline-tuerca">Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- TAB 2: Códigos Alternativos --}}
    <div class="tab-pane fade" id="tabCodigos">
        <div class="card-dark">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-upc me-2"></i>Códigos Alternativos y Equivalencias</h6>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalCodigo">
                    <i class="bi bi-plus-lg me-1"></i>Agregar Código
                </button>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-dark-custom mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código Referencia</th>
                                <th>Marca Alternativa</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($producto->codigosAlternativos as $cod)
                            <tr>
                                <td>{{ $cod->id_codigo_alt }}</td>
                                <td><code style="color:var(--tuerca-gold);">{{ $cod->codigo_referencia }}</code></td>
                                <td>{{ $cod->marca_alternativa ?? '—' }}</td>
                                <td class="text-center">
                                    <form action="{{ route('codigos-alternativos.destroy', $cod->id_codigo_alt) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar este código alternativo?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-tuerca btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Sin códigos alternativos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 3: Compatibilidades --}}
    <div class="tab-pane fade" id="tabCompat">
        <div class="card-dark">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-truck me-2"></i>Compatibilidad Técnica Vehicular</h6>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalCompat">
                    <i class="bi bi-plus-lg me-1"></i>Asignar Vehículo
                </button>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-dark-custom mb-0">
                        <thead>
                            <tr>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Años</th>
                                <th>Motor</th>
                                <th>Combustible</th>
                                <th>Notas / Posición</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($producto->compatibilidades as $comp)
                            <tr>
                                <td><strong>{{ $comp->vehiculo->marca }}</strong></td>
                                <td>{{ $comp->vehiculo->modelo }}</td>
                                <td>{{ $comp->vehiculo->anio_inicio }}–{{ $comp->vehiculo->anio_fin }}</td>
                                <td>{{ $comp->vehiculo->motor_cilindrada ?? '—' }}</td>
                                <td>{{ $comp->vehiculo->combustible }}</td>
                                <td style="font-size:0.8rem;">{{ $comp->notas_posicion ?? '—' }}</td>
                                <td class="text-center">
                                    <form action="{{ route('compatibilidades.destroy', $comp->id_compatibilidad) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar esta compatibilidad?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-tuerca btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">Sin compatibilidades vehiculares.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Agregar Código Alternativo --}}
<div class="modal fade" id="modalCodigo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-content-dark">
            <form method="POST" action="{{ route('codigos-alternativos.store') }}">
                @csrf
                <input type="hidden" name="id_producto" value="{{ $producto->id_producto }}">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Código Alternativo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Código de Referencia *</label>
                        <input type="text" name="codigo_referencia" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Marca Alternativa</label>
                        <input type="text" name="marca_alternativa" class="form-control form-control-dark">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-tuerca" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Asignar Compatibilidad Vehicular --}}
<div class="modal fade" id="modalCompat" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-content-dark">
            <form method="POST" action="{{ route('compatibilidades.store') }}">
                @csrf
                <input type="hidden" name="id_producto" value="{{ $producto->id_producto }}">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar Compatibilidad Vehicular</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Vehículo *</label>
                        <select name="id_vehiculo" class="form-select form-select-dark" required>
                            <option value="">Seleccionar vehículo...</option>
                            @foreach($vehiculos as $v)
                                <option value="{{ $v->id_vehiculo }}">
                                    {{ $v->marca }} {{ $v->modelo }} ({{ $v->anio_inicio }}-{{ $v->anio_fin }}) {{ $v->motor_cilindrada }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Notas de Posición / Observación</label>
                        <input type="text" name="notas_posicion" class="form-control form-control-dark"
                               placeholder="Ej: Eje delantero, lado derecho...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-tuerca" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
