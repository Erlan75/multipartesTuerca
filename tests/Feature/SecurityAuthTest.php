<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Usuario;
use App\Models\BitacoraAuditoria;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SecurityAuthTest extends TestCase
{
    protected $adminOriginalHash;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = Usuario::where('username', 'admin')->first();
        if ($admin) {
            $this->adminOriginalHash = $admin->password_hash;
        }
        // Limpiar rate limiters y caches antes de cada prueba
        RateLimiter::clear('login_attempts:admin');
        RateLimiter::clear('login_attempts:test_user');
    }

    protected function tearDown(): void
    {
        if ($this->adminOriginalHash) {
            Usuario::where('username', 'admin')->update([
                'password_hash' => $this->adminOriginalHash
            ]);
        }
        RateLimiter::clear('login_attempts:admin');
        parent::tearDown();
    }

    public function test_tres_intentos_fallidos_bloquean_la_cuenta_y_registran_en_bitacora(): void
    {
        // 1er intento fallido
        $response1 = $this->post('/login', [
            'username' => 'admin',
            'password' => 'wrong_password_1',
        ]);
        $response1->assertSessionHasErrors('username');
        $this->assertTrue(RateLimiter::attempts('login_attempts:admin') === 1);

        $ultimoFallido1 = BitacoraAuditoria::where('accion', 'LOGIN_FALLIDO')->latest('id_bitacora')->first();
        $this->assertNotNull($ultimoFallido1);
        $this->assertStringContainsString('Intento 1/3', $ultimoFallido1->detalle_cambio);

        // 2do intento fallido
        $response2 = $this->post('/login', [
            'username' => 'admin',
            'password' => 'wrong_password_2',
        ]);
        $response2->assertSessionHasErrors('username');
        $this->assertTrue(RateLimiter::attempts('login_attempts:admin') === 2);

        $ultimoFallido2 = BitacoraAuditoria::where('accion', 'LOGIN_FALLIDO')->latest('id_bitacora')->first();
        $this->assertStringContainsString('Intento 2/3', $ultimoFallido2->detalle_cambio);

        // 3er intento fallido -> DEBE BLOQUEAR
        $response3 = $this->post('/login', [
            'username' => 'admin',
            'password' => 'wrong_password_3',
        ]);
        $response3->assertSessionHasErrors('bloqueado');

        $bloqueo = BitacoraAuditoria::where('accion', 'CUENTA_BLOQUEADA')->latest('id_bitacora')->first();
        $this->assertNotNull($bloqueo);
        $this->assertStringContainsString('Bloqueo por exceder límite de 3 intentos fallidos', $bloqueo->detalle_cambio);

        // 4to intento mientras está bloqueado debe rebotar con error 'bloqueado'
        $response4 = $this->post('/login', [
            'username' => 'admin',
            'password' => 'any_password',
        ]);
        $response4->assertSessionHasErrors('bloqueado');
    }

    public function test_flujo_completo_recuperacion_y_restablecimiento_de_contrasena(): void
    {
        $admin = Usuario::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        // 1. Solicitar recuperación
        $responseSolicitud = $this->post('/recuperar-password', [
            'identificador' => $admin->correo,
        ]);
        $responseSolicitud->assertSessionHas('status');

        $bitacoraSolicitud = BitacoraAuditoria::where('accion', 'PASSWORD_RESET_SOLICITUD')
            ->where('registro_id', $admin->id_usuario)
            ->latest('id_bitacora')
            ->first();
        $this->assertNotNull($bitacoraSolicitud);
        $this->assertStringContainsString($admin->correo, $bitacoraSolicitud->detalle_cambio);

        // 2. Extraer token de la sesión simulada
        $simulatedLink = session('simulated_link');
        $this->assertNotEmpty($simulatedLink);
        preg_match('/\/restablecer-password\/([A-Za-z0-9]+)/', $simulatedLink, $matches);
        $token = $matches[1] ?? null;
        $this->assertNotNull($token);

        // 3. Ver vista de restablecimiento
        $responseView = $this->get('/restablecer-password/' . $token);
        $responseView->assertStatus(200);
        $responseView->assertSee('Establecer Nueva Contraseña');

        // 4. Fijar nueva contraseña
        $nuevaClave = 'adminSecure2026!';
        $responseReset = $this->post('/restablecer-password', [
            'token'                 => $token,
            'password'              => $nuevaClave,
            'password_confirmation' => $nuevaClave,
        ]);

        $responseReset->assertRedirect('/login');
        $responseReset->assertSessionHas('success');

        // 5. Verificar que el hash se actualizó en la base de datos
        $admin->refresh();
        $this->assertTrue(Hash::check($nuevaClave, $admin->password_hash));

        // 6. Verificar bitácora de PASSWORD_RESET_EXITOSO
        $bitacoraExito = BitacoraAuditoria::where('accion', 'PASSWORD_RESET_EXITOSO')
            ->where('registro_id', $admin->id_usuario)
            ->latest('id_bitacora')
            ->first();
        $this->assertNotNull($bitacoraExito);

        // 7. Verificar que el RateLimiter fue limpiado (desbloqueado)
        $this->assertFalse(RateLimiter::tooManyAttempts('login_attempts:admin', 3));
    }

    public function test_cambio_de_contrasena_desde_el_perfil(): void
    {
        $admin = Usuario::where('username', 'admin')->first();
        // Definir una contraseña conocida para la prueba
        $claveActual = 'tempPassword123!';
        $admin->password_hash = Hash::make($claveActual);
        $admin->save();

        $nuevaClave = 'newProfilePass2026!';

        $response = $this->actingAs($admin)->put('/perfil/password', [
            'password_actual'            => $claveActual,
            'password_nueva'             => $nuevaClave,
            'password_nueva_confirmation' => $nuevaClave,
        ]);

        $response->assertRedirect('/perfil/password');
        $response->assertSessionHas('success');

        $admin->refresh();
        $this->assertTrue(Hash::check($nuevaClave, $admin->password_hash));

        $bitacora = BitacoraAuditoria::where('accion', 'PASSWORD_CAMBIO_PERFIL')
            ->where('registro_id', $admin->id_usuario)
            ->latest('id_bitacora')
            ->first();
        $this->assertNotNull($bitacora);
        $this->assertEquals('Perfil de Usuario', $bitacora->modulo_afectado);
    }
}
