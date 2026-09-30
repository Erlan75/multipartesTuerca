@extends('layouts.app')
@section('title', 'Roles — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Roles</li>
@endsection

@section('content')
<div class="card-dark">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-shield-lock me-2"></i>Gestión de Roles</h5>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalRol" onclick="limpiarModal()">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Rol
        </button>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre del Rol</th>
                        <th>Descripción</th>
                        <th>Usuarios</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $rol)
                    <tr>
                        <td>{{ $rol->id_rol }}</td>
                        <td><strong style="color:var(--tuerca-gold);">{{ $rol->nombre_rol }}</strong></td>
                        <td>{{ $rol->descripcion ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $rol->usuarios_count }}</span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-tuerca" title="Editar"
                                        onclick="editarRol({{ $rol->id_rol }}, '{{ addslashes($rol->nombre_rol) }}', '{{ addslashes($rol->descripcion) }}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('roles.destroy', $rol->id_rol) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar el rol {{ $rol->nombre_rol }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-tuerca" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay roles registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Crear/Editar Rol --}}
<div class="modal fade" id="modalRol" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-content-dark">
            <form id="formRol" method="POST" action="{{ route('roles.store') }}">
                @csrf
                <div id="methodField"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRolTitle">Nuevo Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Nombre del Rol *</label>
                        <input type="text" name="nombre_rol" id="nombre_rol" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Descripción</label>
                        <textarea name="descripcion" id="descripcion" class="form-control form-control-dark" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-tuerca" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold" id="btnSubmitRol">Crear Rol</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function limpiarModal() {
    document.getElementById('formRol').action = '{{ route("roles.store") }}';
    document.getElementById('methodField').innerHTML = '';
    document.getElementById('modalRolTitle').textContent = 'Nuevo Rol';
    document.getElementById('btnSubmitRol').textContent = 'Crear Rol';
    document.getElementById('nombre_rol').value = '';
    document.getElementById('descripcion').value = '';
}

function editarRol(id, nombre, descripcion) {
    document.getElementById('formRol').action = '/roles/' + id;
    document.getElementById('methodField').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('modalRolTitle').textContent = 'Editar Rol';
    document.getElementById('btnSubmitRol').textContent = 'Guardar Cambios';
    document.getElementById('nombre_rol').value = nombre;
    document.getElementById('descripcion').value = descripcion;
    new bootstrap.Modal(document.getElementById('modalRol')).show();
}
</script>
@endpush
@endsection
