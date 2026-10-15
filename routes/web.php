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

// ═══════════════════════════════════════════════════════════════
// RUTAS PÚBLICAS (Sin autenticación)
// ═══════════════════════════════════════════════════════════════
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

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
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ── Dashboard ──
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Perfil: Cambiar Contraseña (todos los usuarios autenticados) ──
    Route::get('/perfil/password', [PerfilController::class, 'editPassword'])->name('perfil.password');
    Route::put('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password.update');

    // ═════════════════════════════════════════════════════════════
    // MÓDULO 1: SEGURIDAD Y ACCESO + ADMINISTRACIÓN (Solo Administrador)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador')->group(function () {

        // ── Gestión de Usuarios (CU03) ──
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{id}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{id}/toggle', [UsuarioController::class, 'toggleStatus'])->name('usuarios.toggle');

        // ── Gestión de Roles (CU04) ──
        Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
        Route::put('/roles/{id}', [RolController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [RolController::class, 'destroy'])->name('roles.destroy');

        // ── Bitácora de Auditoría (CU06) ──
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

        // ── Gestión de Proveedores (CU19) ──
        Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/proveedores/crear', [ProveedorController::class, 'create'])->name('proveedores.create');
        Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
        Route::get('/proveedores/{id}/editar', [ProveedorController::class, 'edit'])->name('proveedores.edit');
        Route::put('/proveedores/{id}', [ProveedorController::class, 'update'])->name('proveedores.update');
        Route::match(['post', 'patch'], '/proveedores/{id}/toggle-status', [ProveedorController::class, 'toggleStatus'])->name('proveedores.toggle-status');
        Route::match(['post', 'patch'], '/proveedores/{id}/toggle', [ProveedorController::class, 'toggleStatus'])->name('proveedores.toggle');

        // ── Cuentas por Pagar a Proveedores y Liquidación a Crédito (CU22) ──
        Route::get('/compras/cuentas/por-pagar', [CompraController::class, 'cuentasPorPagar'])->name('compras.cuentas-por-pagar');
        Route::post('/compras/{id}/liquidar-pago', [CompraController::class, 'liquidarPago'])->name('compras.liquidar-pago');
    });

    // ═════════════════════════════════════════════════════════════
    // MÓDULO 2 Y 3: CATÁLOGO, ALMACÉN, INVENTARIO Y COMPRAS
    // (Administrador + Bodeguero)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador,Bodeguero')->group(function () {

        // ── Categorías (CU07) ──
        Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
        Route::put('/categorias/{id}', [CategoriaController::class, 'update'])->name('categorias.update');
        Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');

        // ── Productos y Búsqueda Técnica (CU08, CU12, CU13, CU14, CU15) ──
        Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
        Route::get('/productos/{id}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::put('/productos/{id}', [ProductoController::class, 'update'])->name('productos.update');
        Route::patch('/productos/{id}/toggle', [ProductoController::class, 'toggleStatus'])->name('productos.toggle');
        Route::patch('/productos/{id}/ubicacion', [ProductoController::class, 'actualizarUbicacion'])->name('productos.ubicacion');

        // ── Códigos Alternativos (CU09) ──
        Route::post('/codigos-alternativos', [CodigoAlternativoController::class, 'store'])->name('codigos-alternativos.store');
        Route::delete('/codigos-alternativos/{id}', [CodigoAlternativoController::class, 'destroy'])->name('codigos-alternativos.destroy');

        // ── Vehículos (CU10) ──
        Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
        Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');
        Route::put('/vehiculos/{id}', [VehiculoController::class, 'update'])->name('vehiculos.update');
        Route::delete('/vehiculos/{id}', [VehiculoController::class, 'destroy'])->name('vehiculos.destroy');

        // ── Compatibilidades (CU11) ──
        Route::post('/compatibilidades', [CompatibilidadController::class, 'store'])->name('compatibilidades.store');
        Route::delete('/compatibilidades/{id}', [CompatibilidadController::class, 'destroy'])->name('compatibilidades.destroy');

        // ── Alertas de Stock Mínimo y Crítico (CU16) ──
        Route::get('/inventario/alertas', [InventarioController::class, 'alertas'])->name('inventario.alertas');

        // ── Ajustes Manuales y Uso Interno (CU17, CU18) ──
        Route::get('/inventario/ajustes', [InventarioController::class, 'ajustes'])->name('inventario.ajustes');
        Route::post('/inventario/ajustes', [InventarioController::class, 'storeAjuste'])->name('inventario.ajustes.store');

        // ── Compras, Recepción y Costos de Adquisición (CU20, CU21) ──
        Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/compras/recepcion', [CompraController::class, 'create'])->name('compras.create');
        Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
        Route::get('/compras/historial/costos', [CompraController::class, 'historialCostos'])->name('compras.historial-costos');
        Route::get('/compras/{id}', [CompraController::class, 'show'])->name('compras.show');
    });
});

