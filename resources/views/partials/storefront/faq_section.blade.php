<!-- Preguntas Frecuentes (FAQ Accordion con animación suave) -->
<section id="preguntas-frecuentes" class="cz-section">
    <div class="cz-container">
        <div class="row g-5 align-items-start">
            <div class="col-12 col-lg-4">
                <div class="cz-section-eyebrow">Soporte al Comprador</div>
                <h2 class="cz-section-title">Preguntas Frecuentes</h2>
                <p class="cz-section-sub mb-4">
                    Resolvemos las dudas más comunes de propietarios, mecánicos y encargados de flota antes de comprar.
                </p>
                <a href="https://wa.me/59173397587?text={{ urlencode('Hola Multipartes El Tuerca, tengo una consulta sobre repuestos.') }}"
                   target="_blank"
                   class="cz-btn-dark">
                    <i class="bi bi-whatsapp text-success"></i>
                    <span>Hablar con un Asesor</span>
                </a>
            </div>

            <div class="col-12 col-lg-8">
                @php
                    $faqs = [
                        [
                            'q' => '¿Cómo garantizan que el repuesto sea 100% compatible con mi vehículo?',
                            'a' => 'Cada pieza del catálogo está indexada por su código OEM original, referencias alternativas y tabla verificada de marca, modelo, año y cilindrada. Si tienes dudas antes de retirar o comprar, validamos la aplicación con tu número de chasis (VIN) sin costo adicional.',
                        ],
                        [
                            'q' => '¿Ofrecen precios especiales o descuentos para talleres mecánicos y flotas?',
                            'a' => 'Sí. Los talleres mecánicos, servitecas y empresas con flota de vehículos acceden a descuentos frecuentes preferenciales y vigencia extendida en proformas de hasta 15 días. Crea tu cuenta de cliente o contáctanos por WhatsApp para habilitar tu tarifa de taller.',
                        ],
                        [
                            'q' => '¿Cuánto demora el retiro en tienda o el envío dentro de Santa Cruz?',
                            'a' => 'Todo producto marcado como "En stock" se encuentra físicamente en nuestro almacén de Av. Cristóbal de Mendoza #450 (2do Anillo). El retiro en mostrador está listo en aproximadamente 15 minutos y realizamos despachos el mismo día por motomensajería a talleres de Santa Cruz y encomiendas a provincias.',
                        ],
                        [
                            'q' => '¿Puedo generar una proforma formal o descargar mi comprobante en PDF?',
                            'a' => 'Sí. Desde el carrito lateral puedes elegir entre "Compra Directa" (que emite tu Nota de Venta Digital descargable en PDF) o "Solicitar Proforma", generando un presupuesto formal con precios congelados en Bolivianos listo para presentar a tu cliente o administración.',
                        ],
                        [
                            'q' => '¿Qué formas de pago aceptan en mostrador y en compras online?',
                            'a' => 'En compras online aceptamos Transferencia Bancaria / QR y Tarjeta de Débito/Crédito. En nuestro mostrador de Santa Cruz aceptamos Efectivo en Bolivianos, QR bancario, Tarjeta y pagos mixtos con entrega inmediata de comprobante.',
                        ],
                    ];
                @endphp

                <div class="cz-faq-list">
                    @foreach($faqs as $idx => $faq)
                        <div class="cz-faq-item {{ $idx === 0 ? 'is-open' : '' }}">
                            <button type="button"
                                    class="cz-faq-trigger"
                                    onclick="toggleFaqItem(this)"
                                    aria-expanded="{{ $idx === 0 ? 'true' : 'false' }}">
                                <span>{{ $faq['q'] }}</span>
                                <span class="cz-faq-icon" aria-hidden="true">
                                    <i class="bi bi-plus-lg"></i>
                                </span>
                            </button>
                            <div class="cz-faq-content">
                                <div class="cz-faq-inner">
                                    <div class="cz-faq-answer">{{ $faq['a'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
