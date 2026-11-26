<!-- Sticky Translucent Navbar (80px -> 64px al scroll) -->
<header id="czNavbar" class="cz-navbar">
    <div class="cz-container cz-navbar-inner">
        <!-- Logo Institucional -->
        <a href="{{ route('home') }}" class="cz-brand" aria-label="Multipartes El Tuerca - Inicio">
            <span class="cz-brand-mark">
                <i class="bi bi-gear-wide-connected"></i>
            </span>
            <span class="min-w-0">
                <span class="d-block cz-brand-name">MULTIPARTES EL TUERCA</span>
                <span class="d-block cz-brand-loc">Santa Cruz · Repuestos OEM</span>
            </span>
        </a>

        <!-- Navegación Desktop (máximo 4 enlaces limpios) -->
        <nav class="cz-nav-links" aria-label="Navegación principal">
            <a href="#catalogo" class="cz-nav-link">Catálogo</a>
            <a href="#categorias" class="cz-nav-link">Categorías</a>
            <a href="#preguntas-frecuentes" class="cz-nav-link">Preguntas Frecuentes</a>
            <a href="#ubicacion" class="cz-nav-link">Ubicación</a>
        </nav>

        <!-- Acciones: Carrito + Acceso / Portal -->
        <div class="cz-nav-actions">
            <button type="button"
                    id="btnAbrirCarritoWeb"
                    class="cz-cart-trigger"
                    onclick="abrirModalCarritoWeb()"
                    aria-label="Abrir carrito de repuestos">
                <i class="bi bi-bag-check-fill text-success"></i>
                <span class="d-none d-sm-inline">Carrito</span>
                <span id="badgeCarritoWeb" class="cz-cart-badge">0</span>
            </button>

            <div class="d-none d-lg-flex align-items-center gap-2">
                @auth
                    @if(auth()->user()->id_rol === 5)
                        <a href="{{ route('mis-cotizaciones.index') }}" class="cz-btn-emerald">
                            <i class="bi bi-person-check-fill"></i>
                            <span>Mi Portal</span>
                        </a>
                    @else
                        <a href="{{ route('dashboard') }}" class="cz-btn-emerald">
                            <i class="bi bi-speedometer2"></i>
                            <span>Mi Portal</span>
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST" class="d-inline m-0">
                        @csrf
                        <button type="submit"
                                class="cz-btn-ghost-dark px-3"
                                title="Cerrar Sesión"
                                aria-label="Cerrar Sesión">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="cz-btn-emerald">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Ingresar</span>
                    </a>
                @endauth
            </div>

            <!-- Botón Hamburguesa Móvil -->
            <button type="button"
                    id="czBtnMobileMenu"
                    class="cz-hamburger"
                    onclick="toggleMobileOverlay(true)"
                    aria-label="Abrir menú de navegación"
                    aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>
</header>

<!-- Menú Móvil Overlay Completo -->
<div id="czMobileOverlay" class="cz-mobile-overlay" aria-hidden="true">
    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom" style="border-color: var(--cz-dark-border) !important;">
        <a href="{{ route('home') }}" class="cz-brand" onclick="toggleMobileOverlay(false)">
            <span class="cz-brand-mark">
                <i class="bi bi-gear-wide-connected"></i>
            </span>
            <span>
                <span class="d-block cz-brand-name">MULTIPARTES EL TUERCA</span>
                <span class="d-block cz-brand-loc">Santa Cruz de la Sierra</span>
            </span>
        </a>
        <button type="button"
                class="cz-drawer-close"
                onclick="toggleMobileOverlay(false)"
                aria-label="Cerrar menú">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="d-flex flex-column mt-3 flex-grow-1" aria-label="Menú móvil">
        <a href="#catalogo" class="cz-mobile-nav-link" onclick="toggleMobileOverlay(false)">
            <span>Catálogo de Repuestos</span>
            <i class="bi bi-arrow-right text-success"></i>
        </a>
        <a href="#categorias" class="cz-mobile-nav-link" onclick="toggleMobileOverlay(false)">
            <span>Categorías y Sistemas</span>
            <i class="bi bi-arrow-right text-success"></i>
        </a>
        <a href="#preguntas-frecuentes" class="cz-mobile-nav-link" onclick="toggleMobileOverlay(false)">
            <span>Preguntas Frecuentes</span>
            <i class="bi bi-arrow-right text-success"></i>
        </a>
        <a href="#ubicacion" class="cz-mobile-nav-link" onclick="toggleMobileOverlay(false)">
            <span>Ubicación y Contacto</span>
            <i class="bi bi-arrow-right text-success"></i>
        </a>
    </nav>

    <div class="d-grid gap-2 pt-3 border-top" style="border-color: var(--cz-dark-border) !important;">
        @auth
            @if(auth()->user()->id_rol === 5)
                <a href="{{ route('mis-cotizaciones.index') }}" class="cz-btn-emerald w-100">
                    <i class="bi bi-person-check-fill"></i>
                    <span>Mi Portal ({{ Str::words(auth()->user()->nombre_completo, 2, '') }})</span>
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="cz-btn-emerald w-100">
                    <i class="bi bi-speedometer2"></i>
                    <span>Mi Portal ({{ auth()->user()->rol->nombre_rol ?? 'Personal' }})</span>
                </a>
            @endif
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="cz-btn-ghost-dark w-100">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="cz-btn-emerald w-100">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Ingresar a Mi Cuenta</span>
            </a>
            <a href="{{ route('register') }}" class="cz-btn-ghost-dark w-100">
                <i class="bi bi-person-plus"></i>
                <span>Crear Cuenta de Cliente</span>
            </a>
        @endauth
    </div>
</div>

<!-- Notificaciones de sesión (compra confirmada, proforma, avisos) -->
@if(session('success') || session('warning') || session('error'))
    <div class="cz-flash-wrap">
        <div class="cz-container d-flex flex-column gap-2">
            @if(session('success'))
                <div class="cz-flash cz-flash-success" role="status">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-5 text-success flex-shrink-0"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.remove()" aria-label="Cerrar"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="cz-flash cz-flash-warning" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                        <span>{{ session('warning') }}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.remove()" aria-label="Cerrar"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="cz-flash cz-flash-error" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-x-circle-fill fs-5 text-danger flex-shrink-0"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.remove()" aria-label="Cerrar"></button>
                </div>
            @endif
        </div>
    </div>
@endif
