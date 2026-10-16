<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Usuario;
use App\Helpers\Bitacora;

class AuthController extends Controller
{
    /**
     * CU01: Mostrar formulario de inicio de sesión.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    /**
     * CU01: Procesar inicio de sesión con control de bloqueo por 3 intentos fallidos.
     * Autentica contra username o correo + password_hash usando Hash::check internamente.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:4',
            'password' => 'required|string|min:4',
        ], [
            'username.required' => 'El nombre de usuario o correo es obligatorio.',
            'username.min'      => 'El nombre de usuario o correo debe tener al menos 4 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 4 caracteres.',
        ]);

        $loginInput = trim($request->username);
        $loginField = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'correo' : 'username';

        // Buscar si el usuario existe para asociar su id_usuario y normalizar la clave de bloqueo
        $usuarioExistente = Usuario::where($loginField, $loginInput)->first();
        if (!$usuarioExistente) {
            $usuarioExistente = Usuario::where('username', $loginInput)->orWhere('correo', $loginInput)->first();
        }

        // Key de RateLimiter ligada a la cuenta
        $accountIdentifier = $usuarioExistente ? $usuarioExistente->username : $loginInput;
        $throttleKey = 'login_attempts:' . Str::lower($accountIdentifier);

        // Verificar si la cuenta ya se encuentra bloqueada por 3 intentos fallidos (1 min de lockout)
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'bloqueado' => "Cuenta bloqueada por 3 intentos fallidos. Intente nuevamente en {$seconds} segundos o recupere su contraseña.",
            ])->withInput($request->only('username'));
        }

        $credentials = [
            $loginField => $loginInput,
            'password'  => $request->password,
        ];

        if (Auth::attempt($credentials)) {
            // Verificar que el usuario esté activo
            if (!Auth::user()->estado) {
                Auth::logout();
                return back()->withErrors([
                    'username' => 'Su cuenta se encuentra deshabilitada. Contacte al administrador.',
                ])->withInput($request->only('username'));
            }

            // Login exitoso: reiniciar contador de intentos a 0
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            Bitacora::registrar(
                'Seguridad y Autenticación',
                'LOGIN',
                Auth::id(),
                'Inicio de sesión exitoso para el usuario: ' . Auth::user()->username,
                Auth::id()
            );

            return redirect()->intended(route('dashboard'));
        }

        // Credenciales erróneas: registrar intento fallido y aplicar lockout si llega a 3
        RateLimiter::hit($throttleKey, 60); // 1 minutos de bloqueo (60 seg)
        $attempts = RateLimiter::attempts($throttleKey);
        $userId = $usuarioExistente ? $usuarioExistente->id_usuario : null;

        // Registrar LOGIN_FALLIDO
        Bitacora::registrar(
            'Seguridad y Autenticación',
            'LOGIN_FALLIDO',
            $userId,
            "Intento {$attempts}/3 fallido para el usuario/correo: '{$loginInput}'",
            $userId
        );

        // Si se alcanzó el 3er intento: Registrar CUENTA_BLOQUEADA
        if ($attempts >= 3) {
            Bitacora::registrar(
                'Seguridad y Autenticación',
                'CUENTA_BLOQUEADA',
                $userId,
                "Bloqueo por exceder límite de 3 intentos fallidos para el usuario/correo: '{$loginInput}'",
                $userId
            );

            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'bloqueado' => "Cuenta bloqueada por 3 intentos fallidos. Intente nuevamente en {$seconds} segundos o recupere su contraseña.",
            ])->withInput($request->only('username'));
        }

        $restantes = 3 - $attempts;
        return back()->withErrors([
            'username' => "Credenciales incorrectas. Intento {$attempts} de 3. Le queda(n) {$restantes} intento(s) antes del bloqueo.",
        ])->withInput($request->only('username'));
    }

    /**
     * CU02: Cerrar sesión.
     */
    public function logout(Request $request)
    {
        if (Auth::check()) {
            Bitacora::registrar(
                'Seguridad y Autenticación',
                'LOGOUT',
                Auth::id(),
                'Cierre de sesión para el usuario: ' . Auth::user()->username,
                Auth::id()
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Recuperación de Contraseña: Vista para solicitar restablecimiento.
     */
    public function showForgotPasswordForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.recuperar-password');
    }

    /**
     * Recuperación de Contraseña: Procesar solicitud de restablecimiento.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'identificador' => 'required|string',
        ], [
            'identificador.required' => 'Debe ingresar su correo electrónico o nombre de usuario registrado.',
        ]);

        $input = trim($request->identificador);
        $usuario = Usuario::where('correo', $input)->orWhere('username', $input)->first();

        if (!$usuario) {
            Bitacora::registrar(
                'Seguridad y Autenticación',
                'PASSWORD_RESET_SOLICITUD',
                null,
                "Solicitud de recuperación de contraseña rechazada: usuario/correo '{$input}' no registrado",
                null
            );

            return back()->withErrors([
                'identificador' => 'No encontramos ninguna cuenta asociada a este usuario o correo electrónico.',
            ])->withInput();
        }

        // Generar token seguro temporal de 64 caracteres (vigencia de 30 minutos)
        $token = Str::random(64);
        Cache::put('password_reset_' . $token, [
            'id_usuario' => $usuario->id_usuario,
            'correo'     => $usuario->correo,
            'username'   => $usuario->username,
        ], now()->addMinutes(30));

        // Registro obligatorio en bitácora
        Bitacora::registrar(
            'Seguridad y Autenticación',
            'PASSWORD_RESET_SOLICITUD',
            $usuario->id_usuario,
            "Solicitud de recuperación de contraseña para el correo: {$usuario->correo} (Usuario: {$usuario->username})",
            $usuario->id_usuario
        );

        $resetUrl = route('password.reset', ['token' => $token]);

        return back()->with('status', 'Hemos generado el enlace de restablecimiento seguro.')
                     ->with('simulated_link', $resetUrl)
                     ->with('user_email', $usuario->correo)
                     ->with('user_name', $usuario->nombre_completo);
    }

    /**
     * Recuperación de Contraseña: Vista para ingresar la nueva contraseña mediante token.
     */
    public function showResetPasswordForm($token)
    {
        $resetData = Cache::get('password_reset_' . $token);

        if (!$resetData) {
            return redirect()->route('password.request')->withErrors([
                'identificador' => 'El enlace de recuperación es inválido o ha expirado. Por favor, solicite uno nuevo.',
            ]);
        }

        $usuario = Usuario::find($resetData['id_usuario']);
        if (!$usuario) {
            return redirect()->route('password.request')->withErrors([
                'identificador' => 'El usuario especificado ya no se encuentra en el sistema.',
            ]);
        }

        return view('auth.restablecer-password', compact('token', 'usuario', 'resetData'));
    }

    /**
     * Recuperación de Contraseña: Fijar la nueva contraseña.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&#_.-]/',
            ],
        ], [
            'token.required'     => 'El token de verificación es obligatorio.',
            'password.required'  => 'Debe ingresar la nueva contraseña.',
            'password.min'       => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex'     => 'La contraseña debe incluir al menos una letra mayúscula, una minúscula, un número y un carácter especial (@$!%*?&#_.-).',
        ]);

        $resetData = Cache::get('password_reset_' . $request->token);

        if (!$resetData) {
            return redirect()->route('password.request')->withErrors([
                'identificador' => 'El enlace de recuperación ha expirado o ya ha sido utilizado.',
            ]);
        }

        $usuario = Usuario::find($resetData['id_usuario']);
        if (!$usuario) {
            return redirect()->route('password.request')->withErrors([
                'identificador' => 'El usuario no fue encontrado.',
            ]);
        }

        // Actualizar contraseña física con Hash::make()
        $usuario->password_hash = Hash::make($request->password);
        $usuario->save();

        // Eliminar token de un solo uso
        Cache::forget('password_reset_' . $request->token);

        // Desbloquear al usuario inmediatamente reiniciando RateLimiter
        RateLimiter::clear('login_attempts:' . Str::lower($usuario->username));
        RateLimiter::clear('login_attempts:' . Str::lower($usuario->correo));

        // Registrar en bitácora
        Bitacora::registrar(
            'Seguridad y Autenticación',
            'PASSWORD_RESET_EXITOSO',
            $usuario->id_usuario,
            "Restablecimiento exitoso de contraseña para el usuario: {$usuario->username}",
            $usuario->id_usuario
        );

        return redirect()->route('login')->with('success', 'Contraseña restablecida exitosamente. Ya puede iniciar sesión con su nueva clave.');
    }
}
