@extends('layouts.app')
@section('title', 'Cambiar Contraseña — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Cambiar Contraseña</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card-light">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i>Cambiar Contraseña</h5>
                <span class="badge" style="background: rgba(0,122,255,0.08); color: #007AFF; font-size: 0.75rem;"><i class="bi bi-shield-check me-1"></i>Seguridad</span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(0,122,255,0.1); color: #007AFF;">
                        <i class="bi bi-person-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-semibold text-dark" style="font-size: 0.95rem;">{{ auth()->user()->nombre_completo }}</div>
                        <div class="text-muted" style="font-size: 0.8rem;">
                            Usuario: <span class="fw-medium text-primary">{{ auth()->user()->username }}</span> &bull; {{ auth()->user()->correo }}
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('perfil.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label-dark">Contraseña Actual *</label>
                        <div class="input-group">
                            <input type="password" name="password_actual" id="password_actual" class="form-control form-control-dark @error('password_actual') is-invalid @enderror" placeholder="Ingrese su contraseña actual" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('password_actual', 'eye_actual')" style="border-color: #CBD5E1;">
                                <i class="bi bi-eye" id="eye_actual"></i>
                            </button>
                        </div>
                        @error('password_actual')
                            <div class="text-danger mt-1" style="font-size: 0.8rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label-dark">Nueva Contraseña *</label>
                        <div class="input-group">
                            <input type="password" name="password_nueva" id="password_nueva" class="form-control form-control-dark @error('password_nueva') is-invalid @enderror" placeholder="Mínimo 8 caracteres" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('password_nueva', 'eye_nueva')" style="border-color: #CBD5E1;">
                                <i class="bi bi-eye" id="eye_nueva"></i>
                            </button>
                        </div>
                        <div class="form-text" style="font-size: 0.75rem; color: #64748B;">
                            <i class="bi bi-info-circle me-1"></i>Debe tener al menos 8 caracteres para mayor seguridad.
                        </div>
                        @error('password_nueva')
                            <div class="text-danger mt-1" style="font-size: 0.8rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label-dark">Confirmar Nueva Contraseña *</label>
                        <div class="input-group">
                            <input type="password" name="password_nueva_confirmation" id="password_nueva_confirmation" class="form-control form-control-dark" placeholder="Repita la nueva contraseña" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('password_nueva_confirmation', 'eye_conf')" style="border-color: #CBD5E1;">
                                <i class="bi bi-eye" id="eye_conf"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-tuerca">
                            <i class="bi bi-arrow-left me-1"></i>Volver al Dashboard
                        </a>
                        <button type="submit" class="btn btn-tuerca">
                            <i class="bi bi-check-lg me-1"></i>Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function togglePassVisibility(inputId, eyeId) {
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        if (input.type === 'password') {
            input.type = 'text';
            eye.classList.remove('bi-eye');
            eye.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            eye.classList.remove('bi-eye-slash');
            eye.classList.add('bi-eye');
        }
    }
</script>
@endpush
@endsection
