    <!-- ── Top Utility Strip ── -->
    <div class="top-utility-bar">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> Santa Cruz de la Sierra - Bolivia</span>
                <span class="d-none d-md-inline text-secondary">|</span>
                <span class="d-none d-md-inline"><i class="bi bi-clock-fill text-warning me-1"></i> Lun - Sáb: 08:00 - 18:30</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="https://wa.me/59170012345" target="_blank">
                    <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp: (+591) 700-12345
                </a>
                <span class="text-secondary">|</span>
                <a href="tel:59133456789">
                    <i class="bi bi-telephone-fill text-primary me-1"></i> Tel: 3-345-6789
                </a>
            </div>
        </div>
    </div>

    <!-- ── Public Header & Navbar ── -->
    <header class="public-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="brand-logo-container">
                <div class="brand-logo-icon">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div>
                    <div class="brand-title">MULTIPARTES EL TUERCA</div>
                    <div class="brand-subtitle">Santa Cruz · Catálogo Automotriz</div>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="d-none d-lg-flex align-items-center gap-4">
                <a href="#marcas" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Marcas</a>
                <a href="#categorias" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Categorías</a>
                <a href="#catalogo" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Repuestos</a>
                <a href="#beneficios" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Garantía</a>
                <a href="#contacto" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Ubicación</a>
            </nav>

            <!-- User / Login Action -->
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="btnAbrirCarritoWeb" class="btn btn-sm btn-outline-dark position-relative fw-semibold"
                        style="border-radius: 10px; padding: 7px 12px; font-size: 0.82rem;"
                        onclick="abrirModalCarritoWeb()">
                    <i class="bi bi-cart3 me-1"></i>Carrito
                    <span id="badgeCarritoWeb" class="badge rounded-pill bg-primary ms-1">0</span>
                </button>
                @auth
                    @if(auth()->user()->id_rol === 5)
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-none d-sm-inline fw-semibold text-dark" style="font-size: 0.88rem;">
                                <i class="bi bi-person-circle text-primary me-1"></i>Hola, {{ Str::words(auth()->user()->nombre_completo, 2, '') }}
                            </span>
                            <a href="{{ route('mis-cotizaciones.index') }}" class="btn-mac-primary btn-sm">
                                <i class="bi bi-receipt"></i>
                                <span>Mis Cotizaciones</span>
                            </a>
                            <form action="{{ route('logout') }}" method="POST" class="d-inline mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 10px; padding: 7px 12px; font-size: 0.82rem; font-weight: 600;">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span class="d-none d-md-inline ms-1">Cerrar Sesión</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-none d-md-inline fw-semibold text-dark" style="font-size: 0.88rem;">
                                <i class="bi bi-person-badge text-primary me-1"></i>Hola, {{ Str::words(auth()->user()->nombre_completo, 2, '') }} 
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.7rem;">{{ auth()->user()->rol->nombre_rol ?? 'Personal' }}</span>
                            </span>
                            <a href="{{ route('dashboard') }}" class="btn-mac-primary">
                                <i class="bi bi-speedometer2"></i>
                                <span>Panel Administrativo</span>
                            </a>
                            <form action="{{ route('logout') }}" method="POST" class="d-inline mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 10px; padding: 7px 12px; font-size: 0.82rem; font-weight: 600;" title="Cerrar Sesión">
                                    <i class="bi bi-box-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-mac-primary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Iniciar Sesión</span>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="container mt-3">
            <div class="alert alert-success d-flex align-items-center justify-content-between rounded-4 shadow-sm border-0 p-3" style="background:#ECFDF5; color:#065F46;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-5 text-success me-2"></i>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif
    @if(session('warning'))
        <div class="container mt-3">
            <div class="alert alert-warning d-flex align-items-center justify-content-between rounded-4 shadow-sm border-0 p-3" style="background:#FFFBEB; color:#92400E;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-5 text-warning me-2"></i>
                    <span>{{ session('warning') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif
