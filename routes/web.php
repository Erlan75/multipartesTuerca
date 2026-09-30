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

// ═══════════════════════════════════════════════════════════════
// RUTAS PÚBLICAS (Sin autenticación)
// ═══════════════════════════════════════════════════════════════
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

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
    // MÓDULO 1: SEGURIDAD Y ACCESO (Solo Administrador)
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
    });

    // ═════════════════════════════════════════════════════════════
    // MÓDULO 2: CATÁLOGO Y COMPATIBILIDAD (Administrador + Bodeguero)
    // ═════════════════════════════════════════════════════════════
    Route::middleware('role:Administrador,Bodeguero')->group(function () {

        // ── Categorías (CU07) ──
        Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
        Route::put('/categorias/{id}', [CategoriaController::class, 'update'])->name('categorias.update');
        Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');

        // ── Productos (CU08) ──
        Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
        Route::get('/productos/{id}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::put('/productos/{id}', [ProductoController::class, 'update'])->name('productos.update');
        Route::patch('/productos/{id}/toggle', [ProductoController::class, 'toggleStatus'])->name('productos.toggle');

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
    });
});
