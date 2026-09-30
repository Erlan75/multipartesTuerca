@extends('layouts.app')
@section('title', 'Usuarios — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')
<div class="card-dark">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-people me-2"></i>Gestión de Usuarios</h5>
        <a href="{{ route('usuarios.create') }}" class="btn btn-gold btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Usuario
        </a>
    </div>
    <div class="card-body p-3">
        {{-- Búsqueda --}}
        <form method="GET" class="mb-3">
            <div class="input-group" style="max-width:400px;">
                <input type="text" name="buscar" class="form-control form-control-dark"
                       placeholder="Buscar por nombre, usuario o correo..."
                       value="{{ request('buscar') }}">
                <button class="btn btn-tuerca" type="submit"><i class="bi bi-search"></i></button>
                @if(request('buscar'))
                    <a href="{{ route('usuarios.index') }}" class="btn btn-outline-tuerca">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre Completo</th>
                        <th>Username</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($usuarios as $u)
                    <tr>
                        <td>{{ $u->id_usuario }}</td>
                        <td><strong>{{ $u->nombre_completo }}</strong></td>
                        <td><code style="color:var(--tuerca-gold);">{{ $u->username }}</code></td>
                        <td>{{ $u->correo }}</td>
                        <td>{{ $u->telefono ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $u->rol->nombre_rol }}</span></td>
                        <td>
                            @if($u->estado)
                                <span class="badge-activo">Activo</span>
                            @else
                                <span class="badge-inactivo">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('usuarios.edit', $u->id_usuario) }}" class="btn btn-outline-tuerca" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if($u->id_usuario !== auth()->id())
                                <form action="{{ route('usuarios.toggle', $u->id_usuario) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-outline-tuerca" title="{{ $u->estado ? 'Desactivar' : 'Activar' }}">
                                        <i class="bi bi-{{ $u->estado ? 'toggle-on' : 'toggle-off' }}"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $usuarios->links() }}</div>
    </div>
</div>
@endsection
