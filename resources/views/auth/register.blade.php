<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta de Cliente — Multipartes El Tuerca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            background: #F5F7FB;
            background-image: radial-gradient(ellipse at 30% 20%, rgba(0,122,255,0.06) 0%, transparent 50%),
                              radial-gradient(ellipse at 70% 80%, rgba(52,199,89,0.04) 0%, transparent 50%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .register-container {
            width: 100%;
            max-width: 520px;
            position: relative;
            z-index: 1;
        }

        .register-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 2.25rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03),
                        0 20px 40px -8px rgba(0,0,0,0.06),
                        0 0 0 1px rgba(0,0,0,0.04);
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #007AFF, #0055D4);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            color: #fff;
            font-size: 1.6rem;
            box-shadow: 0 8px 20px rgba(0,122,255,0.25);
        }

        .register-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0F172A;
            text-align: center;
            margin-bottom: 0.25rem;
            letter-spacing: -0.02em;
        }

        .register-subtitle {
            font-size: 0.83rem;
            color: #64748B;
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .form-floating-light {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-floating-light input {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            color: #1E293B;
            font-size: 0.9rem;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            width: 100%;
            transition: all 0.2s ease;
        }

        .form-floating-light input:focus {
            background: #ffffff;
            border-color: #007AFF;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.12);
            outline: none;
            color: #1E293B;
        }

        .form-floating-light input::placeholder { color: #94A3B8; font-size: 0.85rem; }

        .form-floating-light .input-icon {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.05rem;
            transition: color 0.2s;
        }

        .form-floating-light input:focus ~ .input-icon { color: #007AFF; }

        .form-label-custom {
            font-size: 0.76rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.35rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .password-hint {
            font-size: 0.72rem;
            color: #64748B;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .btn-register {
            background: linear-gradient(135deg, #007AFF, #0055D4);
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.85rem;
            border-radius: 12px;
            width: 100%;
            transition: all 0.3s;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(0,122,255,0.25);
        }

        .btn-register:hover {
            background: linear-gradient(135deg, #0055D4, #003DA5);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,122,255,0.35);
            color: #fff;
        }

        .error-msg {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
        }

        .error-msg ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="brand-icon">
                <i class="bi bi-person-badge"></i>
            </div>
            <h1 class="register-title">Crear Cuenta de Cliente</h1>
            <p class="register-subtitle">Regístrate para solicitar cotizaciones técnicas y pedidos directos</p>

            @if($errors->any())
                <div class="error-msg">
                    <div class="d-flex align-items-center mb-1 fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span>Por favor corrige los siguientes errores:</span>
                    </div>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.submit') }}">
                @csrf

                <!-- Nombre Completo -->
                <div class="mb-3">
                    <label class="form-label-custom">Nombre Completo *</label>
                    <div class="form-floating-light">
                        <input type="text" name="nombre_completo" id="nombre_completo"
                               placeholder="Ej. Juan Carlos Morales"
                               value="{{ old('nombre_completo') }}"
                               required autofocus>
                        <i class="bi bi-person input-icon"></i>
                    </div>
                </div>

                <!-- Teléfono / WhatsApp -->
                <div class="mb-3">
                    <label class="form-label-custom">Teléfono / WhatsApp * (Para despacho y proformas)</label>
                    <div class="form-floating-light">
                        <input type="text" name="telefono" id="telefono"
                               placeholder="Ej. 70012345 o 3-3456789"
                               value="{{ old('telefono') }}"
                               required>
                        <i class="bi bi-whatsapp input-icon text-success"></i>
                    </div>
                </div>

                <!-- Correo Electrónico -->
                <div class="mb-3">
                    <label class="form-label-custom">Correo Electrónico *</label>
                    <div class="form-floating-light">
                        <input type="email" name="correo" id="correo"
                               placeholder="tu-correo@ejemplo.com"
                               value="{{ old('correo') }}"
                               required>
                        <i class="bi bi-envelope input-icon"></i>
                    </div>
                </div>

                <!-- Nombre de Usuario (Username) -->
                <div class="mb-3">
                    <label class="form-label-custom">Nombre de Usuario * (Para iniciar sesión)</label>
                    <div class="form-floating-light">
                        <input type="text" name="username" id="username"
                               placeholder="Ej. juan_morales"
                               value="{{ old('username') }}"
                               required>
                        <i class="bi bi-at input-icon"></i>
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="mb-1">
                    <label class="form-label-custom">Contraseña Segura *</label>
                    <div class="form-floating-light">
                        <input type="password" name="password" id="password"
                               placeholder="Mínimo 8 caracteres"
                               required>
                        <i class="bi bi-lock input-icon"></i>
                    </div>
                </div>
                <div class="password-hint">
                    <i class="bi bi-shield-check me-1 text-primary"></i>Debe incluir mayúscula, minúscula, número y un símbolo (@$!%*?&#_.-).
                </div>

                <!-- Confirmar Contraseña -->
                <div class="mb-4">
                    <label class="form-label-custom">Confirmar Contraseña *</label>
                    <div class="form-floating-light">
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               placeholder="Repite tu contraseña"
                               required>
                        <i class="bi bi-check2-circle input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-register">
                    <i class="bi bi-check-circle-fill me-2"></i>Registrarme y Acceder
                </button>
            </form>

            <div class="text-center mt-4 pt-3 border-top" style="border-color: rgba(226, 232, 240, 0.8) !important;">
                <span class="text-muted" style="font-size: 0.85rem;">¿Ya tienes cuenta registrada?</span>
                <a href="{{ route('login') }}" class="ms-1 fw-semibold text-decoration-none" style="color: #007AFF; font-size: 0.85rem;">
                    Inicia sesión aquí
                </a>
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('home') }}" class="text-secondary text-decoration-none" style="font-size: 0.82rem;">
                    <i class="bi bi-arrow-left me-1"></i>Volver al Catálogo Público
                </a>
            </div>
        </div>
    </div>
</body>
</html>
