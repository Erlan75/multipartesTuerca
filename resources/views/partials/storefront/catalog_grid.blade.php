    <!-- ── Sección 1: Selección por Marca de Vehículo ── -->
    <section id="marcas" class="py-5" style="background-color: #FFFFFF; border-bottom: 1px solid var(--mac-border);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 section-header">
                <div>
                    <div class="section-tag">Marcas Disponibles en Inventario</div>
                    <h2 class="section-title">Selecciona la marca de tu vehículo</h2>
                    <p class="section-subtitle">Filtra repuestos 100% compatibles con los modelos atendidos en Santa Cruz.</p>
                </div>
                @if ($marcaSeleccionada)
                    <a href="{{ route('home', array_filter(['categoria_id' => $categoriaId, 'buscar' => $buscar])) }}#marcas" 
                       class="btn-mac-outline btn-sm">
                        <i class="bi bi-x-circle text-danger"></i> Limpiar filtro de marca
                    </a>
                @endif
            </div>

            <!-- Grid of 9 Vehicle Brands -->
            <div class="brand-grid">
                @php
                    $brandLogos = [
                        'Toyota' => [
                            'origin' => 'Japón',
                            'color' => '#EB0A1E',
                            'svg' => '<svg viewBox="0 0 100 70" width="38" height="28"><ellipse cx="50" cy="35" rx="46" ry="32" fill="none" stroke="#EB0A1E" stroke-width="5"/><ellipse cx="50" cy="27" rx="30" ry="14" fill="none" stroke="#EB0A1E" stroke-width="5"/><ellipse cx="50" cy="35" rx="14" ry="29" fill="none" stroke="#EB0A1E" stroke-width="5"/></svg>'
                        ],
                        'Nissan' => [
                            'origin' => 'Japón',
                            'color' => '#C3002F',
                            'svg' => '<svg viewBox="0 0 100 80" width="38" height="30"><circle cx="50" cy="40" r="32" fill="none" stroke="#1E293B" stroke-width="5"/><rect x="8" y="32" width="84" height="16" rx="3" fill="#FFFFFF" stroke="#C3002F" stroke-width="3"/><text x="50" y="44" font-family="Inter,sans-serif" font-weight="800" font-size="11" text-anchor="middle" fill="#1E293B" letter-spacing="1">NISSAN</text></svg>'
                        ],
                        'Suzuki' => [
                            'origin' => 'Japón',
                            'color' => '#E2231A',
                            'svg' => '<svg viewBox="0 0 80 80" width="34" height="34"><path d="M 64,16 L 30,16 L 16,32 L 48,32 L 32,48 L 64,48 L 50,64 L 16,64 L 32,48 L 16,48 L 30,32 L 16,32 Z" fill="#E2231A"/></svg>'
                        ],
                        'Mazda' => [
                            'origin' => 'Japón',
                            'color' => '#102B7B',
                            'svg' => '<svg viewBox="0 0 100 70" width="38" height="28"><ellipse cx="50" cy="35" rx="44" ry="30" fill="none" stroke="#1E293B" stroke-width="4"/><path d="M 22,25 Q 50,48 78,25 Q 50,38 22,25 Z" fill="#102B7B"/><path d="M 38,32 Q 50,15 62,32 Q 50,24 38,32 Z" fill="#102B7B"/></svg>'
                        ],
                        'Mitsubishi' => [
                            'origin' => 'Japón',
                            'color' => '#ED1C24',
                            'svg' => '<svg viewBox="0 0 100 90" width="36" height="32"><polygon points="50,10 65,36 35,36" fill="#ED1C24"/><polygon points="35,36 6,86 36,86 50,61" fill="#ED1C24"/><polygon points="65,36 94,86 64,86 50,61" fill="#ED1C24"/></svg>'
                        ],
                        'Honda' => [
                            'origin' => 'Japón',
                            'color' => '#E40521',
                            'svg' => '<svg viewBox="0 0 90 80" width="36" height="32"><rect x="5" y="5" width="80" height="70" rx="12" fill="none" stroke="#E40521" stroke-width="5"/><path d="M 24,18 L 32,62 L 40,62 L 40,42 L 50,42 L 50,62 L 58,62 L 66,18 L 56,18 L 53,35 L 37,35 L 34,18 Z" fill="#E40521"/></svg>'
                        ],
                        'Hyundai' => [
                            'origin' => 'Corea',
                            'color' => '#002C6C',
                            'svg' => '<svg viewBox="0 0 100 70" width="38" height="28"><ellipse cx="50" cy="35" rx="44" ry="28" fill="none" stroke="#002C6C" stroke-width="5" transform="rotate(-6 50 35)"/><path d="M 35,22 L 42,48 L 48,48 L 48,34 Q 56,33 60,34 L 56,48 L 64,48 L 71,22 L 64,22 L 60,30 Q 56,29 48,30 L 42,22 Z" fill="#002C6C"/></svg>'
                        ],
                        'Kia' => [
                            'origin' => 'Corea',
                            'color' => '#05141F',
                            'svg' => '<svg viewBox="0 0 100 50" width="40" height="24"><text x="50" y="36" font-family="Inter,sans-serif" font-weight="900" font-size="28" text-anchor="middle" fill="#05141F" letter-spacing="2">KIA</text></svg>'
                        ],
                        'Isuzu' => [
                            'origin' => 'Japón',
                            'color' => '#E31B23',
                            'svg' => '<svg viewBox="0 0 100 50" width="40" height="24"><text x="50" y="34" font-family="Inter,sans-serif" font-weight="900" font-size="20" text-anchor="middle" fill="#E31B23" letter-spacing="1">ISUZU</text></svg>'
                        ],
                    ];
                @endphp

                @foreach ($marcas as $m)
                    @php
                        $isActive = ($marcaSeleccionada === $m->marca);
                        $brandInfo = $brandLogos[$m->marca] ?? [
                            'origin' => 'Importado',
                            'color' => '#007AFF',
                            'svg' => '<i class="bi bi-car-front text-primary fs-2"></i>'
                        ];
                        // Params preserving search and category
                        $brandParams = array_filter([
                            'marca' => $isActive ? null : $m->marca,
                            'categoria_id' => $categoriaId,
                            'buscar' => $buscar
                        ]);
                    @endphp

                    <a href="{{ route('home', $brandParams) }}#marcas" 
                       class="brand-card {{ $isActive ? 'active' : '' }}"
                       title="Ver repuestos para {{ $m->marca }}">
                        <div class="brand-logo-wrap">
                            {!! $brandInfo['svg'] !!}
                        </div>
                        <div class="brand-name">{{ $m->marca }}</div>
                        <div class="brand-count">{{ $m->modelos_count }} mod. · {{ $m->productos_count }} pzs</div>
                    </a>
                @endforeach
            </div>

            <!-- Model Sub-filter if a Brand is Selected -->
            @if ($marcaSeleccionada && $modelos->isNotEmpty())
                <div class="model-filter-bar">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-funnel-fill text-primary"></i>
                        <span class="fw-bold" style="font-size: 0.84rem; color: var(--mac-title);">
                            Modelos {{ $marcaSeleccionada }}:
                        </span>
                    </div>

                    <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada, 'categoria_id' => $categoriaId, 'buscar' => $buscar])) }}#catalogo" 
                       class="model-chip {{ empty($modeloSeleccionado) ? 'active' : '' }}">
                        Todos los {{ $marcaSeleccionada }}
                    </a>

                    @foreach ($modelos as $mod)
                        <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada, 'modelo' => $mod, 'categoria_id' => $categoriaId, 'buscar' => $buscar])) }}#catalogo" 
                           class="model-chip {{ $modeloSeleccionado === $mod ? 'active' : '' }}">
                            {{ $mod }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- ── Sección 2: Categorías de Repuestos ── -->
    <section id="categorias" class="py-5" style="background-color: var(--mac-bg);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 section-header">
                <div>
                    <div class="section-tag">Sistemas y Ensambles</div>
                    <h2 class="section-title">Categorías de Repuestos</h2>
                    <p class="section-subtitle">
                        @if ($marcaSeleccionada)
                            Mostrando disponibilidad de repuestos para <strong>{{ $marcaSeleccionada }}</strong>
                        @else
                            Explora repuestos organizados por sistema mecánico y eléctrico
                        @endif
                    </p>
                </div>
                @if ($categoriaId)
                    <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada, 'modelo' => $modeloSeleccionado, 'buscar' => $buscar])) }}#categorias" 
                       class="btn-mac-outline btn-sm">
                        <i class="bi bi-x-circle text-danger"></i> Quitar filtro de categoría
                    </a>
                @endif
            </div>

            @php
                $catMeta = [
                    'Frenos' => ['icon' => 'bi-disc', 'bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#DC2626'],
                    'Suspensión y Dirección' => ['icon' => 'bi-gear-wide-connected', 'bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#D97706'],
                    'Motor e Inyección' => ['icon' => 'bi-fuel-pump', 'bg' => 'rgba(0, 122, 255, 0.1)', 'color' => '#007AFF'],
                    'Transmisión y Embrague' => ['icon' => 'bi-speedometer2', 'bg' => 'rgba(99, 102, 241, 0.1)', 'color' => '#4F46E5'],
                    'Sistema Eléctrico e Iluminación' => ['icon' => 'bi-lightning-charge-fill', 'bg' => 'rgba(234, 179, 8, 0.12)', 'color' => '#CA8A04'],
                    'Refrigeración' => ['icon' => 'bi-snow', 'bg' => 'rgba(6, 182, 212, 0.12)', 'color' => '#0891B2'],
                ];
            @endphp

            <div class="row g-3">
                @foreach ($categorias as $cat)
                    @php
                        $isCatActive = ((string)$categoriaId === (string)$cat->id_categoria);
                        $meta = $catMeta[$cat->nombre] ?? ['icon' => 'bi-box-seam', 'bg' => 'rgba(0, 122, 255, 0.1)', 'color' => '#007AFF'];
                        $catParams = array_filter([
                            'categoria_id' => $isCatActive ? null : $cat->id_categoria,
                            'marca' => $marcaSeleccionada,
                            'modelo' => $modeloSeleccionado,
                            'buscar' => $buscar
                        ]);
                    @endphp
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="{{ route('home', $catParams) }}#catalogo" 
                           class="category-card {{ $isCatActive ? 'active' : '' }}">
                            <div class="category-icon-box" style="background: {{ $meta['bg'] }}; color: {{ $meta['color'] }};">
                                <i class="bi {{ $meta['icon'] }}"></i>
                            </div>
                            <div class="category-card-body flex-grow-1">
                                <h4>{{ $cat->nombre }}</h4>
                                <p>
                                    <strong>{{ $cat->productos_count }}</strong> repuestos 
                                    @if ($marcaSeleccionada)
                                        para {{ $marcaSeleccionada }}
                                    @else
                                        disponibles
                                    @endif
                                </p>
                            </div>
                            <i class="bi bi-chevron-right text-muted" style="font-size: 0.9rem;"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ── Sección 3: Listado de Repuestos Destacados / Catálogo ── -->
    <section id="catalogo" class="py-5" style="background-color: #FFFFFF; border-top: 1px solid var(--mac-border); border-bottom: 1px solid var(--mac-border);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 section-header">
                <div>
                    <div class="section-tag">Vitrina Comercial Activa</div>
                    <h2 class="section-title">Repuestos en Stock</h2>
                    <p class="section-subtitle">
                        Listado de repuestos disponibles en nuestro almacén de Santa Cruz para despacho inmediato.
                    </p>
                </div>
                <div class="text-muted" style="font-size: 0.85rem; font-weight: 500;">
                    Mostrando <strong>{{ $productos->total() }}</strong> repuestos encontrados
                </div>
            </div>

            <!-- Active Filters Bar (if any filter is on) -->
            @if ($buscar || $marcaSeleccionada || $modeloSeleccionado || $categoriaId)
                <div class="active-filters-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted fw-bold" style="font-size: 0.76rem; text-transform: uppercase;">Filtros activos:</span>

                        @if ($buscar)
                            <span class="filter-tag">
                                Búsqueda: "{{ $buscar }}"
                                <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada, 'modelo' => $modeloSeleccionado, 'categoria_id' => $categoriaId])) }}#catalogo">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        @endif

                        @if ($marcaSeleccionada)
                            <span class="filter-tag">
                                Marca: {{ $marcaSeleccionada }}
                                <a href="{{ route('home', array_filter(['buscar' => $buscar, 'categoria_id' => $categoriaId])) }}#catalogo">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        @endif

                        @if ($modeloSeleccionado)
                            <span class="filter-tag">
                                Modelo: {{ $modeloSeleccionado }}
                                <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada, 'buscar' => $buscar, 'categoria_id' => $categoriaId])) }}#catalogo">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        @endif

                        @if ($categoriaSeleccionada)
                            <span class="filter-tag">
                                Categoría: {{ $categoriaSeleccionada->nombre }}
                                <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada, 'modelo' => $modeloSeleccionado, 'buscar' => $buscar])) }}#catalogo">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        @endif
                    </div>

                    <a href="{{ route('home') }}#catalogo" class="btn btn-sm btn-outline-secondary" style="font-size: 0.75rem; border-radius: 8px;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restablecer filtros
                    </a>
                </div>
            @endif

            <!-- Products Grid -->
            @if ($productos->count() > 0)
                <div class="row g-4">
                    @foreach ($productos as $p)
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <div class="product-card">
                                <div>
                                    <!-- Header: Category & Manufacturer -->
                                    <div class="product-header">
                                        <span class="product-cat-badge">
                                            {{ $p->categoria ? $p->categoria->nombre : 'Repuesto' }}
                                        </span>
                                        <span class="product-maker-badge">
                                            {{ $p->marca_fabricante }}
                                        </span>
                                    </div>

                                    <!-- Part Name -->
                                    <h3 class="product-name" title="{{ $p->nombre_pieza }}">
                                        {{ $p->nombre_pieza }}
                                    </h3>

                                    <!-- Codes Row -->
                                    <div class="product-codes-row">
                                        <span class="code-pill code-pill-oem" title="Código OEM Original">
                                            <i class="bi bi-upc-scan"></i> OEM: {{ $p->codigo_oem }}
                                        </span>
                                        <span class="code-pill" title="Código Interno de Inventario">
                                            SKU: {{ $p->codigo_interno }}
                                        </span>
                                    </div>

                                    <!-- Stock & Location Status -->
                                    <div class="product-stock-status">
                                        <span class="status-dot"></span>
                                        <span class="status-stock-ok">
                                            Disponible en Santa Cruz
                                        </span>
                                        @if ($p->pasillo && $p->estante)
                                            <span class="text-muted" style="font-size: 0.7rem; font-weight: normal;">
                                                (Ubic: {{ $p->pasillo }}-{{ $p->estante }})
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Compatible Vehicles Snippet -->
                                    <div class="product-compatibility-snippet">
                                        <div class="comp-title">
                                            <i class="bi bi-car-front me-1"></i> Vehículos compatibles:
                                        </div>
                                        @if ($p->vehiculos->count() > 0)
                                            <div class="text-truncate" style="font-weight: 500;">
                                                @foreach ($p->vehiculos->take(2) as $veh)
                                                    <span class="badge bg-white text-dark border me-1" style="font-weight: 500; font-size: 0.7rem;">
                                                        {{ $veh->marca }} {{ $veh->modelo }}
                                                    </span>
                                                @endforeach
                                                @if ($p->vehiculos->count() > 2)
                                                    <span class="badge bg-light text-secondary" style="font-size: 0.68rem;">
                                                        +{{ $p->vehiculos->count() - 2 }} más
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted" style="font-size: 0.72rem;">Aplicación universal o a verificar</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Footer: Price & Compatibility Button -->
                                <div>
                                    <div class="product-price-row">
                                        <div>
                                            <div class="price-label">Precio Sugerido</div>
                                            <div class="price-val">Bs. {{ number_format($p->precio_venta_sugerido, 2) }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-light text-secondary" style="font-size: 0.7rem;">
                                                Stock: {{ $p->stock_actual }} {{ $p->unidad_medida ?? 'uds' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Trigger Modal Button -->
                                    <div class="d-grid gap-2 mt-2">
                                        <button type="button" 
                                                class="btn-check-compat mt-0" 
                                                onclick='verDetalleCompatibilidad(@json($p))'>
                                            <i class="bi bi-patch-check"></i>
                                            <span>Consultar compatibilidad</span>
                                        </button>
                                        @if((int)$p->stock_actual > 0)
                                        @php
                                            $itemCarritoWeb = [
                                                'id_producto'  => (int) $p->id_producto,
                                                'nombre_pieza' => (string) $p->nombre_pieza,
                                                'codigo_oem'   => (string) ($p->codigo_oem ?? ''),
                                                'precio'       => (float) $p->precio_venta_sugerido,
                                                'stock'        => (int) $p->stock_actual,
                                            ];
                                        @endphp
                                        <button type="button"
                                                onclick="agregarAlCarritoWeb({{ \Illuminate\Support\Js::from($itemCarritoWeb) }})"
                                                class="btn btn-sm btn-success fw-semibold d-flex align-items-center justify-content-center gap-1"
                                                style="border-radius: 10px; padding: 7px 12px; font-size: 0.78rem; border: none;">
                                            <i class="bi bi-cart-plus-fill"></i>
                                            <span>Agregar al carrito</span>
                                        </button>
                                        @endif
                                        <a href="{{ route('mis-cotizaciones.index', ['id_producto' => $p->id_producto]) }}"
                                           class="btn btn-sm btn-primary fw-semibold d-flex align-items-center justify-content-center gap-1"
                                           style="border-radius: 10px; padding: 7px 12px; font-size: 0.78rem; background: linear-gradient(135deg, #007AFF, #0055D4); border: none;">
                                            <i class="bi bi-file-earmark-plus"></i>
                                            <span>Cotizar este repuesto</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $productos->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-5">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--mac-bg); color: var(--mac-subtle); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 16px;">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3 class="fw-bold text-dark" style="font-size: 1.15rem;">No se encontraron repuestos con los filtros seleccionados</h3>
                    <p class="text-muted" style="max-width: 480px; margin: 0 auto 20px; font-size: 0.88rem;">
                        Intenta con otro término de búsqueda, o consulta directamente con nuestro equipo de almacén en Santa Cruz por WhatsApp.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="{{ route('home') }}#catalogo" class="btn-mac-outline">
                            Ver todo el catálogo
                        </a>
                        <a href="https://wa.me/59170012345?text={{ urlencode('Hola Multipartes El Tuerca, busco un repuesto que no encontré en el catálogo web: ' . ($buscar ?? '')) }}" 
                           target="_blank" 
                           class="btn-mac-primary" 
                           style="background: #25D366; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);">
                            <i class="bi bi-whatsapp"></i> Consultar a un Asesor
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- ── Sección de Beneficios & Confianza Comercial ── -->
    <section id="beneficios" class="py-5" style="background-color: var(--mac-bg);">
        <div class="container">
            <div class="text-center max-w-700 mx-auto mb-5">
                <div class="section-tag">Garantía y Respaldo</div>
                <h2 class="section-title">¿Por qué confiar en Multipartes El Tuerca?</h2>
                <p class="section-subtitle">
                    Más de una década brindando soluciones confiables a talleres mecánicos y propietarios en Santa Cruz de la Sierra.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card h-100 p-4 border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="trust-icon mb-3" style="width: 48px; height: 48px; font-size: 1.4rem;">
                            <i class="bi bi-shield-shaded text-primary"></i>
                        </div>
                        <h4 class="fw-bold fs-6 text-dark mb-2">Repuestos Certificados</h4>
                        <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                            Trabajamos con fabricantes líderes internacionales: Denso, Akebono, KYB, 555 Three Five, NGK y Monroe.
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card h-100 p-4 border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="trust-icon mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background: rgba(52, 199, 89, 0.12); color: #34C759;">
                            <i class="bi bi-upc-scan"></i>
                        </div>
                        <h4 class="fw-bold fs-6 text-dark mb-2">Cruce por Código OEM</h4>
                        <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                            Garantizamos cero error de compra mediante validación de número de parte original y código de chasis (VIN).
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card h-100 p-4 border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="trust-icon mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background: rgba(255, 149, 0, 0.12); color: #FF9500;">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <h4 class="fw-bold fs-6 text-dark mb-2">Almacén en Santa Cruz</h4>
                        <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                            Disponibilidad física inmediata en nuestro local. Retiro directo o envíos el mismo día a talleres de la ciudad.
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card h-100 p-4 border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="trust-icon mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background: rgba(99, 102, 241, 0.12); color: #4F46E5;">
                            <i class="bi bi-chat-dots-fill"></i>
                        </div>
                        <h4 class="fw-bold fs-6 text-dark mb-2">Asesoría Mecánica</h4>
                        <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                            Personal capacitado para guiarte en medidas, especificaciones de motor y compatibilidad entre generaciones.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
