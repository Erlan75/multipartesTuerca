<!-- Hero Above-the-Fold (Dark Mode Premium, estático y orientado a búsqueda inmediata) -->
<section class="cz-hero">
    <div class="cz-container">
        <div class="cz-hero-split">
            <div class="cz-hero-copy">
                <!-- Etiqueta superior de respaldo físico -->
                <div class="cz-eyebrow">
                    <span class="cz-eyebrow-dot"></span>
                    <span>CASA MATRIZ SANTA CRUZ &bull; STOCK REAL EN TIENDA</span>
                </div>

                <!-- Titular concreto (enfocado en el beneficio) -->
                <h1 class="cz-hero-title">
                    Repuestos japoneses y coreanos <span>listos para tu vehículo</span>
                </h1>

                <!-- Subtítulo directo -->
                <p class="cz-hero-subtitle">
                    Busca por código OEM, nombre de pieza o modelo de auto. Compra directa o genera tu proforma online en segundos.
                </p>

                <!-- Buscador Inteligente con Autocompletado -->
                <div class="cz-search-shell">
                    <form action="{{ route('home') }}#catalogo" method="GET" class="cz-search-form" id="czHeroSearchForm">
                        <div class="cz-search-field">
                            <i class="bi bi-search"></i>
                            <input type="text"
                                   id="czHeroSearchInput"
                                   name="buscar"
                                   value="{{ $buscar }}"
                                   class="cz-search-input"
                                   placeholder="Ej. 04465-02220, pastillas Corolla, filtro Hilux, amortiguador..."
                                   autocomplete="off"
                                   aria-label="Buscar repuesto por código OEM, nombre o vehículo">
                        </div>
                        <button type="submit" class="cz-btn-emerald flex-shrink-0">
                            <i class="bi bi-search"></i>
                            <span>Buscar</span>
                        </button>
                    </form>

                    <!-- Desplegable de sugerencias instantáneas -->
                    <div id="czAutocompletePanel" class="cz-autocomplete-panel" role="listbox" aria-label="Sugerencias de repuestos"></div>
                </div>

                <!-- CTA principal + enlace secundario discreto -->
                <div class="cz-hero-ctas">
                    <a href="#catalogo" class="cz-btn-emerald">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                        <span>Explorar Catálogo</span>
                    </a>
                    <a href="{{ auth()->check() ? route('mis-cotizaciones.index') : route('register') }}" class="cz-hero-secondary-link">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Cotizar para taller</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Visual de Estudio OEM en Dark Mode -->
            <div class="d-none d-lg-block">
                <div class="cz-hero-visual-card">
                    <img src="{{ asset('images/repuestos/hero_autopartes.jpg') }}"
                         alt="Repuestos automotrices originales y certificados en Multipartes El Tuerca"
                         class="cz-hero-visual-img">
                    <div class="cz-hero-visual-badge">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-patch-check-fill text-success fs-5"></i>
                            <div>
                                <div class="fw-bold text-white" style="line-height: 1.2;">Stock OEM Verificado</div>
                                <div style="font-size: 0.72rem; color: var(--cz-slate-300);">Toyota &middot; Suzuki &middot; Nissan &middot; Hyundai &middot; Kia</div>
                            </div>
                        </div>
                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 font-mono-cz">
                            2do Anillo SCZ
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logo Cloud + Count-up Stats -->
        <div class="cz-hero-proof text-center">
            <div class="cz-logo-cloud-label">Marcas vehiculares y fabricantes certificados en inventario</div>
            <div class="cz-logo-cloud" aria-label="Marcas disponibles">
                @php
                    $marcasLogoCloud = [
                        ['label' => 'Toyota', 'param' => ['marca' => 'Toyota']],
                        ['label' => 'Suzuki', 'param' => ['marca' => 'Suzuki']],
                        ['label' => 'Nissan', 'param' => ['marca' => 'Nissan']],
                        ['label' => 'Hyundai', 'param' => ['marca' => 'Hyundai']],
                        ['label' => 'Kia', 'param' => ['marca' => 'Kia']],
                        ['label' => 'Mitsubishi', 'param' => ['marca' => 'Mitsubishi']],
                        ['label' => 'Mazda', 'param' => ['marca' => 'Mazda']],
                        ['label' => 'Bosch', 'param' => ['buscar' => 'Bosch']],
                        ['label' => 'Denso', 'param' => ['buscar' => 'Denso']],
                        ['label' => 'KYB', 'param' => ['buscar' => 'KYB']],
                    ];
                @endphp
                @foreach($marcasLogoCloud as $logoItem)
                    <a href="{{ route('home', $logoItem['param']) }}#catalogo" class="cz-brand-wordmark">
                        {{ $logoItem['label'] }}
                    </a>
                @endforeach
            </div>

            <!-- 3 Métricas Animadas (Count-up al entrar en viewport) -->
            <div class="cz-stats-grid" id="czStatsSection">
                <div class="cz-stat-card">
                    <div class="cz-stat-icon">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <div>
                        <div class="cz-stat-value">
                            <span class="cz-countup" data-target="{{ $totalProductos }}">0</span>+
                        </div>
                        <div class="cz-stat-label">
                            Repuestos activos ({{ number_format($totalUnidadesStock) }} uds en stock real)
                        </div>
                    </div>
                </div>

                <div class="cz-stat-card">
                    <div class="cz-stat-icon is-amber">
                        <i class="bi bi-car-front-fill"></i>
                    </div>
                    <div>
                        <div class="cz-stat-value">
                            <span class="cz-countup" data-target="{{ $totalMarcas }}">0</span> marcas &middot; <span class="cz-countup" data-target="{{ $totalModelos }}">0</span>
                        </div>
                        <div class="cz-stat-label">
                            Modelos japoneses y coreanos verificados
                        </div>
                    </div>
                </div>

                <div class="cz-stat-card">
                    <div class="cz-stat-icon">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <div class="cz-stat-value">
                            <span class="cz-countup" data-target="15">0</span> min
                        </div>
                        <div class="cz-stat-label">
                            Retiro inmediato en Av. Cristóbal de Mendoza #450 (2do Anillo)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
