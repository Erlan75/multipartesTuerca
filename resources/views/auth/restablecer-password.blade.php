<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña — Multipartes El Tuerca</title>
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
            overflow-x: hidden;
            padding: 1.5rem;
        }

        .recovery-container {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 1;
        }

        .recovery-card {
            background: #ffffff;
            border: 1px solid rgba(226,232,240,0.80);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(15,23,42,0.08), 0 4px 16px rgba(15,23,42,0.04);
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .recovery-icon {
            width: 68px;
            height: 68px;
            background: linear-gradient(135deg, #007AFF, #0055D4);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 24px rgba(0,122,255,0.25);
        }

        .recovery-icon i { font-size: 1.8rem; color: #fff; }

        .recovery-title {
            text-align: center;
            margin-bottom: 0.35rem;
            font-weight: 700;
            font-size: 1.35rem;
            color: #1E293B;
        }

        .recovery-subtitle {
            text-align: center;
            color: #64748B;
            font-size: 0.84rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .user-pill {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-floating-light {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .form-floating-light input {
            background: #ffffff;
            border: 1px solid #CBD5E1;
            color: #1E293B;
            border-radius: 12px;
            padding: 0.85rem 2.8rem 0.85rem 2.8rem;
            font-size: 0.9rem;
            width: 100%;
            transition: all 0.3s;
        }

        .form-floating-light input:focus {
            background: #ffffff;
            border-color: #007AFF;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.10);
            outline: none;
            color: #1E293B;
        }

        .form-floating-light .input-icon-left {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .form-floating-light .input-icon-right {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.1rem;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .form-floating-light input:focus ~ .input-icon-left { color: #007AFF; }

        .btn-primary-mac {
            background: linear-gradient(135deg, #007AFF, #0055D4);
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.85rem;
            border-radius: 12px;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 4px 14px rgba(0,122,255,0.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-primary-mac:hover {
            background: linear-gradient(135deg, #0055D4, #003DA5);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,122,255,0.35);
            color: #fff;
        }

        .btn-secondary-mac {
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            color: #475569;
            font-weight: 500;
            font-size: 0.88rem;
            padding: 0.75rem;
            border-radius: 12px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-secondary-mac:hover {
            background: #E2E8F0;
            color: #1E293B;
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
    </style>
</head>
<body>
    <div class="recovery-container">
        <div class="recovery-card">
            <div class="recovery-icon">
                <i class="bi bi-key"></i>
            </div>
            <h1 class="recovery-title">Establecer Nueva Contraseña</h1>
            <p class="recovery-subtitle">
                Defina su nueva contraseña de acceso para su cuenta en el sistema.
            </p>

            <div class="user-pill">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-primary" style="width: 36px; height: 36px; background: rgba(0,122,255,0.1);">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div>
                    <div class="fw-semibold text-dark" style="font-size: 0.85rem;">{{ $usuario->nombre_completo }}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        {{ $usuario->username }} &bull; {{ $usuario->correo }}
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="error-msg">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-floating-light">
                    <input type="password" name="password" id="password"
                           placeholder="Nueva contraseña (mínimo 8 caracteres)"
                           required autofocus>
                    <i class="bi bi-lock input-icon-left"></i>
                    <button type="button" class="input-icon-right" onclick="togglePassVisibility('password', 'eye1')">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>

                <div class="form-floating-light mb-4">
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           placeholder="Confirmar nueva contraseña"
                           required>
                    <i class="bi bi-shield-check input-icon-left"></i>
                    <button type="button" class="input-icon-right" onclick="togglePassVisibility('password_confirmation', 'eye2')">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>

                <button type="submit" class="btn-primary-mac mb-3">
                    <i class="bi bi-check-circle me-2"></i>Fijar Nueva Contraseña
                </button>
            </form>

            <div class="text-center">
                <a href="{{ route('login') }}" class="btn-secondary-mac">
                    <i class="bi bi-arrow-left me-2"></i>Cancelar y Volver al Login
                </a>
            </div>
        </div>
    </div>

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
</body>
</html>
