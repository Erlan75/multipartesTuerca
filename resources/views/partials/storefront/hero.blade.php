    <!-- ── Hero Section & Commercial Search ── -->
    <section class="hero-section">
        <div class="container text-center">
            <!-- Badge -->
            <div class="hero-badge">
                <span class="pulse-dot"></span>
                <span>Distribución Directa en Santa Cruz · Marcas Japonesas y Coreanas</span>
            </div>

            <!-- Title & Subtitle -->
            <h1 class="hero-title">
                Encuentra el repuesto exacto para tu vehículo
            </h1>
            <p class="hero-subtitle">
                Especialistas en repuestos originales y alternativos de alta durabilidad para vehículos japoneses y coreanos en Santa Cruz de la Sierra. Búsqueda técnica por código OEM, fabricante y modelo vehicular.
            </p>

            <!-- Search Form Card -->
            <div class="search-card">
                <form action="{{ route('home') }}#catalogo" method="GET" class="search-input-group">
                    <!-- Text Search: OEM, Part Name, Internal Code -->
                    <div class="search-input-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" 
                               name="buscar" 
                               value="{{ $buscar }}" 
                               placeholder="Buscar por código OEM (ej. 55810), nombre o código interno..." 
                               autocomplete="off">
                    </div>

                    <!-- Brand Filter Dropdown -->
                    <select name="marca" class="search-select">
                        <option value="">Todas las Marcas</option>
                        @foreach ($marcas as $m)
                            <option value="{{ $m->marca }}" {{ $marcaSeleccionada === $m->marca ? 'selected' : '' }}>
                                {{ $m->marca }} ({{ $m->modelos_count }} modelos)
                            </option>
                        @endforeach
                    </select>

                    <!-- Category Filter Dropdown -->
                    <select name="categoria_id" class="search-select">
                        <option value="">Todas las Categorías</option>
                        @foreach ($categorias as $c)
                            <option value="{{ $c->id_categoria }}" {{ (string)$categoriaId === (string)$c->id_categoria ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-search-hero">
                        <i class="bi bi-search"></i>
                        <span>Buscar Repuesto</span>
                    </button>
                </form>
            </div>

            <!-- Quick Search Tags -->
            <div class="quick-searches">
                <span>Búsquedas populares:</span>
                <a href="{{ route('home', ['buscar' => 'Pastillas']) }}#catalogo" class="quick-chip">Pastillas de Freno</a>
                <a href="{{ route('home', ['buscar' => 'Filtro']) }}#catalogo" class="quick-chip">Filtros</a>
                <a href="{{ route('home', ['buscar' => 'Amortiguador']) }}#catalogo" class="quick-chip">Amortiguadores</a>
                <a href="{{ route('home', ['buscar' => 'Bujía']) }}#catalogo" class="quick-chip">Bujías Iridium</a>
                <a href="{{ route('home', ['buscar' => 'Bomba']) }}#catalogo" class="quick-chip">Bombas</a>
                <a href="{{ route('home', ['buscar' => 'Embrague']) }}#catalogo" class="quick-chip">Embrague</a>
            </div>

            <!-- Trust / Stats Strip -->
            <div class="trust-strip">
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-box-seam-fill"></i></div>
                    <div class="text-start">
                        <p class="trust-title">{{ $totalProductos }} Repuestos Activos</p>
                        <p class="trust-desc">En inventario local Santa Cruz</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-car-front-fill"></i></div>
                    <div class="text-start">
                        <p class="trust-title">{{ $totalMarcas }} Marcas y {{ $totalModelos }} Modelos</p>
                        <p class="trust-desc">Cobertura japonesa y coreana</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-patch-check-fill text-success"></i></div>
                    <div class="text-start">
                        <p class="trust-title">100% Verificado por OEM</p>
                        <p class="trust-desc">Compatibilidad técnica exacta</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-truck text-primary"></i></div>
                    <div class="text-start">
                        <p class="trust-title">Entrega Inmediata</p>
                        <p class="trust-desc">Despacho en Santa Cruz y provincias</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
