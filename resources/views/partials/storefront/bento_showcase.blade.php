<!-- Bento Grid Asimétrico de Categorías y Ventaja OEM -->
<section id="categorias" class="cz-section">
    <div class="cz-container">
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4 pb-2">
            <div>
                <div class="cz-section-eyebrow">Sistemas Automotrices</div>
                <h2 class="cz-section-title">Compra directo por sistema o categoría</h2>
                <p class="cz-section-sub">
                    Acceso rápido a las líneas de mayor demanda en talleres y propietarios de Santa Cruz.
                </p>
            </div>
            <a href="{{ route('home') }}#catalogo" class="cz-btn-outline">
                <i class="bi bi-sliders"></i>
                <span>Ver todas las categorías</span>
            </a>
        </div>

        @php
            $findCat = function (array $keywords) use ($categorias) {
                foreach ($categorias as $cat) {
                    $lower = mb_strtolower($cat->nombre);
                    foreach ($keywords as $kw) {
                        if (str_contains($lower, $kw)) {
                            return $cat;
                        }
                    }
                }
                return null;
            };

            $catFrenos     = $findCat(['freno']);
            $catEncendido  = $findCat(['encend', 'inyecc']);
            $catSuspension = $findCat(['suspensi', 'direcci']);
            $catFiltros    = $findCat(['filtro', 'lubric']);

            $bentoCards = [
                [
                    'span'  => 'span-4',
                    'title' => 'Frenos y Pastillas',
                    'desc'  => 'Pastillas cerámicas, discos ventilados y bombas Bosch, Akebono y Fremax.',
                    'image' => asset('images/repuestos/frenos.jpg'),
                    'url'   => $catFrenos ? route('home', ['categoria_id' => $catFrenos->id_categoria]) . '#catalogo' : route('home', ['buscar' => 'Freno']) . '#catalogo',
                    'count' => $catFrenos ? $catFrenos->productos_count . ' referencias' : 'Stock activo',
                ],
                [
                    'span'  => 'span-3',
                    'title' => 'Encendido e Inyección',
                    'desc'  => 'Bujías Iridium, bobinas Denso/NGK, inyectores y bombas de gasolina.',
                    'image' => asset('images/repuestos/encendido.jpg'),
                    'url'   => $catEncendido ? route('home', ['categoria_id' => $catEncendido->id_categoria]) . '#catalogo' : route('home', ['buscar' => 'Bujía']) . '#catalogo',
                    'count' => $catEncendido ? $catEncendido->productos_count . ' referencias' : 'Stock activo',
                ],
                [
                    'span'  => 'span-3',
                    'title' => 'Suspensión y Dirección',
                    'desc'  => 'Amortiguadores KYB/Monroe, muñones 555, terminales, bieletas y cremalleras.',
                    'image' => asset('images/repuestos/suspension.jpg'),
                    'url'   => $catSuspension ? route('home', ['categoria_id' => $catSuspension->id_categoria]) . '#catalogo' : route('home', ['buscar' => 'Amortiguador']) . '#catalogo',
                    'count' => $catSuspension ? $catSuspension->productos_count . ' referencias' : 'Stock activo',
                ],
                [
                    'span'  => 'span-4',
                    'title' => 'Filtros y Lubricantes',
                    'desc'  => 'Filtros Mann-Filter, Sakura y Toyota OEM junto a aceites 5W-30 Castrol y Mobil 1.',
                    'image' => asset('images/repuestos/filtros.jpg'),
                    'url'   => $catFiltros ? route('home', ['categoria_id' => $catFiltros->id_categoria]) . '#catalogo' : route('home', ['buscar' => 'Filtro']) . '#catalogo',
                    'count' => $catFiltros ? $catFiltros->productos_count . ' referencias' : 'Entrega inmediata',
                ],
            ];
        @endphp

        <div class="cz-bento-grid">
            <!-- Tarjeta Destacada Grande: Garantía OEM -->
            <div class="cz-bento-featured">
                <div>
                    <div class="cz-bento-featured-banner">
                        <img src="{{ asset('images/repuestos/hero_autopartes.jpg') }}"
                             alt="Garantía de compatibilidad OEM en Multipartes El Tuerca"
                             loading="lazy">
                    </div>
                    <span class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3"
                          style="background: rgba(16, 185, 129, 0.16); color: #6EE7B7; font-size: 0.74rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;">
                        <i class="bi bi-patch-check-fill"></i>
                        <span>Cero Errores de Compra</span>
                    </span>
                    <h3 class="fw-bold text-white mb-3" style="font-size: 1.55rem; letter-spacing: -0.025em; line-height: 1.2;">
                        Compatibilidad OEM Garantizada
                    </h3>
                    <p style="color: var(--cz-slate-300); font-size: 0.92rem; line-height: 1.6; margin-bottom: 20px;">
                        Verificamos cada pieza con el número de chasis (VIN) o código original antes de entregar. Sin devoluciones innecesarias ni tiempos muertos en tu taller.
                    </p>
                    <div class="d-flex flex-column gap-2" style="font-size: 0.85rem; color: #E2E8F0;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            <span>Cruce exacto de códigos OEM y referencias alternativas</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            <span>Precios transparentes en Bolivianos (Bs.) con stock real</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            <span>Descuentos preferenciales para talleres y flotas registradas</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                    <a href="#catalogo" class="cz-btn-emerald">
                        <i class="bi bi-upc-scan"></i>
                        <span>Validar en Catálogo</span>
                    </a>
                    <a href="https://wa.me/59170012345?text={{ urlencode('Hola Multipartes El Tuerca, necesito verificar la compatibilidad OEM de un repuesto.') }}"
                       target="_blank"
                       class="cz-btn-ghost-dark">
                        <i class="bi bi-whatsapp text-success"></i>
                        <span>Consultar con Chasis</span>
                    </a>
                </div>
            </div>

            <!-- 4 Tarjetas Bento de Acceso Rápido por Categoría -->
            @foreach($bentoCards as $card)
                <a href="{{ $card['url'] }}" class="cz-bento-card {{ $card['span'] }}">
                    <div>
                        <div class="cz-bento-thumb">
                            <img src="{{ $card['image'] }}" alt="{{ $card['title'] }}" loading="lazy">
                        </div>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['desc'] }}</p>
                    </div>
                    <div class="cz-bento-foot">
                        <span>{{ $card['count'] }}</span>
                        <span class="d-inline-flex align-items-center gap-1">
                            Filtrar <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
