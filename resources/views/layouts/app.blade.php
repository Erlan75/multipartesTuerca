<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Multipartes El Tuerca')</title>
    <meta name="description" content="Sistema de gestión comercial automotriz - Multipartes El Tuerca, Santa Cruz">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --mac-bg: #F5F7FB;
            --mac-surface: #ffffff;
            --mac-border: #E2E8F0;
            --mac-border-soft: rgba(226,232,240,0.80);
            --mac-primary: #007AFF;
            --mac-primary-hover: #0055D4;
            --mac-success: #34C759;
            --mac-warning: #FF9500;
            --mac-danger: #FF3B30;
            --mac-title: #1E293B;
            --mac-text: #334155;
            --mac-muted: #64748B;
            --mac-subtle: #94A3B8;
            --sidebar-width: 260px;
        }

        * { font-family: 'Inter', sans-serif; }

        body {
            background: var(--mac-bg);
            color: var(--mac-text);
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--mac-surface);
            border-right: 1px solid var(--mac-border);
            z-index: 1050;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-brand {
            padding: 1.25rem 1.25rem;
            border-bottom: 1px solid var(--mac-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-brand-text {
            text-align: left;
        }

        .sidebar-brand h5 {
            color: var(--mac-primary);
            font-weight: 700;
            margin: 0;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
        }

        .sidebar-brand small {
            color: var(--mac-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar .nav-section {
            padding: 0.85rem 1.25rem 0.35rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--mac-subtle);
            font-weight: 600;
        }

        .sidebar .nav-link {
            color: var(--mac-text);
            padding: 0.55rem 1.25rem;
            margin: 0.1rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 400;
            border-radius: 10px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar .nav-link:hover {
            background: #F1F5F9;
            color: var(--mac-primary);
        }

        .sidebar .nav-link.active {
            background: var(--mac-primary);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,122,255,0.25);
        }

        .sidebar .nav-link i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        /* ── Main content ── */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            max-width: 100%;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        /* ── Top Navbar ── */
        .top-navbar {
            background: rgba(255,255,255,0.90);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--mac-border);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .top-navbar .breadcrumb {
            margin: 0;
            background: transparent;
            padding: 0;
            flex-wrap: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .top-navbar .breadcrumb-item {
            white-space: nowrap;
        }

        .top-navbar .breadcrumb-item a {
            color: var(--mac-muted);
            text-decoration: none;
        }

        .top-navbar .breadcrumb-item.active {
            color: var(--mac-title);
        }

        .user-dropdown .btn {
            background: #F1F5F9;
            border: 1px solid var(--mac-border);
            color: var(--mac-text);
            font-size: 0.85rem;
            padding: 0.4rem 0.9rem;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .user-dropdown .btn:hover {
            background: #E2E8F0;
            border-color: #CBD5E1;
        }

        .user-dropdown .dropdown-menu {
            background: var(--mac-surface);
            border: 1px solid var(--mac-border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(15,23,42,0.10), 0 2px 8px rgba(15,23,42,0.05);
        }

        .user-dropdown .dropdown-item {
            color: var(--mac-text);
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin: 0.1rem 0.3rem;
        }

        .user-dropdown .dropdown-item:hover {
            background: #F1F5F9;
            color: var(--mac-primary);
        }

        .user-dropdown .dropdown-divider {
            border-color: var(--mac-border);
        }

        /* ── Page content ── */
        .page-content {
            padding: 1.5rem;
        }

        /* ── Cards ── */
        .card-light {
            background: var(--mac-surface);
            border: 1px solid var(--mac-border-soft);
            border-radius: 16px;
            color: var(--mac-text);
            box-shadow: 0 1px 3px rgba(15,23,42,0.04);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .card-light:hover {
            box-shadow: 0 4px 16px rgba(15,23,42,0.08);
            transform: translateY(-1px);
        }

        .card-light .card-header {
            background: #FAFBFC;
            border-bottom: 1px solid var(--mac-border);
            border-radius: 16px 16px 0 0 !important;
            padding: 1rem 1.25rem;
        }

        .card-light .card-header h5,
        .card-light .card-header h6 {
            color: var(--mac-title);
            margin: 0;
            font-weight: 600;
        }

        /* Legacy card-dark class mapped to light for backward compat */
        .card-dark {
            background: var(--mac-surface);
            border: 1px solid var(--mac-border-soft);
            border-radius: 16px;
            color: var(--mac-text);
            box-shadow: 0 1px 3px rgba(15,23,42,0.04);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .card-dark:hover {
            box-shadow: 0 4px 16px rgba(15,23,42,0.08);
            transform: translateY(-1px);
        }

        .card-dark .card-header {
            background: #FAFBFC;
            border-bottom: 1px solid var(--mac-border);
            border-radius: 16px 16px 0 0 !important;
            padding: 1rem 1.25rem;
        }

        .card-dark .card-header h5,
        .card-dark .card-header h6 {
            color: var(--mac-title);
            margin: 0;
            font-weight: 600;
        }

        /* ── Tables ── */
        .table-dark-custom {
            color: var(--mac-text);
        }

        .table-dark-custom thead th {
            background: #FAFBFC;
            color: var(--mac-muted);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--mac-border);
            padding: 0.75rem;
            white-space: nowrap;
        }

        .table-dark-custom tbody td {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid #F1F5F9;
            font-size: 0.85rem;
            vertical-align: middle;
            color: var(--mac-text);
        }

        .table-dark-custom tbody tr:hover {
            background: #F8FAFC;
        }

        /* ── Buttons ── */
        .btn-tuerca {
            background: linear-gradient(135deg, #007AFF, #0055D4);
            border: none;
            color: #fff;
            font-weight: 500;
            border-radius: 10px;
            padding: 0.45rem 1rem;
            font-size: 0.85rem;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,122,255,0.20);
        }

        .btn-tuerca:hover {
            background: linear-gradient(135deg, #0055D4, #003DA5);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0,122,255,0.30);
            color: #fff;
        }

        .btn-gold {
            background: linear-gradient(135deg, #FF9500, #E08600);
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.45rem 1rem;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .btn-gold:hover {
            background: linear-gradient(135deg, #E08600, #CC7A00);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255,149,0,0.30);
            color: #fff;
        }

        .btn-outline-tuerca {
            background: transparent;
            border: 1px solid #CBD5E1;
            color: var(--mac-primary);
            border-radius: 10px;
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-outline-tuerca:hover {
            background: rgba(0,122,255,0.06);
            border-color: var(--mac-primary);
            color: var(--mac-primary);
        }

        /* ── Badges ── */
        .badge-activo {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
        }

        .badge-inactivo {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
        }

        .badge-stock-bajo {
            background: #FFFBEB;
            color: #D97706;
            border: 1px solid #FDE68A;
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
        }

        /* ── Forms ── */
        .form-control-dark,
        .form-select-dark {
            background: #ffffff;
            border: 1px solid #CBD5E1;
            color: var(--mac-text);
            border-radius: 10px;
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
        }

        .form-control-dark:focus,
        .form-select-dark:focus {
            background: #ffffff;
            border-color: var(--mac-primary);
            color: var(--mac-text);
            box-shadow: 0 0 0 3px rgba(0,122,255,0.10);
        }

        .form-control-dark::placeholder {
            color: var(--mac-subtle);
        }

        .form-label-dark {
            color: var(--mac-muted);
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
        }

        /* ── Modals ── */
        .modal-content-dark {
            background: var(--mac-surface);
            border: 1px solid var(--mac-border);
            border-radius: 16px;
            color: var(--mac-text);
            box-shadow: 0 20px 60px rgba(15,23,42,0.12);
        }

        .modal-content-dark .modal-header {
            border-bottom: 1px solid var(--mac-border);
            padding: 1rem 1.25rem;
        }

        .modal-content-dark .modal-header .modal-title {
            color: var(--mac-title);
            font-weight: 600;
        }

        .modal-content-dark .modal-footer {
            border-top: 1px solid var(--mac-border);
        }

        .modal-content-dark .btn-close {
            /* Light mode — no filter needed */
        }

        /* ── Alerts ── */
        .alert-tuerca-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            border-radius: 12px;
        }

        .alert-tuerca-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            border-radius: 12px;
        }

        /* ── Pagination ── */
        .pagination {
            gap: 0.2rem;
            flex-wrap: wrap;
            margin-bottom: 0;
        }

        .pagination .page-link {
            background: var(--mac-surface);
            border: 1px solid var(--mac-border);
            color: var(--mac-text);
            font-size: 0.82rem;
            padding: 0.35rem 0.7rem;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .pagination .page-item.active .page-link {
            background: var(--mac-primary);
            border-color: var(--mac-primary);
            color: #fff;
            box-shadow: 0 2px 6px rgba(0,122,255,0.20);
        }

        .pagination .page-item.disabled .page-link {
            background: #F8FAFC;
            border-color: var(--mac-border);
            color: #CBD5E1;
        }

        .pagination .page-link:hover {
            background: #F1F5F9;
            color: var(--mac-primary);
            border-color: #CBD5E1;
        }

        /* ── Responsive ── */
        .sidebar-toggle {
            display: none;
            min-height: 42px;
            min-width: 42px;
            padding: 0;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            border-radius: 10px;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 0 0 30px rgba(15,23,42,0.25);
                width: 280px;
                max-width: 85vw;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .sidebar-toggle {
                display: inline-flex;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15,23,42,0.55);
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(3px);
                z-index: 1040;
            }
            .sidebar-overlay.show {
                display: block;
            }
            .page-content {
                padding: 1.25rem 1rem;
            }
        }

        @media (max-width: 767.98px) {
            .page-content {
                padding: 0.85rem 0.6rem;
            }
            .top-navbar {
                padding: 0.5rem 0.75rem;
            }
            .top-navbar .breadcrumb {
                max-width: 140px;
                font-size: 0.78rem !important;
            }
            .user-dropdown .user-name {
                display: none;
            }
            .user-dropdown .btn {
                padding: 0.4rem 0.6rem;
                min-height: 42px;
                display: inline-flex;
                align-items: center;
            }
            /* Responsive Modals (w-11/12 max-w-lg) */
            .modal-dialog {
                margin: 1rem auto !important;
                width: 92% !important;
                max-width: 500px !important;
            }
            .modal-content {
                border-radius: 16px;
            }
            .modal-footer {
                flex-direction: column-reverse;
                gap: 0.5rem;
            }
            .modal-footer .btn {
                width: 100%;
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.9rem;
            }
        }

        /* ── Tables & Overflow Container ── */
        .overflow-x-auto, .table-responsive-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid rgba(226,232,240,0.80);
            box-shadow: 0 1px 3px rgba(15,23,42,0.04);
        }

        /* ── Animations ── */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Sidebar --}}
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-text">
                <h5><i class="bi bi-nut me-1"></i> MULTIPARTES</h5>
                <small>El Tuerca — Santa Cruz</small>
            </div>
            <button type="button" class="btn btn-sm btn-light border-0 d-lg-none" onclick="toggleSidebar()" aria-label="Cerrar menú" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;">
                <i class="bi bi-x-lg text-secondary"></i>
            </button>
        </div>

        <div class="nav-section">Principal</div>
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        @if(auth()->user()->rol->nombre_rol === 'Administrador')
        <div class="nav-section">Seguridad y Acceso</div>
        <a href="{{ route('usuarios.index') }}" class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i> Usuarios
        </a>
        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock"></i> Roles
        </a>
        <a href="{{ route('auditoria.index') }}" class="nav-link {{ request()->routeIs('auditoria.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i> Bitácora
        </a>
        @endif

        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
        <div class="nav-section">Catálogo y Vehículos</div>
        <a href="{{ route('categorias.index') }}" class="nav-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i> Categorías
        </a>
        <a href="{{ route('productos.index') }}" class="nav-link {{ request()->routeIs('productos.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i> Productos / Ubicaciones
        </a>
        <a href="{{ route('vehiculos.index') }}" class="nav-link {{ request()->routeIs('vehiculos.*') ? 'active' : '' }}">
            <i class="bi bi-truck"></i> Vehículos
        </a>

        <div class="nav-section">Almacén e Inventario</div>
        <a href="{{ route('inventario.alertas') }}" class="nav-link {{ request()->routeIs('inventario.alertas') ? 'active' : '' }}">
            <i class="bi bi-exclamation-triangle"></i> Alertas de Stock
        </a>
        <a href="{{ route('inventario.ajustes') }}" class="nav-link {{ request()->routeIs('inventario.ajustes*') ? 'active' : '' }}">
            <i class="bi bi-sliders"></i> Ajustes de Stock
        </a>

        <div class="nav-section">Compras y Proveedores</div>
        @if(auth()->user()->rol->nombre_rol === 'Administrador')
        <a href="{{ route('proveedores.index') }}" class="nav-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
            <i class="bi bi-building"></i> Proveedores
        </a>
        @endif
        <a href="{{ route('compras.create') }}" class="nav-link {{ request()->routeIs('compras.create') ? 'active' : '' }}">
            <i class="bi bi-cart-plus"></i> Recepción de Mercadería
        </a>
        <a href="{{ route('compras.index') }}" class="nav-link {{ request()->routeIs('compras.index') || request()->routeIs('compras.show') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i> Historial de Compras
        </a>
        <a href="{{ route('compras.historial-costos') }}" class="nav-link {{ request()->routeIs('compras.historial-costos') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Historial de Costos
        </a>
        @if(auth()->user()->rol->nombre_rol === 'Administrador')
        <a href="{{ route('compras.cuentas-por-pagar') }}" class="nav-link {{ request()->routeIs('compras.cuentas-por-pagar') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i> Cuentas por Pagar
        </a>
        @endif
        @endif
    </nav>

    {{-- Main Content --}}
    <div class="main-content">
        {{-- Top Navbar --}}
        <div class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-tuerca sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0" style="font-size: 0.85rem;">
                        @yield('breadcrumb')
                    </ol>
                </nav>
            </div>

            <div class="user-dropdown dropdown">
                <button class="btn dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <span class="user-name">{{ auth()->user()->nombre_completo }}</span>
                    <span class="badge ms-1" style="font-size: 0.65rem; background: rgba(0,122,255,0.10); color: #007AFF;">{{ auth()->user()->rol->nombre_rol }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('perfil.password') }}"><i class="bi bi-key me-2"></i>Cambiar Contraseña</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Page Content --}}
        <div class="page-content fade-in">
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="alert alert-tuerca-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-tuerca-error alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-tuerca-error alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Errores de validación:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
    @stack('scripts')
</body>
</html>
