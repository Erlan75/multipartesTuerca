    <!-- ── Footer Comercial & Ubicación ── -->
    <footer id="contacto" class="public-footer">
        <div class="container">
            <div class="row g-4 pb-4">
                <!-- Columna 1: Empresa -->
                <div class="col-12 col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="brand-logo-icon" style="width: 36px; height: 36px; font-size: 1.1rem;">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                        <div>
                            <div class="text-white fw-bold" style="font-size: 1rem; line-height: 1.2;">MULTIPARTES EL TUERCA</div>
                            <div class="text-secondary" style="font-size: 0.68rem; letter-spacing: 0.04em;">SANTA CRUZ DE LA SIERRA</div>
                        </div>
                    </div>
                    <p style="font-size: 0.84rem; line-height: 1.6; color: #94A3B8;">
                        Comercializadora y distribuidora de repuestos automotrices de precisión para marcas japonesas y coreanas. Calidad OEM, asesoramiento técnico y garantía garantizada.
                    </p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="https://wa.me/59170012345" target="_blank" class="btn btn-sm btn-success px-3" style="border-radius: 8px;">
                            <i class="bi bi-whatsapp me-1"></i> WhatsApp
                        </a>
                        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light px-3" style="border-radius: 8px;">
                            <i class="bi bi-lock me-1"></i> Ingreso Personal
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Marcas -->
                <div class="col-6 col-md-3 col-lg-2">
                    <h5 class="footer-heading">Marcas Populares</h5>
                    <ul class="footer-links">
                        <li><a href="{{ route('home', ['marca' => 'Toyota']) }}#catalogo">Toyota</a></li>
                        <li><a href="{{ route('home', ['marca' => 'Nissan']) }}#catalogo">Nissan</a></li>
                        <li><a href="{{ route('home', ['marca' => 'Suzuki']) }}#catalogo">Suzuki</a></li>
                        <li><a href="{{ route('home', ['marca' => 'Mazda']) }}#catalogo">Mazda</a></li>
                        <li><a href="{{ route('home', ['marca' => 'Mitsubishi']) }}#catalogo">Mitsubishi</a></li>
                        <li><a href="{{ route('home', ['marca' => 'Honda']) }}#catalogo">Honda</a></li>
                        <li><a href="{{ route('home', ['marca' => 'Hyundai']) }}#catalogo">Hyundai / Kia</a></li>
                    </ul>
                </div>

                <!-- Columna 3: Categorías -->
                <div class="col-6 col-md-3 col-lg-3">
                    <h5 class="footer-heading">Sistemas Automotrices</h5>
                    <ul class="footer-links">
                        @foreach ($categorias as $cat)
                            <li>
                                <a href="{{ route('home', ['categoria_id' => $cat->id_categoria]) }}#catalogo">
                                    {{ $cat->nombre }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Columna 4: Ubicación & Contacto -->
                <div class="col-12 col-md-6 col-lg-3">
                    <h5 class="footer-heading">Atención y Local</h5>
                    <ul class="footer-links">
                        <li class="d-flex gap-2">
                            <i class="bi bi-geo-alt-fill text-danger flex-shrink-0 mt-1"></i>
                            <span>Av. Cristóbal de Mendoza #450 (Segundo Anillo), Santa Cruz de la Sierra - Bolivia</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-telephone-fill text-primary flex-shrink-0 mt-1"></i>
                            <span>Teléfono: (+591) 3-345-6789</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-whatsapp text-success flex-shrink-0 mt-1"></i>
                            <span>Celular / WhatsApp: (+591) 700-12345</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-clock-fill text-warning flex-shrink-0 mt-1"></i>
                            <span>Lun - Vie: 08:00 a 18:30<br>Sábados: 08:30 a 13:00</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Copyright & Credits -->
            <div class="pt-4 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size: 0.78rem;">
                <div>
                    &copy; {{ date('Y') }} Multipartes El Tuerca - Santa Cruz. Todos los derechos reservados.
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-secondary">Paleta macOS Light Clean · Sistema de Catálogo Comercial</span>
                    <a href="{{ route('login') }}" class="text-secondary text-decoration-none hover-white">Acceso al Sistema</a>
                </div>
            </div>
        </div>
    </footer>
