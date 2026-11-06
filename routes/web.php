<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CodigoAlternativoController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\CompatibilidadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\AsistenteIaController;

// ═══════════════════════════════════════════════════════════════
// RUTAS PÚBLICAS (Sin autenticación)
// ═══════════════════════════════════════════════════════════════
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/registro', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/registro', [AuthController::class, 'register'])->name('register.submit');

// ── Recuperación de Contraseña (Público) ──
Route::get('/recuperar-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/recuperar-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/restablecer-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/restablecer-password', [AuthController::class, 'resetPassword'])->name('password.update');

// ═══════════════════════════════════════════════════════════════
// RUTAS PROTEGIDAS (Requieren autenticación)
// ═══════════════════════════════════════════════════════════════
Route::middleware('auth')->group(function () {

    // ── Logout ──
    Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

    // ── Dashboard ──
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Perfil: Cambiar Contraseña (todos los usuarios autenticados) ──
    Route::get('/perfil/password', [PerfilController::class, 'editPassword'])->name('perfil.password');
    Route::put('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password.update');

    // ── Portal de Cotizaciones para Clientes Web (Rol 5 y Usuarios Autenticados) ──
    Route::get('/mis-cotizaciones', [CotizacionController::class, 'misCotizaciones'])->name('mis-cotizaciones.index');
    Route::post('/mis-cotizaciones', [CotizacionController::class, 'storeMiCotizacion'])->name('mis-cotizaciones.store');

    // ── Asistente Inteligente Multimodal de Voz y Texto (Voice-to-SQL) ──
    Route::post('/asistente/consultar', [AsistenteIaController::class, 'consultar'])->name('asistente.consultar');

    // ═════════════════════════════════════════════════════════════
    // MÓDULO 1: SEGURIDAD Y ACCESO + ADMINISTRACIÓN (Solo Administrador)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador')->group(function () {

        // ── Gestión de Usuarios ──
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{id}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{id}/toggle', [UsuarioController::class, 'toggleStatus'])->name('usuarios.toggle');

        // ── Gestión de Roles ──
        Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
        Route::put('/roles/{id}', [RolController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [RolController::class, 'destroy'])->name('roles.destroy');

        // ── Bitácora de Auditoría ──
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    });

    // ═════════════════════════════════════════════════════════════
    // MÓDULO FINANCIERO: CUENTAS POR PAGAR (Administrador + Cajero + Bodeguero)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador,Cajero,Bodeguero')->group(function () {
        Route::get('/compras/cuentas/por-pagar', [CompraController::class, 'cuentasPorPagar'])->name('compras.cuentas-por-pagar');
        Route::get('/cuentas-por-pagar', [CompraController::class, 'cuentasPorPagar'])->name('cuentas-por-pagar.alias');
        Route::get('/compras/pagos', [CompraController::class, 'cuentasPorPagar'])->name('compras.pagos.alias');
        Route::post('/compras/{id}/liquidar-pago', [CompraController::class, 'liquidarPago'])->name('compras.liquidar-pago');
    });

    // ═════════════════════════════════════════════════════════════
    // CATÁLOGO DE REPUESTOS Y CONSULTA (Admin + Bodeguero + Vendedor + Cajero)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador,Bodeguero,Vendedor,Cajero')->group(function () {
        Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
        Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('/repuestos', [ProductoController::class, 'index'])->name('repuestos.index');
        Route::get('/almacen/ubicaciones', [ProductoController::class, 'index'])->name('almacen.ubicaciones.alias');
    });

    // ═════════════════════════════════════════════════════════════
    // CICLO 3: CLIENTES, COTIZACIONES, PUNTO DE VENTA (POS) Y TESORERÍA/CAJA
    // (Administrador, Vendedor y Cajero)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador,Vendedor,Cajero')->group(function () {

        // ── Gestión de Clientes Particulares, Talleres y Flotas ──
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/crear', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{id}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::get('/clientes/{id}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/clientes/{id}', [ClienteController::class, 'update'])->name('clientes.update');

        // ── Cotizaciones y Proformas Comerciales ──
        Route::get('/cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
        Route::get('/cotizaciones/crear', [CotizacionController::class, 'create'])->name('cotizaciones.create');
        Route::post('/cotizaciones', [CotizacionController::class, 'store'])->name('cotizaciones.store');
        Route::post('/cotizaciones/{id}/convertir', [CotizacionController::class, 'convertirAVenta'])->name('cotizaciones.convertir');
        Route::match(['post', 'patch'], '/cotizaciones/{id}/anular', [CotizacionController::class, 'anular'])->name('cotizaciones.anular');

        // ── Punto de Venta (POS), Notas de Venta y Ticket Térmico 80mm ──
        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::get('/ventas/pos', [VentaController::class, 'pos'])->name('ventas.pos');
        Route::get('/ventas/crear', [VentaController::class, 'pos'])->name('ventas.create');
        Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::get('/ventas/{id}/ticket', [VentaController::class, 'ticket'])->name('ventas.ticket');
        Route::get('/ventas/{id}', [VentaController::class, 'ticket'])->name('ventas.show');
        Route::post('/ventas/{id}/anular', [VentaController::class, 'anular'])->name('ventas.anular');

        // ── Tesorería, Sesiones de Caja, Movimientos Menores y Arqueo ──
        Route::get('/caja', [CajaController::class, 'index'])->name('caja.index');
        Route::post('/caja/abrir', [CajaController::class, 'abrir'])->name('caja.abrir');
        Route::post('/caja/movimientos', [CajaController::class, 'registrarMovimiento'])->name('caja.movimientos.store');
        Route::post('/caja/{id}/cerrar', [CajaController::class, 'cerrar'])->name('caja.cerrar');
        Route::get('/caja/{id}', [CajaController::class, 'show'])->name('caja.show');
    });

    // Vista de proforma individual accesible también para el propio Cliente Web (validado en controlador)
    Route::get('/cotizaciones/{id}', [CotizacionController::class, 'show'])->name('cotizaciones.show');

    // ═════════════════════════════════════════════════════════════
    // MÓDULO 2 Y 3: ALMACÉN, INVENTARIO, PROVEEDORES Y COMPRAS
    // (Administrador + Bodeguero)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador,Bodeguero')->group(function () {

        // ── Gestión de Proveedores e Importadores Mayoristas ──
        Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/proveedores/crear', [ProveedorController::class, 'create'])->name('proveedores.create');
        Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
        Route::get('/proveedores/{id}/editar', [ProveedorController::class, 'edit'])->name('proveedores.edit');
        Route::put('/proveedores/{id}', [ProveedorController::class, 'update'])->name('proveedores.update');
        Route::match(['post', 'patch'], '/proveedores/{id}/toggle-status', [ProveedorController::class, 'toggleStatus'])->name('proveedores.toggle-status');
        Route::match(['post', 'patch'], '/proveedores/{id}/toggle', [ProveedorController::class, 'toggleStatus'])->name('proveedores.toggle');

        // ── Categorías (gestión) ──
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
        Route::put('/categorias/{id}', [CategoriaController::class, 'update'])->name('categorias.update');
        Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');

        // ── Productos (gestión y altas) ──
        Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
        Route::get('/productos/{id}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::put('/productos/{id}', [ProductoController::class, 'update'])->name('productos.update');
        Route::patch('/productos/{id}/toggle', [ProductoController::class, 'toggleStatus'])->name('productos.toggle');
        Route::patch('/productos/{id}/ubicacion', [ProductoController::class, 'actualizarUbicacion'])->name('productos.ubicacion');

        // ── Códigos Alternativos ──
        Route::post('/codigos-alternativos', [CodigoAlternativoController::class, 'store'])->name('codigos-alternativos.store');
        Route::delete('/codigos-alternativos/{id}', [CodigoAlternativoController::class, 'destroy'])->name('codigos-alternativos.destroy');

        // ── Catálogo Vehicular ──
        Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
        Route::get('/catalogo-vehicular', [VehiculoController::class, 'index'])->name('catalogo-vehicular.alias');
        Route::get('/vehiculos/{id}/repuestos', [VehiculoController::class, 'repuestosCompatibles'])->name('vehiculos.repuestos');
        Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');
        Route::put('/vehiculos/{id}', [VehiculoController::class, 'update'])->name('vehiculos.update');
        Route::delete('/vehiculos/{id}', [VehiculoController::class, 'destroy'])->name('vehiculos.destroy');

        // ── Compatibilidades ──
        Route::post('/compatibilidades', [CompatibilidadController::class, 'store'])->name('compatibilidades.store');
        Route::delete('/compatibilidades/{id}', [CompatibilidadController::class, 'destroy'])->name('compatibilidades.destroy');

        // ── Alertas de Stock Mínimo y Crítico ──
        Route::get('/inventario/alertas', [InventarioController::class, 'alertas'])->name('inventario.alertas');

        // ── Ajustes Manuales y Uso Interno ──
        Route::get('/inventario/ajustes', [InventarioController::class, 'ajustes'])->name('inventario.ajustes');
        Route::post('/inventario/ajustes', [InventarioController::class, 'storeAjuste'])->name('inventario.ajustes.store');

        // ── Compras, Recepción y Costos de Adquisición ──
        Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/compras/recepcion', [CompraController::class, 'create'])->name('compras.create');
        Route::get('/compras/crear', [CompraController::class, 'create'])->name('compras.crear.alias');
        Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
        Route::get('/compras/historial/costos', [CompraController::class, 'historialCostos'])->name('compras.historial-costos');
        Route::get('/compras/costos', [CompraController::class, 'historialCostos'])->name('compras.costos.alias');
        Route::get('/compras/{id}', [CompraController::class, 'show'])->name('compras.show');
    });
});
