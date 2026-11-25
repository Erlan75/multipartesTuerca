<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Multipartes El Tuerca — Repuestos Japoneses y Coreanos en Santa Cruz</title>
    <meta name="description" content="Catálogo de repuestos originales y alternativos para vehículos japoneses y coreanos en Santa Cruz de la Sierra. Búsqueda por código OEM, compra directa y proformas online.">

    <!-- Tipografía Capa Zero (Plus Jakarta Sans + JetBrains Mono) e Iconografía Bootstrap Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
</head>
<body>
    {{-- 1. Sticky Header Translúcido y Menú Móvil Overlay --}}
    @include('partials.storefront.navbar')

    <main>
        {{-- 2. Hero Above-the-Fold + Autocomplete Search + Logo Cloud + Count-up Stats --}}
        @include('partials.storefront.hero')

        {{-- 3. Bento Grid de Categorías y Garantía OEM --}}
        @include('partials.storefront.bento_showcase')

        {{-- 4. Catálogo con Filter Chips, Filtros Combinables y Product Cards --}}
        @include('partials.storefront.catalog_grid')

        {{-- 5. Preguntas Frecuentes (FAQ Accordion) --}}
        @include('partials.storefront.faq_section')
    </main>

    {{-- 6. Fat Footer Comercial en 4 Columnas --}}
    @include('partials.storefront.footer')

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- 7. Side Drawer de Carrito, Modal de Compatibilidad, Sticky Bar Móvil y Scripts --}}
    @include('partials.storefront.cart_drawer')
</body>
</html>
