<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Multipartes El Tuerca - Santa Cruz | Catálogo de Repuestos Automotrices</title>
    <meta name="description" content="Catálogo comercial de repuestos para vehículos japoneses y coreanos en Santa Cruz de la Sierra. Repuestos originales y certificados con entrega inmediata.">

    <!-- Fuentes e Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --mac-bg: #F5F7FB;
            --mac-surface: #FFFFFF;
            --mac-border: #E2E8F0;
            --mac-border-subtle: rgba(226, 232, 240, 0.8);
            --mac-primary: #007AFF;
            --mac-primary-hover: #0055D4;
            --mac-primary-soft: rgba(0, 122, 255, 0.08);
            --mac-success: #34C759;
            --mac-success-soft: rgba(52, 199, 89, 0.12);
            --mac-warning: #FF9500;
            --mac-warning-soft: rgba(255, 149, 0, 0.12);
            --mac-danger: #FF3B30;
            --mac-title: #0F172A;
            --mac-text: #334155;
            --mac-muted: #64748B;
            --mac-subtle: #94A3B8;
            --mac-radius: 16px;
            --mac-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 2px 6px -1px rgba(0, 0, 0, 0.02);
            --mac-shadow-lg: 0 16px 32px -4px rgba(0, 122, 255, 0.08), 0 4px 12px -2px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--mac-bg);
            color: var(--mac-text);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ── Top Utility Bar ── */
        .top-utility-bar {
            background: #0F172A;
            color: #94A3B8;
            font-size: 0.78rem;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .top-utility-bar a {
            color: #CBD5E1;
            text-decoration: none;
            transition: color 0.15s;
        }
        .top-utility-bar a:hover {
            color: #FFFFFF;
        }

        /* ── Public Navbar ── */
        .public-navbar {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--mac-border);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }
        .brand-logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .brand-logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #007AFF 0%, #0055D4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 1.35rem;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            flex-shrink: 0;
        }
        .brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--mac-title);
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .brand-subtitle {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--mac-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ── Nav Buttons ── */
        .btn-mac-primary {
            background: linear-gradient(135deg, #007AFF 0%, #0055D4 100%);
            color: #FFFFFF !important;
            border: none;
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.25);
            text-decoration: none;
        }
        .btn-mac-primary:hover {
            background: linear-gradient(135deg, #0066D6 0%, #0047B3 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(0, 122, 255, 0.35);
        }
        .btn-mac-outline {
            background: #FFFFFF;
            color: var(--mac-title) !important;
            border: 1px solid var(--mac-border);
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-mac-outline:hover {
            border-color: var(--mac-primary);
            color: var(--mac-primary) !important;
            background: var(--mac-primary-soft);
        }

        /* ── Hero Section ── */
        .hero-section {
            background: radial-gradient(120% 100% at 50% 0%, #FFFFFF 0%, #F5F7FB 100%);
            border-bottom: 1px solid var(--mac-border);
            padding: 48px 0 54px;
            position: relative;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--mac-primary);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 18px;
        }
        .hero-badge .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: var(--mac-success);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 3px rgba(52, 199, 89, 0.2);
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--mac-title);
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 14px;
        }
        @media (max-width: 768px) {
            .hero-title {
                font-size: 1.85rem;
            }
        }
        .hero-subtitle {
            font-size: 1.05rem;
            color: var(--mac-muted);
            max-width: 680px;
            margin: 0 auto 30px;
            line-height: 1.5;
        }

        /* ── Hero Search Box ── */
        .search-card {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 20px;
            padding: 10px;
            box-shadow: var(--mac-shadow-lg);
            max-width: 860px;
            margin: 0 auto;
        }
        .search-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        @media (min-width: 768px) {
            .search-input-group {
                flex-wrap: nowrap;
            }
        }
        .search-input-wrapper {
            position: relative;
            flex-grow: 1;
            min-width: 240px;
        }
        .search-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--mac-subtle);
            font-size: 1.1rem;
        }
        .search-input-wrapper input {
            width: 100%;
            border: 1px solid transparent;
            background: var(--mac-bg);
            border-radius: 12px;
            padding: 12px 14px 12px 46px;
            font-size: 0.95rem;
            color: var(--mac-title);
            transition: all 0.2s;
            outline: none;
        }
        .search-input-wrapper input:focus {
            background: #FFFFFF;
            border-color: var(--mac-primary);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15);
        }
        .search-select {
            border: 1px solid transparent;
            background: var(--mac-bg);
            border-radius: 12px;
            padding: 12px 34px 12px 14px;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--mac-text);
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .search-select:focus {
            background: #FFFFFF;
            border-color: var(--mac-primary);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15);
        }
        .btn-search-hero {
            background: linear-gradient(135deg, #007AFF 0%, #0055D4 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            cursor: pointer;
        }
        .btn-search-hero:hover {
            background: linear-gradient(135deg, #0066D6 0%, #0047B3 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 122, 255, 0.4);
        }

        /* Quick Search Chips */
        .quick-searches {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 18px;
            font-size: 0.78rem;
            color: var(--mac-muted);
        }
        .quick-chip {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 999px;
            padding: 3px 12px;
            color: var(--mac-text);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.15s;
        }
        .quick-chip:hover {
            border-color: var(--mac-primary);
            color: var(--mac-primary);
            background: var(--mac-primary-soft);
        }

        /* ── Value Props Strip ── */
        .trust-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            max-width: 960px;
            margin: 36px auto 0;
        }
        .trust-item {
            background: #FFFFFF;
            border: 1px solid var(--mac-border-subtle);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .trust-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--mac-primary-soft);
            color: var(--mac-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .trust-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--mac-title);
            margin: 0;
            line-height: 1.2;
        }
        .trust-desc {
            font-size: 0.72rem;
            color: var(--mac-muted);
            margin: 0;
        }

        /* ── Section Titles ── */
        .section-header {
            margin-bottom: 26px;
        }
        .section-tag {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--mac-primary);
            margin-bottom: 4px;
        }
        .section-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--mac-title);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .section-subtitle {
            font-size: 0.88rem;
            color: var(--mac-muted);
            margin: 0;
        }

        /* ── Brand Grid (Sección 1) ── */
        .brand-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 14px;
        }
        @media (min-width: 992px) {
            .brand-grid {
                grid-template-columns: repeat(9, 1fr);
            }
        }
        .brand-card {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 16px;
            padding: 18px 10px 14px;
            text-align: center;
            text-decoration: none;
            color: var(--mac-title);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            position: relative;
            cursor: pointer;
            height: 100%;
        }
        .brand-card:hover {
            transform: translateY(-3px);
            border-color: var(--mac-primary);
            box-shadow: 0 8px 20px rgba(0, 122, 255, 0.12);
            color: var(--mac-primary);
        }
        .brand-card.active {
            border: 2px solid var(--mac-primary);
            background: rgba(0, 122, 255, 0.04);
            box-shadow: 0 6px 18px rgba(0, 122, 255, 0.15);
        }
        .brand-card.active::after {
            content: "✓ Activo";
            position: absolute;
            top: -9px;
            background: var(--mac-primary);
            color: #FFFFFF;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 1px 8px;
            border-radius: 999px;
            letter-spacing: 0.04em;
        }
        .brand-logo-wrap {
            width: 46px;
            height: 46px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-logo-wrap svg,
        .brand-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .brand-name {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 2px;
            line-height: 1.2;
        }
        .brand-count {
            font-size: 0.68rem;
            color: var(--mac-muted);
            font-weight: 500;
        }

        /* ── Model Selector (Filtered Brand) ── */
        .model-filter-bar {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 14px;
            padding: 14px 18px;
            margin-top: 14px;
            box-shadow: var(--mac-shadow);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .model-chip {
            background: var(--mac-bg);
            border: 1px solid var(--mac-border);
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--mac-text);
            text-decoration: none;
            transition: all 0.15s;
        }
        .model-chip:hover {
            border-color: var(--mac-primary);
            color: var(--mac-primary);
            background: var(--mac-primary-soft);
        }
        .model-chip.active {
            background: var(--mac-primary);
            border-color: var(--mac-primary);
            color: #FFFFFF;
        }

        /* ── Category Cards (Sección 2) ── */
        .category-card {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: var(--mac-title);
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            height: 100%;
        }
        .category-card:hover {
            transform: translateY(-2px);
            border-color: var(--mac-primary);
            box-shadow: 0 8px 24px rgba(0, 122, 255, 0.09);
            color: var(--mac-primary);
        }
        .category-card.active {
            border: 2px solid var(--mac-primary);
            background: rgba(0, 122, 255, 0.04);
        }
        .category-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .category-card-body h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.2;
        }
        .category-card-body p {
            font-size: 0.75rem;
            color: var(--mac-muted);
            margin: 0;
            font-weight: 500;
        }

        /* ── Product Cards (Sección 3) ── */
        .product-card {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-3px);
            border-color: rgba(0, 122, 255, 0.4);
            box-shadow: 0 12px 28px rgba(0, 122, 255, 0.1);
        }
        .product-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }
        .product-cat-badge {
            background: var(--mac-bg);
            border: 1px solid var(--mac-border);
            color: var(--mac-muted);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .product-maker-badge {
            background: var(--mac-primary-soft);
            color: var(--mac-primary);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
        }
        .product-name {
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--mac-title);
            line-height: 1.35;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.7em;
        }
        .product-codes-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .code-pill {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 3px 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.74rem;
            font-weight: 600;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .code-pill-oem {
            background: rgba(0, 122, 255, 0.07);
            border-color: rgba(0, 122, 255, 0.25);
            color: #0066D6;
        }
        .product-stock-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 14px;
        }
        .status-stock-ok {
            color: #15803D;
        }
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--mac-success);
        }
        .product-compatibility-snippet {
            background: var(--mac-bg);
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 16px;
            font-size: 0.74rem;
            color: var(--mac-text);
        }
        .product-compatibility-snippet .comp-title {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--mac-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .product-price-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            border-top: 1px solid var(--mac-border-subtle);
            padding-top: 14px;
            margin-top: auto;
        }
        .price-label {
            font-size: 0.68rem;
            color: var(--mac-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .price-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--mac-title);
            letter-spacing: -0.02em;
        }

        .btn-check-compat {
            background: #FFFFFF;
            border: 1px solid var(--mac-primary);
            color: var(--mac-primary);
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-check-compat:hover {
            background: var(--mac-primary);
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.25);
        }

        /* ── Active Filter Bar ── */
        .active-filters-bar {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 12px;
            padding: 10px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-tag {
            background: var(--mac-primary-soft);
            border: 1px solid rgba(0, 122, 255, 0.2);
            color: var(--mac-primary);
            border-radius: 999px;
            padding: 3px 12px;
            font-size: 0.76rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-tag a {
            color: var(--mac-primary);
            text-decoration: none;
            opacity: 0.7;
        }
        .filter-tag a:hover {
            opacity: 1;
        }

        /* ── Modal Styling (macOS Light) ── */
        .modal-content-mac {
            background: #FFFFFF;
            border: 1px solid var(--mac-border);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .modal-header-mac {
            border-bottom: 1px solid var(--mac-border);
            padding: 18px 24px;
            background: #F8FAFC;
        }
        .modal-body-mac {
            padding: 24px;
        }
        .modal-footer-mac {
            border-top: 1px solid var(--mac-border);
            padding: 16px 24px;
            background: #F8FAFC;
        }

        /* ── Footer ── */
        .public-footer {
            background: #0F172A;
            color: #94A3B8;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 60px 0 24px;
            margin-top: 70px;
        }
        .footer-heading {
            color: #FFFFFF;
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 18px;
            letter-spacing: -0.01em;
        }
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-links li {
            margin-bottom: 10px;
        }
        .footer-links a {
            color: #94A3B8;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.15s;
        }
        .footer-links a:hover {
            color: #FFFFFF;
        }

        /* ── Pagination (macOS Light Style) ── */
        .pagination {
            gap: 5px;
            justify-content: center;
            margin-top: 28px;
        }
        .page-link {
            color: #1E293B;
            background-color: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px !important;
            padding: 7px 14px;
            font-weight: 500;
            font-size: 0.84rem;
            transition: all 0.15s;
        }
        .page-link:hover {
            background-color: #F1F5F9;
            border-color: #CBD5E1;
            color: #007AFF;
        }
        .page-item.active .page-link {
            background-color: #007AFF;
            border-color: #007AFF;
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(0, 122, 255, 0.3);
        }
        .page-item.disabled .page-link {
            background-color: #F8FAFC;
            color: #94A3B8;
            border-color: #E2E8F0;
        }
    </style>
</head>
<body>

    <!-- ── Top Utility Strip ── -->
    <div class="top-utility-bar">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> Santa Cruz de la Sierra - Bolivia</span>
                <span class="d-none d-md-inline text-secondary">|</span>
                <span class="d-none d-md-inline"><i class="bi bi-clock-fill text-warning me-1"></i> Lun - Sáb: 08:00 - 18:30</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="https://wa.me/59170012345" target="_blank">
                    <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp: (+591) 700-12345
                </a>
                <span class="text-secondary">|</span>
                <a href="tel:59133456789">
                    <i class="bi bi-telephone-fill text-primary me-1"></i> Tel: 3-345-6789
                </a>
            </div>
        </div>
    </div>

    <!-- ── Public Header & Navbar ── -->
    <header class="public-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="brand-logo-container">
                <div class="brand-logo-icon">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div>
                    <div class="brand-title">MULTIPARTES EL TUERCA</div>
                    <div class="brand-subtitle">Santa Cruz · Catálogo Automotriz</div>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="d-none d-lg-flex align-items-center gap-4">
                <a href="#marcas" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Marcas</a>
                <a href="#categorias" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Categorías</a>
                <a href="#catalogo" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Repuestos</a>
                <a href="#beneficios" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Garantía</a>
                <a href="#contacto" class="text-decoration-none fw-semibold text-secondary" style="font-size: 0.88rem;">Ubicación</a>
            </nav>

            <!-- User / Login Action -->
            <div class="d-flex align-items-center gap-2">
                @auth
                    @if(auth()->user()->id_rol === 5)
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-none d-sm-inline fw-semibold text-dark" style="font-size: 0.88rem;">
                                <i class="bi bi-person-circle text-primary me-1"></i>Hola, {{ Str::words(auth()->user()->nombre_completo, 2, '') }}
                            </span>
                            <a href="#catalogo" class="btn-mac-outline btn-sm">
                                <i class="bi bi-receipt"></i>
                                <span>Mis Cotizaciones</span>
                            </a>
                            <form action="{{ route('logout') }}" method="POST" class="d-inline mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 10px; padding: 7px 12px; font-size: 0.82rem; font-weight: 600;">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span class="d-none d-md-inline ms-1">Cerrar Sesión</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-none d-md-inline fw-semibold text-dark" style="font-size: 0.88rem;">
                                <i class="bi bi-person-badge text-primary me-1"></i>Hola, {{ Str::words(auth()->user()->nombre_completo, 2, '') }} 
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.7rem;">{{ auth()->user()->rol->nombre_rol ?? 'Personal' }}</span>
                            </span>
                            <a href="{{ route('dashboard') }}" class="btn-mac-primary">
                                <i class="bi bi-speedometer2"></i>
                                <span>Panel Administrativo</span>
                            </a>
                            <form action="{{ route('logout') }}" method="POST" class="d-inline mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 10px; padding: 7px 12px; font-size: 0.82rem; font-weight: 600;" title="Cerrar Sesión">
                                    <i class="bi bi-box-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-mac-primary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Iniciar Sesión</span>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="container mt-3">
            <div class="alert alert-success d-flex align-items-center justify-content-between rounded-4 shadow-sm border-0 p-3" style="background:#ECFDF5; color:#065F46;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-5 text-success me-2"></i>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif
    @if(session('warning'))
        <div class="container mt-3">
            <div class="alert alert-warning d-flex align-items-center justify-content-between rounded-4 shadow-sm border-0 p-3" style="background:#FFFBEB; color:#92400E;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-5 text-warning me-2"></i>
                    <span>{{ session('warning') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

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
                                    <button type="button" 
                                            class="btn-check-compat" 
                                            onclick='verDetalleCompatibilidad(@json($p))'>
                                        <i class="bi bi-patch-check"></i>
                                        <span>Consultar compatibilidad</span>
                                    </button>
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

    <!-- ── Modal de Compatibilidad Vehicular (macOS Light Clean) ── -->
    <div class="modal fade" id="modalCompatibilidad" tabindex="-1" aria-labelledby="modalCompatibilidadLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-mac">
                <div class="modal-header modal-header-mac d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(0, 122, 255, 0.1); color: #007AFF; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-patch-check-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark m-0" id="modalCompatibilidadLabel" style="font-size: 1.05rem;">
                                Ficha Técnica y Compatibilidad Vehicular
                            </h5>
                            <span class="text-muted" style="font-size: 0.75rem;">Verifica la aplicación exacta antes de tu compra</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body modal-body-mac">
                    <!-- Ficha Rápida del Repuesto -->
                    <div class="p-3 mb-4 rounded-3" style="background: #F8FAFC; border: 1px solid var(--mac-border);">
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <div class="text-primary fw-bold text-uppercase" id="modalCategoria" style="font-size: 0.72rem; letter-spacing: 0.05em;"></div>
                                <h4 class="fw-bold text-dark mb-2" id="modalNombrePieza" style="font-size: 1.25rem;"></h4>
                                <div class="d-flex gap-2 flex-wrap mb-2">
                                    <span class="code-pill code-pill-oem" id="modalCodigoOem"></span>
                                    <span class="code-pill" id="modalCodigoInterno"></span>
                                    <span class="badge bg-white text-dark border" id="modalMarcaFabricante"></span>
                                    <span class="badge bg-white text-secondary border" id="modalProcedencia"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-4 text-md-end d-flex flex-column justify-content-center">
                                <span class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 600;">Precio de Venta</span>
                                <div class="fw-bold text-dark" id="modalPrecio" style="font-size: 1.5rem; letter-spacing: -0.02em;"></div>
                                <div class="text-success fw-semibold mt-1" style="font-size: 0.78rem;" id="modalStock">
                                    <i class="bi bi-check-circle-fill me-1"></i> En Stock Santa Cruz
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Vehículos Compatibles -->
                    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 0.92rem;">
                        <i class="bi bi-car-front-fill text-primary"></i>
                        <span>Modelos Compatibles Verificados:</span>
                    </h6>

                    <div class="table-responsive border rounded-3" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light text-muted" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>MARCA Y MODELO</th>
                                    <th>AÑOS</th>
                                    <th>MOTOR / CILINDRADA</th>
                                    <th>COMBUSTIBLE</th>
                                    <th>NOTAS DE MONTAJE / POSICIÓN</th>
                                </tr>
                            </thead>
                            <tbody id="modalVehiculosList">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer modal-footer-mac d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">
                        Cerrar
                    </button>
                    <a id="modalBtnWhatsApp" href="#" target="_blank" class="btn btn-success" style="border-radius: 10px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.25);">
                        <i class="bi bi-whatsapp"></i>
                        <span>Consultar este Repuesto por WhatsApp</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /**
         * Abre y llena el modal interactivo de compatibilidad técnica
         */
        function verDetalleCompatibilidad(producto) {
            document.getElementById('modalNombrePieza').textContent = producto.nombre_pieza;
            document.getElementById('modalCategoria').textContent = producto.categoria ? producto.categoria.nombre : 'REPUESTO';
            document.getElementById('modalCodigoOem').innerHTML = '<i class="bi bi-upc-scan"></i> OEM: ' + producto.codigo_oem;
            document.getElementById('modalCodigoInterno').textContent = 'SKU: ' + producto.codigo_interno;
            document.getElementById('modalMarcaFabricante').textContent = 'Fabricante: ' + (producto.marca_fabricante || 'Original');
            document.getElementById('modalProcedencia').textContent = 'Procedencia: ' + (producto.procedencia || 'Importación');
            
            const precioFormatted = parseFloat(producto.precio_venta_sugerido).toFixed(2);
            document.getElementById('modalPrecio').textContent = 'Bs. ' + precioFormatted;
            
            document.getElementById('modalStock').innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + 
                producto.stock_actual + ' ' + (producto.unidad_medida || 'unidades') + ' disponibles en Santa Cruz';

            // Tabla de vehículos compatibles
            const tbody = document.getElementById('modalVehiculosList');
            tbody.innerHTML = '';

            if (producto.vehiculos && producto.vehiculos.length > 0) {
                producto.vehiculos.forEach(v => {
                    const tr = document.createElement('tr');
                    const notas = (v.pivot && v.pivot.notas_posicion) ? v.pivot.notas_posicion : 'Compatibilidad estándar según OEM';
                    tr.innerHTML = `
                        <td class="fw-bold text-dark">
                            <span class="badge bg-primary-subtle text-primary me-1">${v.marca}</span> ${v.modelo}
                        </td>
                        <td>${v.anio_inicio} - ${v.anio_fin}</td>
                        <td><code>${v.motor_cilindrada || 'Estándar'}</code></td>
                        <td><span class="badge bg-light text-secondary">${v.combustible || 'Gasolina'}</span></td>
                        <td class="text-muted"><small>${notas}</small></td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="5" class="text-center text-muted py-3">Aplicación universal o compatibilidad directa a verificar con número de chasis.</td>';
                tbody.appendChild(tr);
            }

            // Enlace dinámico de WhatsApp con mensaje precargado
            const mensajeWa = `Hola Multipartes El Tuerca, deseo consultar disponibilidad y precio para el repuesto: ${producto.nombre_pieza} (OEM: ${producto.codigo_oem}, Fabricante: ${producto.marca_fabricante || ''}). ¿Lo tienen para entrega inmediata en Santa Cruz?`;
            const waUrl = 'https://wa.me/59170012345?text=' + encodeURIComponent(mensajeWa);
            document.getElementById('modalBtnWhatsApp').setAttribute('href', waUrl);

            // Mostrar el modal
            const modalEl = document.getElementById('modalCompatibilidad');
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }
    </script>
</body>
</html>
