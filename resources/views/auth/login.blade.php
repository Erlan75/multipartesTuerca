<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Multipartes El Tuerca</title>
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
            overflow: hidden;
        }

        /* Subtle floating accents */
        body::before {
            content: '';
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0,122,255,0.06), transparent);
            top: -100px;
            right: -100px;
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        body::after {
            content: '';
            position: fixed;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(52,199,89,0.05), transparent);
            bottom: -80px;
            left: -80px;
            border-radius: 50%;
            animation: float 10s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid rgba(226,232,240,0.60);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(15,23,42,0.08), 0 4px 16px rgba(15,23,42,0.04);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #007AFF, #0055D4);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 24px rgba(0,122,255,0.25);
        }

        .login-icon i { font-size: 1.8rem; color: #fff; }

        .login-title {
            text-align: center;
            margin-bottom: 0.3rem;
            font-weight: 700;
            font-size: 1.4rem;
            color: #1E293B;
        }

        .login-subtitle {
            text-align: center;
            color: #64748B;
            font-size: 0.8rem;
            margin-bottom: 2rem;
            letter-spacing: 0.5px;
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
            padding: 0.85rem 0.85rem 0.85rem 2.8rem;
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

        .form-floating-light input::placeholder { color: #94A3B8; }

        .form-floating-light .input-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .form-floating-light input:focus ~ .input-icon { color: #007AFF; }

        .btn-login {
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

        .btn-login:hover {
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
            padding: 0.65rem 0.9rem;
            font-size: 0.82rem;
            margin-bottom: 1rem;
        }

        .alert-success-mac {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            border-radius: 14px;
            padding: 0.75rem 1rem;
            font-size: 0.84rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
        }

        .alert-bloqueado-mac {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            color: #B91C1C;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .btn-recuperar-link {
            color: #007AFF;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            transition: color 0.2s;
        }

        .btn-recuperar-link:hover {
            color: #0055D4;
            text-decoration: underline;
        }

        .forgot-link {
            color: #64748B;
            font-size: 0.82rem;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #007AFF;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-icon">
                <i class="bi bi-nut"></i>
            </div>
            <h1 class="login-title">Multipartes El Tuerca</h1>
            <p class="login-subtitle">Sistema de Gestión Comercial Automotriz</p>

            @if(session('success'))
                <div class="alert-success-mac">
                    <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->has('bloqueado'))
                <div class="alert-bloqueado-mac bg-red-50 border-red-200 text-red-700 rounded-xl p-4">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-shield-lock-fill text-danger fs-3 flex-shrink-0 mt-1"></i>
                        <div>
                            <div class="fw-bold mb-1" style="font-size:0.92rem; color:#991B1B;">
                                Acceso Temporalmente Bloqueado
                            </div>
                            <div style="font-size:0.83rem; line-height: 1.45; color: #7F1D1D;">
                                {{ $errors->first('bloqueado') }}
                            </div>
                            <div class="mt-2 pt-1 border-top" style="border-color: rgba(239, 68, 68, 0.2) !important;">
                                <a href="{{ route('password.request') }}" class="btn-recuperar-link">
                                    <i class="bi bi-key me-1"></i>Recuperar contraseña ahora &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($errors->any())
                <div class="error-msg">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf

                <div class="form-floating-light">
                    <input type="text" name="username" id="username"
                           placeholder="Nombre de usuario o correo"
                           value="{{ old('username') }}"
                           required autofocus>
                    <i class="bi bi-person input-icon"></i>
                </div>

                <div class="form-floating-light mb-2">
                    <input type="password" name="password" id="password"
                           placeholder="Contraseña"
                           required>
                    <i class="bi bi-lock input-icon"></i>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <a href="{{ route('password.request') }}" class="forgot-link">
                        <i class="bi bi-question-circle me-1"></i>¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button type="submit" class="btn btn-login mt-1">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>
