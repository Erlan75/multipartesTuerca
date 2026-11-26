<!-- Fat Footer Oscuro (#0F172A) en 4 Columnas -->
<footer id="ubicacion" class="cz-footer">
    <div class="cz-container">
        <div class="row g-5">
            <!-- Columna 1: Identidad de Multipartes El Tuerca -->
            <div class="col-12 col-lg-4">
                <a href="{{ route('home') }}" class="cz-brand mb-3">
                    <span class="cz-brand-mark">
                        <i class="bi bi-gear-wide-connected"></i>
                    </span>
                    <span>
                        <span class="d-block cz-brand-name">MULTIPARTES EL TUERCA</span>
                        <span class="d-block cz-brand-loc">Santa Cruz de la Sierra</span>
                    </span>
                </a>
                <p class="mt-3 mb-4" style="font-size: 0.88rem; line-height: 1.65; color: var(--cz-slate-400);">
                    Especialistas en repuestos originales y alternativos certificados para vehículos japoneses y coreanos. Inventario real, verificación por código OEM y respaldo directo a talleres y propietarios.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="https://wa.me/59173397587" target="_blank" class="cz-btn-emerald" style="min-height: 44px; padding: 0 18px; font-size: 0.85rem;">
                        <i class="bi bi-whatsapp"></i>
                        <span>WhatsApp Directo</span>
                    </a>
                    <a href="{{ route('login') }}" class="cz-btn-ghost-dark" style="min-height: 44px; padding: 0 16px; font-size: 0.85rem;">
                        <i class="bi bi-shield-lock"></i>
                        <span>Portal</span>
                    </a>
                </div>
            </div>

            <!-- Columna 2: Enlaces Rápidos -->
            <div class="col-6 col-md-4 col-lg-2">
                <h3 class="cz-footer-heading">Enlaces Rápidos</h3>
                <ul class="cz-footer-list">
                    <li><a href="#catalogo">Catálogo Completo</a></li>
                    <li><a href="#categorias">Categorías</a></li>
                    <li><a href="#preguntas-frecuentes">Preguntas Frecuentes</a></li>
                    <li><a href="{{ route('register') }}">Crear Cuenta Cliente</a></li>
                    <li><a href="{{ route('login') }}">Ingresar al Sistema</a></li>
                </ul>
            </div>

            <!-- Columna 3: Sistemas y Marcas -->
            <div class="col-6 col-md-4 col-lg-2">
                <h3 class="cz-footer-heading">Marcas Clave</h3>
                <ul class="cz-footer-list">
                    <li><a href="{{ route('home', ['marca' => 'Toyota']) }}#catalogo">Toyota</a></li>
                    <li><a href="{{ route('home', ['marca' => 'Suzuki']) }}#catalogo">Suzuki</a></li>
                    <li><a href="{{ route('home', ['marca' => 'Nissan']) }}#catalogo">Nissan</a></li>
                    <li><a href="{{ route('home', ['marca' => 'Hyundai']) }}#catalogo">Hyundai</a></li>
                    <li><a href="{{ route('home', ['marca' => 'Kia']) }}#catalogo">Kia</a></li>
                    <li><a href="{{ route('home', ['marca' => 'Mitsubishi']) }}#catalogo">Mitsubishi</a></li>
                </ul>
            </div>

            <!-- Columna 4: Horarios y Dirección Exacta -->
            <div class="col-12 col-md-4 col-lg-4">
                <h3 class="cz-footer-heading">Casa Matriz y Horarios</h3>
                <ul class="cz-footer-list">
                    <li class="d-flex align-items-start gap-2">
                        <i class="bi bi-geo-alt-fill text-success mt-1 flex-shrink-0"></i>
                        <span style="color: var(--cz-slate-300); font-size: 0.88rem;">
                            Av. Cristóbal de Mendoza #450, 2do Anillo, Santa Cruz de la Sierra, Bolivia
                        </span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-telephone-fill text-warning flex-shrink-0"></i>
                        <a href="tel:59133456789">Tel: 3-345-6789</a>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-whatsapp text-success flex-shrink-0"></i>
                        <a href="https://wa.me/59173397587" target="_blank">WhatsApp: 73397587</a>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="bi bi-clock-fill text-warning mt-1 flex-shrink-0"></i>
                        <span style="color: var(--cz-slate-300); font-size: 0.86rem;">
                            Lunes a Viernes: 08:00 – 18:30<br>
                            Sábados: 08:30 – 13:00
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Aviso Legal & Copyright -->
        <div class="cz-footer-bottom">
            <div>
                &copy; {{ date('Y') }} Multipartes El Tuerca — Santa Cruz de la Sierra, Bolivia. Todos los derechos reservados.
            </div>
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <span>Precios expresados en Bolivianos (Bs.)</span>
                <a href="{{ route('login') }}" style="color: var(--cz-slate-300); text-decoration: none;">Acceso Personal</a>
            </div>
        </div>
    </div>
</footer>
