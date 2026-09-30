<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * CU01: Procesar inicio de sesión.
     * Autentica contra username + password_hash usando Hash::check internamente.
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

        // Laravel usa getAuthPassword() del modelo para obtener el hash
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

            $request->session()->regenerate();

            Bitacora::registrar(
                'Seguridad / Acceso',
                'LOGIN',
                Auth::id(),
                json_encode(['evento' => 'Inicio de sesión exitoso', 'usuario' => Auth::user()->username])
            );

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => 'Credenciales incorrectas. Verifique su usuario/correo y contraseña.',
        ])->withInput($request->only('username'));
    }

    /**
     * CU02: Cerrar sesión.
     */
    public function logout(Request $request)
    {
        Bitacora::registrar(
            'Seguridad / Acceso',
            'LOGOUT',
            Auth::id(),
            json_encode(['evento' => 'Cierre de sesión', 'usuario' => Auth::user()->username])
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
