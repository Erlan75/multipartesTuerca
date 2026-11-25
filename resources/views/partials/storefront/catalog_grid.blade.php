<!-- Catálogo Comercial: Filter Chips + Filtros Combinables + Product Cards -->
<section id="catalogo" class="cz-section cz-catalog-section">
    <div class="cz-container">
        <!-- Cabecera de sección -->
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
            <div>
                <div class="cz-section-eyebrow">Inventario Verificado en Santa Cruz</div>
                <h2 class="cz-section-title">Catálogo de Repuestos</h2>
                <p class="cz-section-sub">
                    Filtra por categoría, vehículo compatible o disponibilidad inmediata y agrega directo a tu carrito.
                </p>
            </div>
            <div class="font-mono-cz fw-bold" style="font-size: 0.85rem; color: var(--cz-ink);">
                {{ $productos->total() }} {{ $productos->total() === 1 ? 'repuesto encontrado' : 'repuestos encontrados' }}
            </div>
        </div>

        <!-- Filter Chips Horizontales Deslizables por Categoría -->
        <div class="cz-chips-track" role="navigation" aria-label="Filtrar por categoría">
            @php
                $baseSinCat = array_filter([
                    'buscar'      => $buscar ?: null,
                    'marca'       => $marcaSeleccionada ?: null,
                    'modelo'      => $modeloSeleccionado ?: null,
                    'vehiculo_id' => $vehiculoId ?: null,
                    'en_stock'    => $soloStock ? 1 : null,
                ]);
            @endphp
            <a href="{{ route('home', $baseSinCat) }}#catalogo"
               class="cz-chip {{ empty($categoriaId) ? 'is-active' : '' }}">
                <i class="bi bi-grid-fill"></i>
                <span>Todas</span>
                <span class="cz-chip-count">{{ $totalProductos }}</span>
            </a>

            @foreach($categorias as $cat)
                @php
                    $isCatActive = ((int) $categoriaId === (int) $cat->id_categoria);
                    $catParams = array_filter([
                        'categoria_id' => $isCatActive ? null : $cat->id_categoria,
                        'buscar'       => $buscar ?: null,
                        'marca'        => $marcaSeleccionada ?: null,
                        'modelo'       => $modeloSeleccionado ?: null,
                        'vehiculo_id'  => $vehiculoId ?: null,
                        'en_stock'     => $soloStock ? 1 : null,
                    ]);
                @endphp
                <a href="{{ route('home', $catParams) }}#catalogo"
                   class="cz-chip {{ $isCatActive ? 'is-active' : '' }}">
                    <span>{{ $cat->nombre }}</span>
                    <span class="cz-chip-count">{{ $cat->productos_count }}</span>
                </a>
            @endforeach
        </div>

        <!-- Barra de Filtros Facetados Combinables (Vehículo + Marca + Solo en Stock) -->
        <form action="{{ route('home') }}#catalogo" method="GET" class="cz-faceted-bar" id="czFacetedForm">
            @if($categoriaId)
                <input type="hidden" name="categoria_id" value="{{ $categoriaId }}">
            @endif
            @if($buscar !== '')
                <input type="hidden" name="buscar" value="{{ $buscar }}">
            @endif

            <div class="row g-3 align-items-center">
                <!-- Filtro: Vehículo Compatible -->
                <div class="col-12 col-md-5 col-lg-5">
                    <label for="czFilterVehiculo" class="form-label mb-1 fw-bold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--cz-muted);">
                        <i class="bi bi-car-front-fill me-1"></i>Vehículo Compatible
                    </label>
                    <select id="czFilterVehiculo"
                            name="vehiculo_id"
                            class="form-select cz-faceted-select"
                            onchange="document.getElementById('czFacetedForm').submit()">
                        <option value="">Todos los vehículos ({{ $totalModelos }} modelos)</option>
                        @foreach($vehiculos as $v)
                            <option value="{{ $v->id_vehiculo }}" {{ (int) $vehiculoId === (int) $v->id_vehiculo ? 'selected' : '' }}>
                                {{ $v->marca }} {{ $v->modelo }} ({{ $v->anio_inicio }}–{{ $v->anio_fin }})@if($v->motor_cilindrada) · {{ $v->motor_cilindrada }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filtro: Marca Vehicular -->
                <div class="col-12 col-sm-6 col-md-3 col-lg-3">
                    <label for="czFilterMarca" class="form-label mb-1 fw-bold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--cz-muted);">
                        <i class="bi bi-tag-fill me-1"></i>Marca
                    </label>
                    <select id="czFilterMarca"
                            name="marca"
                            class="form-select cz-faceted-select"
                            onchange="document.getElementById('czFacetedForm').submit()">
                        <option value="">Todas las marcas</option>
                        @foreach($marcas as $m)
                            <option value="{{ $m->marca }}" {{ $marcaSeleccionada === $m->marca ? 'selected' : '' }}>
                                {{ $m->marca }} ({{ $m->productos_count }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filtro: Solo disponibles en stock -->
                <div class="col-12 col-sm-6 col-md-4 col-lg-4">
                    <span class="d-block mb-1 fw-bold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--cz-muted);">
                        Disponibilidad
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <label class="cz-stock-toggle flex-grow-1 mb-0">
                            <input type="checkbox"
                                   name="en_stock"
                                   value="1"
                                   {{ $soloStock ? 'checked' : '' }}
                                   onchange="document.getElementById('czFacetedForm').submit()">
                            <span>Solo disponibles en stock</span>
                        </label>
                    </div>
                </div>
            </div>
        </form>

        <!-- Chips Removibles de Filtros Activos -->
        @if($buscar !== '' || $marcaSeleccionada !== '' || $modeloSeleccionado !== '' || $categoriaSeleccionada || $vehiculoSeleccionado || $soloStock)
            <div class="cz-active-pills">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold" style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.06em; color: #065F46;">
                        Filtros activos:
                    </span>

                    @if($buscar !== '')
                        <span class="cz-removable-pill">
                            <span>Búsqueda: "{{ $buscar }}"</span>
                            <a href="{{ route('home', array_filter(['marca' => $marcaSeleccionada ?: null, 'modelo' => $modeloSeleccionado ?: null, 'categoria_id' => $categoriaId, 'vehiculo_id' => $vehiculoId, 'en_stock' => $soloStock ? 1 : null])) }}#catalogo" aria-label="Quitar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </span>
                    @endif

                    @if($categoriaSeleccionada)
                        <span class="cz-removable-pill">
                            <span>Categoría: {{ $categoriaSeleccionada->nombre }}</span>
                            <a href="{{ route('home', array_filter(['buscar' => $buscar ?: null, 'marca' => $marcaSeleccionada ?: null, 'modelo' => $modeloSeleccionado ?: null, 'vehiculo_id' => $vehiculoId, 'en_stock' => $soloStock ? 1 : null])) }}#catalogo" aria-label="Quitar categoría">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </span>
                    @endif

                    @if($vehiculoSeleccionado)
                        <span class="cz-removable-pill">
                            <span>Vehículo: {{ $vehiculoSeleccionado->marca }} {{ $vehiculoSeleccionado->modelo }} ({{ $vehiculoSeleccionado->anio_inicio }}–{{ $vehiculoSeleccionado->anio_fin }})</span>
                            <a href="{{ route('home', array_filter(['buscar' => $buscar ?: null, 'marca' => $marcaSeleccionada ?: null, 'categoria_id' => $categoriaId, 'en_stock' => $soloStock ? 1 : null])) }}#catalogo" aria-label="Quitar vehículo">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </span>
                    @endif

                    @if($marcaSeleccionada !== '')
                        <span class="cz-removable-pill">
                            <span>Marca: {{ $marcaSeleccionada }}</span>
                            <a href="{{ route('home', array_filter(['buscar' => $buscar ?: null, 'categoria_id' => $categoriaId, 'vehiculo_id' => $vehiculoId, 'en_stock' => $soloStock ? 1 : null])) }}#catalogo" aria-label="Quitar marca">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </span>
                    @endif

                    @if($soloStock)
                        <span class="cz-removable-pill">
                            <span>Solo en stock</span>
                            <a href="{{ route('home', array_filter(['buscar' => $buscar ?: null, 'marca' => $marcaSeleccionada ?: null, 'modelo' => $modeloSeleccionado ?: null, 'categoria_id' => $categoriaId, 'vehiculo_id' => $vehiculoId])) }}#catalogo" aria-label="Quitar filtro de stock">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </span>
                    @endif
                </div>

                <a href="{{ route('home') }}#catalogo" class="btn btn-sm btn-outline-dark fw-semibold" style="border-radius: 8px; min-height: 36px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Limpiar filtros</span>
                </a>
            </div>
        @endif

        <!-- Grilla de Product Cards (12 columnas responsivas) -->
        @if($productos->count() > 0)
            <div class="row g-4">
                @foreach($productos as $p)
                    @php
                        $stock = (int) $p->stock_actual;
                        $stockMin = (int) ($p->stock_minimo ?? 3);
                        if ($stock <= 0) {
                            $badgeClass = 'out-stock';
                            $badgeIcon  = 'bi-dash-circle-fill';
                            $badgeLabel = 'Agotado';
                        } elseif ($stock <= $stockMin) {
                            $badgeClass = 'low-stock';
                            $badgeIcon  = 'bi-exclamation-circle-fill';
                            $badgeLabel = "Últimas unidades: {$stock}";
                        } else {
                            $badgeClass = 'in-stock';
                            $badgeIcon  = 'bi-check-circle-fill';
                            $badgeLabel = "En stock: {$stock} uds";
                        }

                        $itemCarritoWeb = [
                            'id_producto'  => (int) $p->id_producto,
                            'nombre_pieza' => (string) $p->nombre_pieza,
                            'codigo_oem'   => (string) ($p->codigo_oem ?? ''),
                            'precio'       => (float) $p->precio_venta_sugerido,
                            'stock'        => $stock,
                        ];
                    @endphp
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <article class="cz-product-card">
                            <div>
                                <!-- Cabecera visual: Disponibilidad + Código OEM -->
                                <div class="cz-product-top">
                                    <span class="cz-stock-badge {{ $badgeClass }}">
                                        <i class="bi {{ $badgeIcon }}"></i>
                                        <span>{{ $badgeLabel }}</span>
                                    </span>
                                    <span class="cz-oem-pill" title="Código OEM Original">
                                        <i class="bi bi-upc-scan"></i>
                                        <span>{{ $p->codigo_oem }}</span>
                                    </span>
                                </div>

                                <!-- Categoría y Fabricante -->
                                <div class="cz-product-kicker">
                                    {{ $p->categoria ? $p->categoria->nombre : 'Repuesto' }} &middot; {{ $p->marca_fabricante ?: 'Original' }}
                                </div>

                                <!-- Nombre de la pieza -->
                                <h3 class="cz-product-title" title="{{ $p->nombre_pieza }}">
                                    {{ $p->nombre_pieza }}
                                </h3>

                                <!-- Vehículos compatibles (texto legible, alineado a la izquierda) -->
                                <div class="cz-product-compat">
                                    <div class="cz-product-compat-label">
                                        <i class="bi bi-car-front me-1"></i>Vehículos compatibles
                                    </div>
                                    @if($p->vehiculos->count() > 0)
                                        <div>
                                            {{ $p->vehiculos->take(2)->map(fn($v) => "{$v->marca} {$v->modelo} ({$v->anio_inicio}-{$v->anio_fin})")->implode(', ') }}
                                            @if($p->vehiculos->count() > 2)
                                                <span class="fw-bold text-dark">+{{ $p->vehiculos->count() - 2 }} más</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-muted">Aplicación universal o verificación por chasis</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Precio destacado en Bs. + Acciones táctiles -->
                            <div>
                                <div class="cz-product-price-box">
                                    <div>
                                        <div class="cz-price-unit">Precio en tienda</div>
                                        <div class="cz-price-amount">Bs. {{ number_format($p->precio_venta_sugerido, 2) }}</div>
                                    </div>
                                    <span class="font-mono-cz" style="font-size: 0.72rem; color: var(--cz-muted);">
                                        SKU {{ $p->codigo_interno }}
                                    </span>
                                </div>

                                <div class="cz-product-actions">
                                    @if($stock > 0)
                                        <button type="button"
                                                onclick="agregarAlCarritoWeb({{ \Illuminate\Support\Js::from($itemCarritoWeb) }})"
                                                class="cz-btn-emerald w-100">
                                            <i class="bi bi-cart-plus-fill"></i>
                                            <span>Agregar al carrito</span>
                                        </button>
                                    @else
                                        <a href="{{ route('mis-cotizaciones.index', ['id_producto' => $p->id_producto]) }}"
                                           class="cz-btn-dark w-100">
                                            <i class="bi bi-bell"></i>
                                            <span>Solicitar cotización</span>
                                        </a>
                                    @endif

                                    <button type="button"
                                            class="cz-btn-compat w-100"
                                            onclick="verDetalleCompatibilidad({{ \Illuminate\Support\Js::from($p) }})">
                                        <i class="bi bi-patch-check"></i>
                                        <span>Ver compatibilidad</span>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="mt-4">
                {{ $productos->links() }}
            </div>
        @else
            <!-- Empty State Amigable -->
            <div class="text-center py-5 px-4 rounded-4" style="background-color: var(--cz-canvas); border: 1px dashed var(--cz-border-strong);">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                     style="width: 64px; height: 64px; background-color: #FFFFFF; border: 1px solid var(--cz-border); color: var(--cz-muted); font-size: 1.75rem;">
                    <i class="bi bi-search"></i>
                </div>
                <h3 class="fw-bold mb-2" style="font-size: 1.25rem; color: var(--cz-ink);">
                    No encontramos repuestos con los filtros seleccionados
                </h3>
                <p class="mx-auto mb-4" style="max-width: 500px; color: var(--cz-body); font-size: 0.92rem;">
                    Prueba quitando algún filtro o envíanos el código OEM / foto de la pieza por WhatsApp para ubicarla en nuestro almacén de Santa Cruz.
                </p>
                <div class="d-flex justify-content-center flex-wrap gap-3">
                    <a href="{{ route('home') }}#catalogo" class="cz-btn-dark">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Limpiar filtros</span>
                    </a>
                    <a href="https://wa.me/59170012345?text={{ urlencode('Hola Multipartes El Tuerca, busco un repuesto que no encontré en el catálogo web: ' . ($buscar ?? '')) }}"
                       target="_blank"
                       class="cz-btn-emerald">
                        <i class="bi bi-whatsapp"></i>
                        <span>Consultar por WhatsApp</span>
                    </a>
                </div>
            </div>
        @endif
    </div>
</section>
