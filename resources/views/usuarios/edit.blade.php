@extends('layouts.app')
@section('title', 'Editar Usuario — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-dark">
            <div class="card-header">
                <h5><i class="bi bi-pencil-square me-2"></i>Editar Usuario: {{ $usuario->nombre_completo }}</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('usuarios.update', $usuario->id_usuario) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label-dark">Nombre Completo *</label>
                            <input type="text" name="nombre_completo" class="form-control form-control-dark"
                                   value="{{ old('nombre_completo', $usuario->nombre_completo) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Rol *</label>
                            <select name="id_rol" class="form-select form-select-dark" required>
                                @foreach($roles as $rol)
                                    <option value="{{ $rol->id_rol }}" {{ old('id_rol', $usuario->id_rol) == $rol->id_rol ? 'selected' : '' }}>
                                        {{ $rol->nombre_rol }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Username *</label>
                            <input type="text" name="username" class="form-control form-control-dark"
                                   value="{{ old('username', $usuario->username) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Correo Electrónico *</label>
                            <input type="email" name="correo" class="form-control form-control-dark"
                                   value="{{ old('correo', $usuario->correo) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Teléfono</label>
                            <input type="text" name="telefono" class="form-control form-control-dark"
                                   value="{{ old('telefono', $usuario->telefono) }}">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-gold">
                            <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                        </button>
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-tuerca">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
