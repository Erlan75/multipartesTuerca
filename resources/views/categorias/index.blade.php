@extends('layouts.app')
@section('title', 'Categorías — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Categorías</li>
@endsection

@section('content')
<div class="card-dark">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-tags me-2"></i>Categorías de Repuestos</h5>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalCategoria" onclick="limpiarModal()">
            <i class="bi bi-plus-lg me-1"></i>Nueva Categoría
        </button>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Productos</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categorias as $cat)
                    <tr>
                        <td>{{ $cat->id_categoria }}</td>
                        <td><strong style="color:var(--tuerca-gold);">{{ $cat->nombre }}</strong></td>
                        <td style="max-width:400px;">{{ $cat->descripcion ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $cat->productos_count }}</span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-tuerca" title="Editar"
                                        onclick="editarCategoria({{ $cat->id_categoria }}, '{{ addslashes($cat->nombre) }}', '{{ addslashes($cat->descripcion) }}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('categorias.destroy', $cat->id_categoria) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar la categoría {{ $cat->nombre }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-tuerca" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay categorías registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Crear/Editar Categoría --}}
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-content-dark">
            <form id="formCategoria" method="POST" action="{{ route('categorias.store') }}">
                @csrf
                <div id="methodFieldCat"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCatTitle">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Nombre *</label>
                        <input type="text" name="nombre" id="cat_nombre" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Descripción</label>
                        <textarea name="descripcion" id="cat_descripcion" class="form-control form-control-dark" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-tuerca" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold" id="btnSubmitCat">Crear Categoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function limpiarModal() {
    document.getElementById('formCategoria').action = '{{ route("categorias.store") }}';
    document.getElementById('methodFieldCat').innerHTML = '';
    document.getElementById('modalCatTitle').textContent = 'Nueva Categoría';
    document.getElementById('btnSubmitCat').textContent = 'Crear Categoría';
    document.getElementById('cat_nombre').value = '';
    document.getElementById('cat_descripcion').value = '';
}

function editarCategoria(id, nombre, descripcion) {
    document.getElementById('formCategoria').action = '/categorias/' + id;
    document.getElementById('methodFieldCat').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('modalCatTitle').textContent = 'Editar Categoría';
    document.getElementById('btnSubmitCat').textContent = 'Guardar Cambios';
    document.getElementById('cat_nombre').value = nombre;
    document.getElementById('cat_descripcion').value = descripcion;
    new bootstrap.Modal(document.getElementById('modalCategoria')).show();
}
</script>
@endpush
@endsection
