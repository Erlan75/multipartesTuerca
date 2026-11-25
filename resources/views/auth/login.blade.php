<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Multipartes El Tuerca</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --cz-dark: #0F172A;
            --cz-dark-elev: #1E293B;
            --cz-emerald: #10B981;
            --cz-emerald-dark: #059669;
            --cz-emerald-deep: #047857;
            --cz-amber: #F59E0B;
            --cz-canvas: #F8FAFC;
            --cz-ink: #0F172A;
            --cz-body: #475569;
            --cz-muted: #64748B;
            --cz-border: #CBD5E1;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--cz-canvas);
            color: var(--cz-body);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        .cz-auth-split {
            display: grid;
            grid-template-columns: 1fr;
            min-height: 100vh;
        }
        @media (min-width: 992px) {
            .cz-auth-split {
                grid-template-columns: 1fr 1fr;
            }
        }
        /* Mitad Izquierda: Identidad Oscura Premium */
        .cz-auth-brand-pane {
            background-color: var(--cz-dark);
            background-image:
                radial-gradient(70% 60% at 15% 15%, rgba(16, 185, 129, 0.16) 0%, transparent 65%),
                radial-gradient(50% 50% at 90% 85%, rgba(245, 158, 11, 0.10) 0%, transparent 60%),
                linear-gradient(rgba(148, 163, 184, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.04) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 48px 48px, 48px 48px;
            color: #FFFFFF;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 32px;
        }
        @media (min-width: 992px) {
            .cz-auth-brand-pane {
                padding: 48px 56px;
            }
        }
        .cz-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #CBD5E1;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.04);
            width: fit-content;
            transition: all 0.2s ease;
        }
        .cz-back-link:hover {
            color: #FFFFFF;
            border-color: var(--cz-emerald);
            background: rgba(16, 185, 129, 0.12);
        }
        .cz-brand-emblem {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: #022C22;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
        }
        .cz-brand-feature {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(30, 41, 59, 0.65);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }
        .cz-brand-feature i {
            color: var(--cz-emerald);
            font-size: 1.15rem;
            margin-top: 2px;
        }
        /* Mitad Derecha: Formulario Minimalista */
        .cz-auth-form-pane {
            background-color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
        }
        @media (min-width: 992px) {
            .cz-auth-form-pane {
                padding: 48px 56px;
            }
        }
        .cz-auth-form-box {
            width: 100%;
            max-width: 420px;
        }
        .cz-field-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--cz-ink);
            margin-bottom: 8px;
        }
        .cz-input-wrap {
            position: relative;
        }
        .cz-input {
            width: 100%;
            height: 48px;
            padding: 0 46px 0 44px;
            border-radius: 12px;
            border: 1px solid var(--cz-border);
            background-color: var(--cz-canvas);
            color: var(--cz-ink);
            font-size: 0.9375rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .cz-input:focus {
            background-color: #FFFFFF;
            border-color: var(--cz-emerald-dark);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
            outline: none;
        }
        .cz-input.is-invalid {
            border-color: #DC2626;
            background-color: #FEF2F2;
        }
        .cz-input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--cz-muted);
            font-size: 1.1rem;
            pointer-events: none;
        }
        .cz-toggle-pass {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            color: var(--cz-muted);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .cz-toggle-pass:hover {
            color: var(--cz-ink);
            background-color: #E2E8F0;
        }
        .cz-btn-submit {
            width: 100%;
            min-height: 48px;
            border-radius: 12px;
            border: none;
            background-color: var(--cz-emerald-dark);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.28);
            transition: all 0.2s ease;
        }
        .cz-btn-submit:hover {
            background-color: var(--cz-emerald-deep);
            transform: translateY(-1px);
        }
        .cz-btn-submit:active {
            transform: translateY(0);
        }
        .cz-alert {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .cz-alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .cz-alert-warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .cz-alert-error   { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
    </style>
</head>
<body>
    <div class="cz-auth-split">
        <!-- Mitad Izquierda: Panel Oscuro Multipartes El Tuerca -->
        <aside class="cz-auth-brand-pane">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <a href="{{ route('home') }}" class="cz-back-link">
                    <i class="bi bi-arrow-left"></i>
                    <span>Volver a la tienda</span>
                </a>
                <span class="d-none d-sm-inline-flex align-items-center gap-2" style="font-family: 'JetBrains Mono', monospace; font-size: 0.72rem; color: #94A3B8;">
                    <i class="bi bi-geo-alt-fill text-success"></i>2do Anillo · Santa Cruz
                </span>
            </div>

            <div class="my-auto py-3">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="cz-brand-emblem">
                        <i class="bi bi-gear-wide-connected"></i>
                    </span>
                    <div>
                        <div class="fw-bold text-white" style="font-size: 1.15rem; letter-spacing: -0.02em;">MULTIPARTES EL TUERCA</div>
                        <div style="font-size: 0.72rem; color: #94A3B8; letter-spacing: 0.08em; text-transform: uppercase;">Repuestos Japoneses y Coreanos</div>
                    </div>
                </div>

                <h1 class="fw-bold text-white mb-3" style="font-size: clamp(1.6rem, 2.8vw, 2.35rem); letter-spacing: -0.03em; line-height: 1.15;">
                    Acceso unificado para clientes web y equipo interno
                </h1>
                <p class="mb-4" style="color: #CBD5E1; font-size: 0.94rem; line-height: 1.6; max-width: 460px;">
                    Gestiona tus compras directas, descarga comprobantes y proformas en PDF o administra el mostrador e inventario en tiempo real.
                </p>

                <div class="d-none d-lg-flex flex-column gap-3" style="max-width: 460px;">
                    <div class="cz-brand-feature">
                        <i class="bi bi-patch-check-fill"></i>
                        <div>
                            <div class="fw-bold text-white" style="font-size: 0.88rem;">Clientes y Talleres Web</div>
                            <div style="font-size: 0.8rem; color: #94A3B8;">Compra online, historial de proformas y descarga de comprobantes en PDF.</div>
                        </div>
                    </div>
                    <div class="cz-brand-feature">
                        <i class="bi bi-speedometer2" style="color: var(--cz-amber);"></i>
                        <div>
                            <div class="fw-bold text-white" style="font-size: 0.88rem;">Personal de Tienda y Almacén</div>
                            <div style="font-size: 0.8rem; color: #94A3B8;">POS con ticket 80mm, caja, inventario OEM, reportes analíticos y Asistente IA.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-none d-lg-flex align-items-center justify-content-between pt-3 border-top" style="border-color: rgba(148, 163, 184, 0.16) !important; font-size: 0.78rem; color: #94A3B8;">
                <span>Av. Cristóbal de Mendoza #450</span>
                <span>WhatsApp: 700-12345</span>
            </div>
        </aside>

        <!-- Mitad Derecha: Formulario Minimalista -->
        <main class="cz-auth-form-pane">
            <div class="cz-auth-form-box">
                <div class="mb-4">
                    <span class="d-inline-block fw-bold text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.08em; color: var(--cz-emerald-deep);">
                        Acceso Seguro
                    </span>
                    <h2 class="fw-bold mb-1" style="font-size: 1.75rem; color: var(--cz-ink); letter-spacing: -0.025em;">
                        Iniciar Sesión
                    </h2>
                    <p class="m-0" style="font-size: 0.9rem; color: var(--cz-muted);">
                        Ingresa tu usuario o correo registrado para continuar.
                    </p>
                </div>

                @if(session('success'))
                    <div class="cz-alert cz-alert-success" role="status">
                        <i class="bi bi-check-circle-fill fs-5 text-success flex-shrink-0"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="cz-alert cz-alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                        <span>{{ session('warning') }}</span>
                    </div>
                @endif

                @if($errors->has('bloqueado'))
                    <div class="cz-alert cz-alert-error" role="alert">
                        <i class="bi bi-shield-lock-fill fs-4 text-danger flex-shrink-0"></i>
                        <div>
                            <div class="fw-bold mb-1">Acceso Temporalmente Bloqueado</div>
                            <div class="mb-2">{{ $errors->first('bloqueado') }}</div>
                            <a href="{{ route('password.request') }}" class="fw-bold text-decoration-none" style="color: #991B1B;">
                                <i class="bi bi-key me-1"></i>Recuperar contraseña ahora &rarr;
                            </a>
                        </div>
                    </div>
                @elseif($errors->any())
                    <div class="cz-alert cz-alert-error" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 text-danger flex-shrink-0"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="username" class="cz-field-label">Usuario o Correo Electrónico</label>
                        <div class="cz-input-wrap">
                            <i class="bi bi-person cz-input-icon"></i>
                            <input type="text"
                                   name="username"
                                   id="username"
                                   class="cz-input @error('username') is-invalid @enderror"
                                   placeholder="Ej. juan_morales o correo@ejemplo.com"
                                   value="{{ old('username') }}"
                                   autocomplete="username"
                                   required
                                   autofocus>
                        </div>
                        @error('username')
                            <div class="text-danger small mt-1 fw-medium">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="password" class="cz-field-label m-0">Contraseña</label>
                            <a href="{{ route('password.request') }}" class="text-decoration-none fw-semibold" style="font-size: 0.8rem; color: var(--cz-emerald-deep);">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                        <div class="cz-input-wrap">
                            <i class="bi bi-lock cz-input-icon"></i>
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="cz-input @error('password') is-invalid @enderror"
                                   placeholder="Ingresa tu contraseña"
                                   autocomplete="current-password"
                                   required>
                            <button type="button"
                                    class="cz-toggle-pass"
                                    onclick="togglePasswordField('password', this)"
                                    aria-label="Mostrar u ocultar contraseña">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1 fw-medium">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="cz-btn-submit mt-4">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Iniciar Sesión</span>
                    </button>
                </form>

                <div class="text-center mt-4 pt-4 border-top" style="border-color: #E2E8F0 !important;">
                    <span style="font-size: 0.88rem; color: var(--cz-muted);">¿Aún no tienes cuenta de cliente?</span>
                    <a href="{{ route('register') }}" class="ms-1 fw-bold text-decoration-none" style="color: var(--cz-emerald-deep); font-size: 0.88rem;">
                        Crear cuenta de cliente
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        function togglePasswordField(id, btn) {
            const input = document.getElementById(id);
            if (!input) return;
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        }

        // Manejador de historial: al presionar 'Atrás' desde login, salta al catálogo público raíz
        if (window.history && window.history.pushState) {
            window.history.pushState(null, "", window.location.href);
            window.onpopstate = function() {
                window.location.replace("{{ route('home') }}");
            };
        }
    </script>
</body>
</html>
