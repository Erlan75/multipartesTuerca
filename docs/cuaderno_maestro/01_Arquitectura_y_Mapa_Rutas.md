# 01. Arquitectura del Sistema y Mapa de Rutas — Multipartes El Tuerca

Documento maestro de arquitectura de software para **Multipartes El Tuerca** (Santa Cruz de la Sierra, Bolivia — Av. Cristóbal de Mendoza #450, 2do Anillo). Diseñado bajo el paradigma **Smart-DB** sobre **PostgreSQL 16+**, con **Laravel 12** como capa de orquestación HTTP, exportación documental y experiencia conversacional.

---

## 1. Stack Tecnológico y Despliegue en la Nube

| Capa | Tecnología / Versión | Rol en la Arquitectura |
| :--- | :--- | :--- |
| **Lenguaje Backend** | PHP `^8.2` (imagen Docker `php:8.3-apache`) | Ejecución de controladores, servicios de dominio, exportación e integración IA |
| **Framework Web** | Laravel `^12.0` (`laravel/framework`) | Enrutamiento, sesiones, middleware de seguridad (`auth`, `role`, `PreventBackHistory`), vistas Blade |
| **Base de Datos** | PostgreSQL 16+ en **Neon Serverless** (`us-east-2` / Ohio) | Motor **Smart-DB**: 19 tablas, 17 procedimientos/funciones (`sp_*` / `fn_*`), 15 triggers transaccionales (`fn_trg_*`) |
| **Infraestructura Cloud** | **Render.com** (`render.yaml` + `Dockerfile`, región `ohio`) | Contenedor Apache+PHP optimizado, `AUTO_DEPLOY_SMARTDB=false`, healthcheck `/up`, logs a `stderr` |
| **Exportación PDF** | `barryvdh/laravel-dompdf` `^3.1` | Comprobantes de venta virtual/mostrador, proformas, notas de compra, actas de arqueo y reportes |
| **Exportación Excel** | `openspout/openspout` `^4.28` | Libros `.xlsx` estilizados con encabezado institucional, formato monetario `Bs. #,##0.00` y totales |
| **Gráficos Analíticos** | `Chart.js` (CDN oficial en `layouts/app.blade.php`) | Visualizaciones interactivas en los reportes del Ciclo 4 (`CU35`–`CU38`) |
| **Asistente IA y Voz** | Google **Gemini** (`gemini-2.5-flash` -> respaldo `gemini-2.0-flash`) + **Web Speech API** | Dictado por voz / texto en español, `functionDeclarations`, SQL read-only y respaldo local completo |
| **UI Vitrina Pública** | CSS Custom **Capa Zero** (`public/css/storefront.css`), Bootstrap 5.3.3, Bootstrap Icons 1.11.3, `Plus Jakarta Sans` + `JetBrains Mono` | Portada Dark Mode Premium + catálogo Swiss Clean Light, autocomplete, Bento Grid, Side Drawer y Split-Screen Auth |
| **UI Panel Administrativo** | Blade + Tailwind CSS CDN (`preflight: false`) + Bootstrap 5.3.3 + Font Awesome 6.5.1 | Terminal POS, tableros de gestión, arqueo de caja y widget flotante del Asistente IA |

---

## 2. Arquitectura de Carpetas y Servicios (`app/Services/`)

### 2.1. Exportador Universal (`App\Services\ExportadorUniversalService`)
Centraliza la generación de reportes PDF y Excel (`.xlsx`) en todo el sistema (`ExportacionService` fue eliminado para evitar wrappers redundantes). Es inyectado directamente en:
- `DashboardController` (catálogo activo)
- `ReporteController` (CU35–CU38)
- `VentaController` (notas de venta + comprobante PDF descargable para cliente web y mostrador)
- `CotizacionController` (proformas comerciales)
- `CompraController` (recepciones de mercadería)
- `CajaController` (actas de arqueo y movimientos)

Expone `exportarLista(...)`, `exportarPdf(...)` (orientación automática `portrait`/`landscape` según cantidad de columnas) y `exportarExcel(...)` (estilos OpenSpout con encabezado *"MULTIPARTES EL TUERCA - SANTA CRUZ"*, subtítulos de filtros, KPIs y fila de totales).

### 2.2. Arquitectura Modular del Asistente IA (`CU39`)
La fachada `App\Services\AiSqlAssistantService` delega sus responsabilidades en cuatro clases cohesivas dentro de `app/Services/Ai/`:

1. **`AiGeminiClient` (`app/Services/Ai/AiGeminiClient.php`):**
   - Gestiona llamadas HTTP (`Http::timeout(6.5)`) contra la API v1beta de Google Gemini usando `config('services.gemini.key')`, probando primero `gemini-2.5-flash` y conmutando automáticamente a `gemini-2.0-flash` ante fallo/cuota (registrando en `Log::warning`).
   - Construye el `systemInstruction` con el mapa funcional completo de la plataforma web y el esquema real de las **19 tablas** + funciones `fn_reporte_*`.
   - Declara las 5 herramientas transaccionales (`functionDeclarations`): `preparar_venta`, `preparar_cotizacion`, `preparar_compra`, `preparar_ajuste_inventario` y `preparar_cliente`.
2. **`AiTextNormalizer` (`app/Services/Ai/AiTextNormalizer.php`):**
   - Limpia muletillas de dictado por voz (*"ehh"*, *"por favor"*, *"hazme un favor"*, *"digamos"*, etc.) y aplica normalización fonética (*pastiyas -> pastilla*, *coroya -> corolla*, *jailux -> hilux*, *bosh -> bosch*).
   - Ejecuta búsqueda difusa tolerante a errores (`levenshtein` + `similar_text`) por nombre, marca, código OEM, SKU, códigos alternativos y vehículos compatibles, además de resolver clientes y proveedores.
   - Depura tecnicismos (`SQLSTATE`, nombres internos de tablas/SP) y formatea texto para lectura en voz alta (`Bs.` -> `bolivianos`, `OEM` -> `código O E M`).
3. **`AiDraftManager` (`app/Services/Ai/AiDraftManager.php`):**
   - Mantiene la conversación multi-turno en sesión (`ai_chat_history`, hasta 8 turnos) y los borradores transaccionales (`ai_tx_draft`, TTL 15 minutos).
   - Implementa la máquina de estados por campo (`pendiente` | `completado` | `omitido_por_usuario`), permitiendo extracción simultánea de múltiples datos y respetando frases como *"no sé / cualquiera / normal"*.
   - Gestiona el subflujo guiado de **alta de producto nuevo durante una compra** y la **recuperación tras cancelar tarjeta** (`ai_last_cancelled_draft`, TTL 5 minutos).
4. **`AiTransactionExecutor` (`app/Services/Ai/AiTransactionExecutor.php`):**
   - Verifica la matriz de permisos `PERMISOS_TRANSACCIONALES` por `id_rol`.
   - Construye tarjetas de confirmación con `action_token` (UUID en sesión, expiración 10 min) y `draft_snapshot`.
   - Ejecuta atómicamente en PostgreSQL `CALL sp_registrar_venta`, `CALL sp_registrar_cotizacion`, `CALL sp_registrar_compra`, `CALL sp_registrar_ajuste_inventario`, y altas de `clientes` y `productos` con `Bitacora::registrar(...)`, propagando `app.id_usuario` y `app.ip_origen`.

---

## 3. Inventario Completo de Controladores (`app/Http/Controllers/`)

| Controlador | Responsabilidad Principal | Ciclo |
| :--- | :--- | :--- |
| `HomeController` | Vitrina pública, búsqueda OEM/vehículo, filtros facetados, índice `autocompleteCatalog` y conteos sin N+1 | Público |
| `AuthController` | Login con control de intentos fallidos/bloqueo, registro de clientes web (`rol 5`), logout y restablecimiento de contraseña | Ciclo 1 / Público |
| `PerfilController` | Cambio de contraseña del usuario autenticado (`PASSWORD_CAMBIO_PERFIL`) | Ciclo 1 |
| `UsuarioController` | CRUD y activación/baja lógica de usuarios internos | Ciclo 1 |
| `RolController` | Gestión de roles y matriz de permisos | Ciclo 1 |
| `AuditoriaController` | Consulta filtrada de `bitacoras_auditorias` | Ciclo 1 |
| `DashboardController` | Métricas gerenciales del día (`fn_obtener_metricas_pos`) y exportación global del catálogo | Ciclos 1–4 |
| `CategoriaController` | CRUD de categorías de repuestos | Ciclo 2 |
| `ProductoController` | CRUD de repuestos, OEM, precios, márgenes, baja lógica y ubicación física (`pasillo`/`estante`/`nivel_gaveta`) | Ciclo 2 |
| `CodigoAlternativoController` | Altas y bajas de códigos alternativos por producto | Ciclo 2 |
| `VehiculoController` | Catálogo vehicular (marca, modelo, años, motor, combustible) y consulta de repuestos compatibles | Ciclo 2 |
| `CompatibilidadController` | Asociación producto–vehículo con notas de montaje/posición | Ciclo 2 |
| `InventarioController` | Alertas de stock crítico/bajo mínimo y registro de ajustes/mermas/uso interno vía `sp_registrar_ajuste_inventario` | Ciclo 2 |
| `ProveedorController` | CRUD y activación/desactivación de proveedores mayoristas | Ciclo 2 |
| `CompraController` | Recepción de mercadería (`sp_registrar_compra`), cuentas por pagar (`sp_liquidar_pago_compra`), historial de costos y exportación PDF/Excel | Ciclo 2 |
| `ClienteController` | Cartera de clientes particulares, talleres mecánicos y empresas/flotas con descuento frecuente | Ciclo 3 |
| `CotizacionController` | Proformas comerciales (`sp_registrar_cotizacion`), conversión a venta (`sp_convertir_cotizacion_a_venta`), anulación y portal `/mis-cotizaciones` | Ciclo 3 |
| `VentaController` | Terminal POS (`sp_registrar_venta`), anulación (`sp_anular_venta`), Ticket Térmico 80mm, PDF de venta y checkout virtual (`POST /mi-compra`, `/mi-comprobante/{id}`) | Ciclo 3 |
| `CajaController` | Apertura de turno (`sp_abrir_caja`), ingresos/retiros (`sp_registrar_movimiento_caja`), arqueo/cierre (`sp_cerrar_caja`) y exportación de actas | Ciclo 3 |
| `ReporteController` | Analítica Smart-DB: Rotación (`CU35`), Rentabilidad (`CU36`), Compras por Distribuidor (`CU37`), Balance de Medios de Pago (`CU38`) con Chart.js y exportación PDF/Excel | Ciclo 4 |
| `AsistenteIaController` | Endpoints `/asistente/consultar`, `/asistente-ia/confirmar` y `/asistente/limpiar` para el agente conversacional CU39 | Ciclo 4 |

---

## 4. Mapa de Rutas Agrupado por Ciclos y Perfiles (`routes/web.php`)

### 4.1. Rutas Públicas (Sin autenticación)
- `GET /` (`home`) -> `HomeController@index`
- `GET /login`, `POST /login` -> `AuthController`
- `GET /registro`, `POST /registro` -> `AuthController`
- `GET/POST /recuperar-password`, `GET/POST /restablecer-password` -> `AuthController`

### 4.2. Rutas Transversales (Middleware `auth`)
- `GET/POST /logout` (`logout`)
- `GET /dashboard` (`dashboard`), `GET /dashboard/exportar/{formato}` (`pdf|excel`)
- `GET/PUT /perfil/password` (`perfil.password`, `perfil.password.update`)
- **Portal Cliente Web / Comprador Virtual:**
  - `GET /mis-cotizaciones` (`mis-cotizaciones.index`), `POST /mis-cotizaciones` (`mis-cotizaciones.store`)
  - `POST /mi-compra` (`mi-compra.store`)
  - `GET /mi-comprobante/{id}` (`mi-comprobante.show`), `GET /mi-comprobante/{id}/pdf` (`mi-comprobante.pdf`)
  - `GET /cotizaciones/{id}` (`cotizaciones.show`, validado por titularidad si es Rol 5), `GET /cotizaciones/{id}/exportar/{formato}`
- **Asistente IA (CU39):**
  - `POST /asistente/consultar` (`asistente.consultar`)
  - `POST /asistente-ia/confirmar` (`asistente.confirmar`)
  - `POST /asistente/limpiar` (`asistente.limpiar`)

### 4.3. Rutas por Ciclo y Middleware de Rol (`role:...`)

| Módulo / Ciclo | Prefijos / Rutas Principales | Roles Autorizados |
| :--- | :--- | :--- |
| **Ciclo 1 — Seguridad y Auditoría** | `/usuarios*`, `/roles*`, `/auditoria` | `Administrador` |
| **Ciclo 2 — Consulta de Catálogo** | `/categorias`, `/productos`, `/repuestos`, `/almacen/ubicaciones` | `Administrador`, `Bodeguero`, `Vendedor`, `Cajero` |
| **Ciclo 2 — Gestión Almacén y Compras** | `/proveedores*`, `/categorias` (POST/PUT/DEL), `/productos/*` (CRUD/toggle/ubicación), `/codigos-alternativos*`, `/vehiculos*`, `/compatibilidades*`, `/inventario/alertas`, `/inventario/ajustes`, `/compras*` | `Administrador`, `Bodeguero` |
| **Ciclo 2 — Cuentas por Pagar** | `/compras/cuentas/por-pagar`, `/compras/{id}/liquidar-pago` | `Administrador`, `Cajero`, `Bodeguero` |
| **Ciclo 3 — Comercial, POS y Caja** | `/clientes*`, `/cotizaciones*` (bandeja, crear, convertir, anular), `/ventas*` (pos, ticket 80mm, pdf, exportar, anular), `/caja*` (abrir, movimientos, cerrar, exportar) | `Administrador`, `Vendedor`, `Cajero` |
| **Ciclo 4 — Reportes Analíticos** | `/reportes/rotacion`, `/reportes/compras-distribuidor` | `Administrador`, `Bodeguero` |
| **Ciclo 4 — Rentabilidad** | `/reportes/rentabilidad` | `Administrador` (exclusivo) |
| **Ciclo 4 — Balance Medios de Pago** | `/reportes/balance-medios-pago` | `Administrador`, `Cajero` |

---

## 5. Organización de la Vitrina Pública (`resources/views/partials/storefront/`)

`resources/views/welcome.blade.php` (44 líneas) actúa como layout limpio que ensambla los estilos `public/css/storefront.css` y 7 parciales modulares:
1. `navbar.blade.php`: Header sticky translúcido (`80px -> 64px`), 4 enlaces, contador de carrito, acceso y menú overlay móvil.
2. `hero.blade.php`: Portada Dark Mode estática, buscador con autocompletado instantáneo (`CZ_CATALOGO_AUTOCOMPLETE`), Logo Cloud y 3 estadísticas animadas (`Count-up`).
3. `bento_showcase.blade.php`: Bento Grid asimétrico de garantía OEM y acceso rápido a categorías clave.
4. `catalog_grid.blade.php`: Filter chips horizontales, filtros combinables por vehículo/marca/stock, Product Cards con badges de disponibilidad y estado vacío.
5. `faq_section.blade.php`: Acordeón de 5 preguntas frecuentes con animación CSS suave.
6. `footer.blade.php`: Fat Footer oscuro en 4 columnas con dirección, horarios y contacto oficial.
7. `cart_drawer.blade.php`: Side Drawer deslizante (`420px`), selector Compra Directa vs. Proforma Formal, Trust Badges, barra inferior fija móvil y modal de compatibilidad vehicular.
