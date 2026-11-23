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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            }
        }
    </script>
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

        {{-- 1. SEGURIDAD Y ACCESO (Solo Administrador - Rol 1) --}}
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

        {{-- 2. CATÁLOGO DE REPUESTOS (Administrador, Bodeguero, Vendedor - Roles 1, 2, 3) --}}
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero', 'Vendedor']))
        <div class="nav-section">Catálogo de Repuestos</div>
        <a href="{{ route('productos.index') }}" class="nav-link {{ request()->routeIs('productos.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i> Catálogo / Ubicaciones
        </a>
        <a href="{{ route('categorias.index') }}" class="nav-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i> Categorías
        </a>
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
        <a href="{{ route('vehiculos.index') }}" class="nav-link {{ request()->routeIs('vehiculos.*') ? 'active' : '' }}">
            <i class="bi bi-truck"></i> Catálogo Vehicular
        </a>
        @endif
        @endif

        {{-- 3. ALMACÉN E INVENTARIO (Administrador, Bodeguero - Roles 1, 2) --}}
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
        <div class="nav-section">Almacén e Inventario</div>
        <a href="{{ route('inventario.alertas') }}" class="nav-link {{ request()->routeIs('inventario.alertas') ? 'active' : '' }}">
            <i class="bi bi-exclamation-triangle"></i> Alertas de Stock
        </a>
        <a href="{{ route('inventario.ajustes') }}" class="nav-link {{ request()->routeIs('inventario.ajustes*') ? 'active' : '' }}">
            <i class="bi bi-sliders"></i> Ajustes de Stock
        </a>
        @endif

        {{-- 4. COMPRAS Y PROVEEDORES (Administrador, Bodeguero - Roles 1, 2) --}}
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
        <div class="nav-section">Compras y Recepción</div>
        <a href="{{ route('proveedores.index') }}" class="nav-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
            <i class="bi bi-building"></i> Proveedores
        </a>
        <a href="{{ route('compras.create') }}" class="nav-link {{ request()->routeIs('compras.create') ? 'active' : '' }}">
            <i class="bi bi-cart-plus"></i> Recepción de Mercadería
        </a>
        <a href="{{ route('compras.index') }}" class="nav-link {{ request()->routeIs('compras.index') || request()->routeIs('compras.show') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i> Historial de Compras
        </a>
        <a href="{{ route('compras.historial-costos') }}" class="nav-link {{ request()->routeIs('compras.historial-costos') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Historial de Costos
        </a>
        @endif

        {{-- 5. VENTAS, POS, COTIZACIONES Y CLIENTES (Administrador, Vendedor, Cajero) --}}
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Vendedor', 'Cajero']))
        <div class="nav-section">Comercial y Punto de Venta</div>
        <a href="{{ route('ventas.pos') }}" class="nav-link {{ request()->routeIs('ventas.pos') || request()->routeIs('ventas.create') ? 'active' : '' }}">
            <i class="bi bi-cart-check"></i> Punto de Venta (POS)
        </a>
        <a href="{{ route('ventas.index') }}" class="nav-link {{ request()->routeIs('ventas.index') || request()->routeIs('ventas.ticket') || request()->routeIs('ventas.show') ? 'active' : '' }}">
            <i class="bi bi-receipt-cutoff"></i> Notas de Venta
        </a>
        <a href="{{ route('cotizaciones.index') }}" class="nav-link {{ request()->routeIs('cotizaciones.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-spreadsheet"></i> Cotizaciones y Proformas
        </a>
        <a href="{{ route('clientes.index') }}" class="nav-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
            <i class="bi bi-person-vcard"></i> Clientes y Talleres
        </a>
        @endif

        {{-- 6. CAJA Y FINANZAS (Administrador, Cajero, Bodeguero, Vendedor) --}}
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Cajero', 'Bodeguero', 'Vendedor']))
        <div class="nav-section">Tesorería y Finanzas</div>
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Cajero', 'Vendedor']))
        <a href="{{ route('caja.index') }}" class="nav-link {{ request()->routeIs('caja.*') ? 'active' : '' }}">
            <i class="bi bi-cash-coin"></i> Sesiones y Arqueo de Caja
        </a>
        @endif
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Cajero', 'Bodeguero']))
        <a href="{{ route('compras.cuentas-por-pagar') }}" class="nav-link {{ request()->routeIs('compras.cuentas-por-pagar') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i> Cuentas por Pagar
        </a>
        @endif
        @endif

        {{-- 7. REPORTES Y ANALÍTICA (Según Rol) --}}
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero', 'Cajero']))
        <div class="nav-section">Reportes y Analítica</div>
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
        <a href="{{ route('reportes.rotacion') }}" class="nav-link {{ request()->routeIs('reportes.rotacion') ? 'active' : '' }}">
            <i class="bi bi-arrow-repeat"></i> Rotación de Repuestos
        </a>
        @endif
        @if(auth()->user()->rol->nombre_rol === 'Administrador')
        <a href="{{ route('reportes.rentabilidad') }}" class="nav-link {{ request()->routeIs('reportes.rentabilidad') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Rentabilidad y Márgenes
        </a>
        @endif
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
        <a href="{{ route('reportes.compras') }}" class="nav-link {{ request()->routeIs('reportes.compras') ? 'active' : '' }}">
            <i class="bi bi-truck"></i> Compras por Distribuidor
        </a>
        @endif
        @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Cajero']))
        <a href="{{ route('reportes.balance') }}" class="nav-link {{ request()->routeIs('reportes.balance') ? 'active' : '' }}">
            <i class="bi bi-pie-chart"></i> Balance Medios de Pago
        </a>
        @endif
        @endif

        {{-- 8. ENLACE AL CATÁLOGO PÚBLICO --}}
        <div class="nav-section">Vitrina Comercial</div>
        <a href="{{ route('home') }}" target="_blank" class="nav-link">
            <i class="bi bi-shop"></i> Catálogo Público <i class="bi bi-box-arrow-up-right ms-auto" style="font-size:0.75rem;"></i>
        </a>
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

    {{-- ═══════════════════════════════════════════════════════════════════════════
         ASISTENTE INTELIGENTE MULTIMODAL DE VOZ Y TEXTO (VOICE-TO-SQL)
    ═══════════════════════════════════════════════════════════════════════════ --}}
    <style>
        @keyframes pulseMicWave {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.65); transform: scale(1); }
            70% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); transform: scale(1.06); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); transform: scale(1); }
        }
        .ai-mic-listening {
            background: linear-gradient(135deg, #EF4444, #DC2626) !important;
            color: #ffffff !important;
            border-color: #DC2626 !important;
            animation: pulseMicWave 1.4s infinite;
        }
        .ai-widget-panel {
            position: fixed;
            bottom: 90px;
            right: 24px;
            width: 420px;
            max-width: calc(100vw - 32px);
            height: 560px;
            max-height: calc(100vh - 120px);
            background: #ffffff;
            border: 1px solid #CBD5E1;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22), 0 4px 16px rgba(0, 122, 255, 0.10);
            z-index: 9999;
            display: none;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.25s ease;
        }
        .ai-widget-panel.open {
            display: flex;
        }
        .ai-fab-btn {
            position: fixed;
            bottom: 22px;
            right: 24px;
            z-index: 9999;
            height: 54px;
            padding: 0 20px;
            border-radius: 999px;
            background: linear-gradient(135deg, #0F172A 0%, #0055D4 100%);
            color: #ffffff;
            border: 2px solid rgba(255,255,255,0.85);
            box-shadow: 0 10px 25px rgba(0, 85, 212, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 0.86rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .ai-fab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 85, 212, 0.45);
        }
        .ai-chip {
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            color: #334155;
            border-radius: 999px;
            padding: 5px 11px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .ai-chip:hover {
            background: #EFF6FF;
            border-color: #93C5FD;
            color: #1D4ED8;
        }
        @media print {
            .sidebar, .top-navbar, .no-print, .alert, .ai-fab-btn, .ai-widget-panel { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            .page-content { padding: 0 !important; }
            body { background: #ffffff !important; }
        }
    </style>

    <!-- Botón Flotante Principal -->
    <button type="button" id="aiAssistantFab" class="ai-fab-btn" onclick="toggleAiAssistant()" title="Asistente Inteligente por Voz y Texto">
        <i class="fa-solid fa-microphone-lines text-base text-sky-300"></i>
        <span>Asistente El Tuerca</span>
        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
    </button>

    <!-- Ventana Flotante del Asistente -->
    <div id="aiAssistantPanel" class="ai-widget-panel" aria-live="polite">
        <!-- Cabecera -->
        <div class="px-4 py-3 bg-gradient-to-r from-slate-900 via-slate-800 to-blue-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-600/30 border border-blue-400/40 flex items-center justify-center text-sky-300">
                    <i class="fa-solid fa-robot text-base"></i>
                </div>
                <div>
                    <div class="font-bold text-sm leading-tight">Asistente El Tuerca</div>
                    <div class="text-[11px] text-slate-300 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span id="aiStatusSubtitle">Listo para ayudarte por voz o texto</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" id="aiResetBtn" onclick="limpiarCharlaAi()"
                        class="px-2 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center gap-1 text-[10.5px] font-semibold border-0 transition"
                        title="Nueva charla / Limpiar conversación">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                    <span class="hidden sm:inline">Nueva charla</span>
                </button>
                <button type="button" id="aiMuteBtn" onclick="toggleAiVoiceMute()"
                        class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center border-0 transition"
                        title="Activar / Silenciar respuesta por voz">
                    <i id="aiMuteIcon" class="fa-solid fa-volume-high text-xs"></i>
                </button>
                <button type="button" onclick="toggleAiAssistant()"
                        class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center border-0 transition"
                        title="Cerrar asistente">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Chips de Sugerencias Rápidas -->
        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 flex items-center gap-1.5 overflow-x-auto" style="scrollbar-width: thin;">
            <button type="button" class="ai-chip" onclick="enviarSugerenciaAi('Quiero registrar una venta')">
                <i class="fa-solid fa-cart-shopping text-emerald-600 mr-1"></i>Registrar venta
            </button>
            <button type="button" class="ai-chip" onclick="enviarSugerenciaAi('¿Qué repuestos están en stock crítico?')">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1"></i>Stock bajo
            </button>
            <button type="button" class="ai-chip" onclick="enviarSugerenciaAi('Mostrar rotación de repuestos y productos hueso')">
                <i class="fa-solid fa-arrows-rotate text-blue-600 mr-1"></i>Rotación
            </button>
            @if(auth()->user()->rol->nombre_rol === 'Administrador')
            <button type="button" class="ai-chip" onclick="enviarSugerenciaAi('¿Cómo vamos con la rentabilidad y ganancias?')">
                <i class="fa-solid fa-chart-line text-emerald-600 mr-1"></i>Ganancias
            </button>
            @endif
            @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Cajero']))
            <button type="button" class="ai-chip" onclick="enviarSugerenciaAi('¿Cómo está la caja y los pagos de hoy?')">
                <i class="fa-solid fa-scale-balanced text-indigo-600 mr-1"></i>Caja y pagos
            </button>
            @endif
            <button type="button" class="ai-chip" onclick="enviarSugerenciaAi('Buscar repuestos compatibles para Vitara')">
                <i class="fa-solid fa-car text-sky-600 mr-1"></i>Compatibilidad
            </button>
        </div>

        <!-- Historial de Conversación -->
        <div id="aiChatMessages" class="flex-1 p-3.5 overflow-y-auto space-y-3 bg-slate-50/50 text-xs">
            <div class="bg-white border border-slate-200 rounded-2xl rounded-tl-sm p-3 shadow-sm text-slate-700" id="aiWelcomeCard">
                <div class="font-bold text-slate-900 mb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-hand-sparkles text-blue-600"></i>
                    ¡Hola, {{ Str::words(auth()->user()->nombre_completo, 2, '') }}!
                </div>
                <p class="mb-0 leading-relaxed">
                    Háblame o escríbeme con toda confianza, como en el mostrador. Puedo ayudarte a buscar repuestos, ver stock y ventas del día, o <strong>armar contigo una venta, cotización, compra o merma paso a paso</strong> antes de guardarla.
                </p>
            </div>
        </div>

        <!-- Barra de Entrada de Texto y Micrófono -->
        <form id="aiChatForm" onsubmit="manejarEnvioAi(event)" class="p-3 bg-white border-t border-slate-200 flex items-center gap-2">
            <button type="button" id="aiMicBtn" onclick="iniciarReconocimientoVoz()"
                    class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center border border-slate-300 transition flex-shrink-0"
                    title="Hablar por micrófono (Español)">
                <i id="aiMicIcon" class="fa-solid fa-microphone text-sm"></i>
            </button>
            <input type="text" id="aiQueryInput" maxlength="400" autocomplete="off"
                   placeholder="Escribe o pulsa el micrófono para hablar..."
                   class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-blue-600">
            <button type="submit" id="aiSendBtn"
                    class="w-10 h-10 rounded-xl bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center border-0 shadow-sm transition flex-shrink-0"
                    title="Enviar consulta">
                <i class="fa-solid fa-paper-plane text-xs"></i>
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        // ── Lógica del Asistente Multimodal (Voice-to-SQL + Web Speech API) ──
        let aiVoiceMuted = false;
        let aiRecognition = null;
        let aiIsListening = false;

        function toggleAiAssistant() {
            const panel = document.getElementById('aiAssistantPanel');
            panel.classList.toggle('open');
            if (panel.classList.contains('open')) {
                setTimeout(() => document.getElementById('aiQueryInput')?.focus(), 120);
            } else if (window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }
        }

        function toggleAiVoiceMute() {
            aiVoiceMuted = !aiVoiceMuted;
            const icon = document.getElementById('aiMuteIcon');
            if (aiVoiceMuted) {
                icon.className = 'fa-solid fa-volume-xmark text-xs text-rose-300';
                if (window.speechSynthesis) window.speechSynthesis.cancel();
            } else {
                icon.className = 'fa-solid fa-volume-high text-xs';
            }
        }

        function enviarSugerenciaAi(texto) {
            const input = document.getElementById('aiQueryInput');
            input.value = texto;
            ejecutarConsultaAi(texto);
        }

        function manejarEnvioAi(e) {
            e.preventDefault();
            const input = document.getElementById('aiQueryInput');
            const texto = (input.value || '').trim();
            if (!texto) return;
            input.value = '';
            ejecutarConsultaAi(texto);
        }

        function agregarBurbujaUsuario(texto) {
            const chat = document.getElementById('aiChatMessages');
            const div = document.createElement('div');
            div.className = 'flex justify-end';
            div.innerHTML = `
                <div class="bg-blue-600 text-white rounded-2xl rounded-tr-sm px-3.5 py-2.5 max-w-[85%] shadow-sm leading-relaxed">
                    ${escaparHtmlAi(texto)}
                </div>
            `;
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
        }

        function agregarBurbujaRespuesta(data) {
            const chat = document.getElementById('aiChatMessages');
            const div = document.createElement('div');

            let bordeClase = 'bg-white border border-slate-200';
            let iconoTitulo = '<i class="fa-solid fa-comment-dots text-blue-600"></i> Asistente El Tuerca';
            if (data.tipo === 'restriccion') {
                bordeClase = 'bg-amber-50 border border-amber-300';
                iconoTitulo = '<i class="fa-solid fa-shield-halved text-amber-600"></i> Permisos de tu cuenta';
            } else if (data.tipo === 'error') {
                bordeClase = 'bg-rose-50 border border-rose-300';
                iconoTitulo = '<i class="fa-solid fa-circle-exclamation text-rose-600"></i> Revisemos este dato';
            } else if (data.tipo === 'ejecucion_exitosa') {
                bordeClase = 'bg-emerald-50 border border-emerald-300';
                iconoTitulo = '<i class="fa-solid fa-circle-check text-emerald-600"></i> ¡Operación Registrada!';
            } else if (data.tipo === 'confirmacion') {
                bordeClase = 'bg-blue-50/70 border-2 border-blue-300';
                iconoTitulo = '<i class="fa-solid fa-clipboard-check text-blue-700"></i> Revisa y confirma';
            } else if (data.tipo === 'aclaracion') {
                bordeClase = 'bg-sky-50/70 border border-sky-200';
                iconoTitulo = '<i class="fa-solid fa-comments text-sky-600"></i> Seguimos armándolo';
            }

            div.className = `${bordeClase} rounded-2xl rounded-tl-sm p-3 shadow-sm text-slate-800 space-y-2`;

            const badgeOrigen = data.origen === 'gemini'
                ? '<span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold">IA El Tuerca</span>'
                : (data.origen === 'smartdb'
                    ? '<span class="px-1.5 py-0.5 rounded bg-emerald-700 text-white text-[10px] font-bold">Confirmado</span>'
                    : '<span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-200 text-[10px] font-bold">Mostrador</span>');

            // 1. Tabla de datos analíticos
            let tablaHtml = '';
            if (Array.isArray(data.datos_resumen) && data.datos_resumen.length > 0) {
                const cols = Object.keys(data.datos_resumen[0]);
                const ths = cols.map(c => `<th class="py-1.5 px-2 bg-slate-100 text-slate-600 font-bold text-[10px] uppercase whitespace-nowrap border-b border-slate-200">${escaparHtmlAi(c.replace(/_/g, ' '))}</th>`).join('');
                const trs = data.datos_resumen.slice(0, 8).map(row => {
                    const tds = cols.map(c => `<td class="py-1.5 px-2 border-b border-slate-100 text-[11px] whitespace-nowrap">${escaparHtmlAi(String(row[c] ?? '-'))}</td>`).join('');
                    return `<tr>${tds}</tr>`;
                }).join('');

                tablaHtml = `
                    <div class="overflow-x-auto border border-slate-200 rounded-xl mt-2 bg-white">
                        <table class="w-full text-left border-collapse">
                            <thead><tr>${ths}</tr></thead>
                            <tbody>${trs}</tbody>
                        </table>
                    </div>
                `;
            }

            // 2. Tarjeta de Confirmación Humana (Modo Transaccional Paso 1)
            let confirmacionHtml = '';
            if (data.tipo === 'confirmacion' && data.accion_pendiente) {
                const acc = data.accion_pendiente;
                const token = escaparHtmlAi(acc.action_token);
                const resumenFilas = Object.entries(acc.resumen || {}).map(([k, v]) => `
                    <div class="flex justify-between gap-2 text-[11px] py-0.5 border-b border-slate-100 last:border-0">
                        <span class="text-slate-500 font-semibold">${escaparHtmlAi(k)}:</span>
                        <span class="text-slate-900 font-bold text-right">${escaparHtmlAi(String(v))}</span>
                    </div>
                `).join('');

                let itemsHtml = '';
                if (Array.isArray(acc.items) && acc.items.length > 0) {
                    const liRows = acc.items.map(it => `
                        <tr class="border-b border-slate-100">
                            <td class="py-1 px-1.5 text-[10.5px] font-semibold text-slate-800">${escaparHtmlAi(it.nombre_pieza)}</td>
                            <td class="py-1 px-1.5 text-center text-[10.5px] font-bold">${escaparHtmlAi(String(it.cantidad))}</td>
                            <td class="py-1 px-1.5 text-right font-mono text-[10.5px]">Bs. ${Number(it.subtotal || 0).toFixed(2)}</td>
                        </tr>
                    `).join('');
                    itemsHtml = `
                        <div class="overflow-x-auto border border-slate-200 rounded-lg bg-slate-50 mt-2">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-[9.5px] uppercase text-slate-500">
                                        <th class="py-1 px-1.5">Repuesto</th>
                                        <th class="py-1 px-1.5 text-center">Cant</th>
                                        <th class="py-1 px-1.5 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>${liRows}</tbody>
                            </table>
                        </div>
                    `;
                }

                const advHtml = acc.advertencia
                    ? `<div class="p-2 rounded-lg bg-amber-100/80 border border-amber-300 text-amber-900 text-[10.5px] mt-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>${escaparHtmlAi(acc.advertencia)}</div>`
                    : '';

                confirmacionHtml = `
                    <div class="bg-white border border-blue-200 rounded-xl p-2.5 mt-2 space-y-1.5" id="cardConfirm_${token}">
                        <div class="flex items-center justify-between gap-2 pb-1 border-b border-slate-100">
                            <span class="font-bold text-slate-900 text-[11.5px]">${escaparHtmlAi(acc.titulo || 'Confirmar Operación')}</span>
                            <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[9.5px] font-bold">${escaparHtmlAi(acc.badge || 'Smart-DB')}</span>
                        </div>
                        <div>${resumenFilas}</div>
                        ${itemsHtml}
                        ${advHtml}
                        <div class="flex items-center gap-2 pt-2" id="btnsConfirm_${token}">
                            <button type="button" onclick="confirmarAccionAi('${token}', false)"
                                    class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] border-0 shadow-sm transition">
                                <i class="fa-solid fa-check-double mr-1"></i>Confirmar y Ejecutar
                            </button>
                            <button type="button" onclick="confirmarAccionAi('${token}', true)"
                                    class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] border border-slate-300 transition">
                                Cancelar
                            </button>
                        </div>
                    </div>
                `;
            }

            // 3. Botón directo a Ticket Térmico 80mm / PDF / Comprobante tras ejecución exitosa
            let enlaceHtml = '';
            if (data.enlace_comprobante || data.enlace_pdf) {
                const btnPrincipal = data.enlace_comprobante ? `
                    <a href="${escaparHtmlAi(data.enlace_comprobante)}" target="_blank"
                       class="inline-flex items-center gap-1.5 py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] text-decoration-none shadow-sm transition">
                        <i class="fa-solid fa-receipt"></i> ${escaparHtmlAi(data.etiqueta_enlace || 'Abrir Comprobante')}
                    </a>
                ` : '';
                const btnPdf = data.enlace_pdf ? `
                    <a href="${escaparHtmlAi(data.enlace_pdf)}" target="_blank"
                       class="inline-flex items-center gap-1.5 py-2 px-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-[11px] text-decoration-none shadow-sm transition">
                        <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                    </a>
                ` : '';
                enlaceHtml = `<div class="pt-1 flex flex-wrap gap-1.5">${btnPrincipal}${btnPdf}</div>`;
            }

            div.innerHTML = `
                <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-slate-900 text-[11px] flex items-center gap-1">
                        ${iconoTitulo}
                    </span>
                    ${badgeOrigen}
                </div>
                <p class="mb-0 leading-relaxed text-slate-700 whitespace-pre-line">${escaparHtmlAi(data.texto_pantalla || '')}</p>
                ${tablaHtml}
                ${confirmacionHtml}
                ${enlaceHtml}
            `;
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
        }

        async function limpiarCharlaAi() {
            const chat = document.getElementById('aiChatMessages');
            const welcome = document.getElementById('aiWelcomeCard');
            const subtitle = document.getElementById('aiStatusSubtitle');
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            subtitle.textContent = 'Reiniciando conversación...';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resp = await fetch("{{ route('asistente.limpiar') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({})
                });
                const data = await resp.json();
                chat.innerHTML = '';
                if (welcome) chat.appendChild(welcome);
                agregarBurbujaRespuesta(data);
            } catch (err) {
                chat.innerHTML = '';
                if (welcome) chat.appendChild(welcome);
            } finally {
                subtitle.textContent = 'Listo para ayudarte por voz o texto';
            }
        }

        async function confirmarAccionAi(actionToken, cancelar) {
            const btns = document.getElementById(`btnsConfirm_${actionToken}`);
            if (btns) {
                btns.innerHTML = `<span class="text-[11px] font-semibold text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i>${cancelar ? 'Cancelando...' : 'Guardando operación...'}</span>`;
            }

            const subtitle = document.getElementById('aiStatusSubtitle');
            subtitle.textContent = cancelar ? 'Cancelando...' : 'Registrando tu operación...';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resp = await fetch("{{ route('asistente.confirmar') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ action_token: actionToken, cancelar: !!cancelar })
                });

                const data = await resp.json();
                subtitle.textContent = 'Listo para ayudarte por voz o texto';

                if (btns) {
                    btns.innerHTML = cancelar
                        ? '<span class="text-[11px] font-bold text-slate-500"><i class="fa-solid fa-ban mr-1"></i>Cancelado</span>'
                        : (data.ok
                            ? '<span class="text-[11px] font-bold text-emerald-700"><i class="fa-solid fa-check-circle mr-1"></i>¡Listo y guardado!</span>'
                            : '<span class="text-[11px] font-bold text-rose-600"><i class="fa-solid fa-circle-xmark mr-1"></i>No se pudo completar</span>');
                }

                agregarBurbujaRespuesta(data);
                hablarRespuestaEnEspanol(data.texto_voz || data.texto_pantalla || '');
            } catch (err) {
                subtitle.textContent = 'Listo para ayudarte por voz o texto';
                if (btns) {
                    btns.innerHTML = '<span class="text-[11px] font-bold text-rose-600">Se perdió la conexión al confirmar</span>';
                }
                agregarBurbujaRespuesta({
                    tipo: 'error',
                    origen: 'local',
                    texto_pantalla: 'Uy, se cortó un momento la conexión. Revisa tu internet e intenta nuevamente.',
                    datos_resumen: []
                });
            }
        }

        async function ejecutarConsultaAi(consultaTexto) {
            agregarBurbujaUsuario(consultaTexto);

            const subtitle = document.getElementById('aiStatusSubtitle');
            subtitle.textContent = 'Revisando en el sistema...';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resp = await fetch("{{ route('asistente.consultar') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ consulta: consultaTexto })
                });

                const data = await resp.json();
                subtitle.textContent = 'Listo para ayudarte por voz o texto';

                agregarBurbujaRespuesta(data);
                hablarRespuestaEnEspanol(data.texto_voz || data.texto_pantalla || '');
            } catch (err) {
                subtitle.textContent = 'Listo para ayudarte por voz o texto';
                agregarBurbujaRespuesta({
                    origen: 'local',
                    texto_pantalla: 'No pude comunicarme con el servidor en este momento. ¿Intentamos de nuevo?',
                    datos_resumen: []
                });
            }
        }

        function hablarRespuestaEnEspanol(textoBruto) {
            if (aiVoiceMuted || !('speechSynthesis' in window) || !textoBruto) return;

            window.speechSynthesis.cancel();

            // Limpiar símbolos Markdown y normalizar abreviaturas monetarias
            const textoLimpio = String(textoBruto)
                .replace(/[\*\#\_`~>]/g, '')
                .replace(/\bBs\.?\s*/gi, 'bolivianos ')
                .replace(/\s+/g, ' ')
                .trim();

            const utterance = new SpeechSynthesisUtterance(textoLimpio);
            utterance.lang = 'es-BO';
            utterance.rate = 1.02;
            utterance.pitch = 1.0;

            const voces = window.speechSynthesis.getVoices();
            if (voces && voces.length > 0) {
                const vozEs = voces.find(v => v.lang && (v.lang.toLowerCase().startsWith('es-bo') || v.lang.toLowerCase().startsWith('es-419') || v.lang.toLowerCase().startsWith('es-mx')))
                           || voces.find(v => v.lang && v.lang.toLowerCase().startsWith('es'));
                if (vozEs) {
                    utterance.voice = vozEs;
                    utterance.lang = vozEs.lang;
                }
            }

            window.speechSynthesis.speak(utterance);
        }

        function iniciarReconocimientoVoz() {
            const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRec) {
                alert('Tu navegador no soporta reconocimiento de voz nativo (Web Speech API). Te recomendamos usar Google Chrome o Microsoft Edge, o escribir tu consulta directamente.');
                return;
            }

            if (aiIsListening && aiRecognition) {
                aiRecognition.stop();
                return;
            }

            if (window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }

            aiRecognition = new SpeechRec();
            aiRecognition.lang = 'es-BO';
            aiRecognition.continuous = false;
            aiRecognition.interimResults = false;

            const micBtn = document.getElementById('aiMicBtn');
            const subtitle = document.getElementById('aiStatusSubtitle');
            const input = document.getElementById('aiQueryInput');

            aiRecognition.onstart = function () {
                aiIsListening = true;
                micBtn.classList.add('ai-mic-listening');
                subtitle.textContent = 'Escuchando por micrófono... Habla ahora';
                input.placeholder = 'Escuchando tu voz en español...';
            };

            aiRecognition.onresult = function (event) {
                const transcripcion = event.results?.[0]?.[0]?.transcript || '';
                if (transcripcion.trim() !== '') {
                    input.value = transcripcion.trim();
                    ejecutarConsultaAi(transcripcion.trim());
                    input.value = '';
                }
            };

            aiRecognition.onerror = function (event) {
                if (event.error === 'language-not-supported') {
                    aiRecognition.lang = 'es-419';
                }
                subtitle.textContent = 'Consultas por voz o texto en tiempo real';
            };

            aiRecognition.onend = function () {
                aiIsListening = false;
                micBtn.classList.remove('ai-mic-listening');
                subtitle.textContent = 'Consultas por voz o texto en tiempo real';
                input.placeholder = 'Escribe o pulsa el micrófono para hablar...';
            };

            aiRecognition.start();
        }

        function escaparHtmlAi(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>
