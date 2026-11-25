<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Multipartes El Tuerca - Santa Cruz | Catálogo de Repuestos Automotrices</title>
    <meta name="description" content="Catálogo comercial de repuestos para vehículos japoneses y coreanos en Santa Cruz de la Sierra. Repuestos originales y certificados con entrega inmediata.">

    <!-- Fuentes, Iconos y Estilos de la Vitrina Pública -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
</head>
<body>
    {{-- Barra de utilidad superior, cabecera pública y notificaciones --}}
    @include('partials.storefront.navbar')

    {{-- Sección Hero, buscador técnico por OEM/marca/categoría y métricas de confianza --}}
    @include('partials.storefront.hero')

    {{-- Secciones de marcas, categorías, vitrina de repuestos en stock y beneficios --}}
    @include('partials.storefront.catalog_grid')

    {{-- Pie de página comercial y ubicación en Santa Cruz --}}
    @include('partials.storefront.footer')

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Modales de compatibilidad vehicular, carrito web directo y scripts interactivos --}}
    @include('partials.storefront.cart_drawer')
</body>
</html>
