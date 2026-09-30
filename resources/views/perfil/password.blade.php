@extends('layouts.app')
@section('title', 'Cambiar Contraseña — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Cambiar Contraseña</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card-dark">
            <div class="card-header">
                <h5><i class="bi bi-key me-2"></i>Cambiar Contraseña</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4" style="font-size:0.85rem;">
                    Usuario: <strong style="color:var(--tuerca-gold);">{{ auth()->user()->username }}</strong>
                    ({{ auth()->user()->nombre_completo }})
                </p>
                <form method="POST" action="{{ route('perfil.password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label-dark">Contraseña Actual *</label>
                        <input type="password" name="password_actual" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Nueva Contraseña *</label>
                        <input type="password" name="password_nueva" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Confirmar Nueva Contraseña *</label>
                        <input type="password" name="password_nueva_confirmation" class="form-control form-control-dark" required>
                    </div>
                    <button type="submit" class="btn btn-gold">
                        <i class="bi bi-check-lg me-1"></i>Actualizar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
