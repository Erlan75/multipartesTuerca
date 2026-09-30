<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Bitacora;

class PerfilController extends Controller
{
    /**
     * CU05: Mostrar formulario de cambio de contraseña.
     */
    public function editPassword()
    {
        return view('perfil.password');
    }

    /**
     * CU05: Procesar cambio de contraseña.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_actual'  => 'required|string',
            'password_nueva'   => 'required|string|min:6|confirmed',
        ], [
            'password_actual.required'  => 'Debe ingresar su contraseña actual.',
            'password_nueva.required'   => 'Debe ingresar la nueva contraseña.',
            'password_nueva.min'        => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'password_nueva.confirmed'  => 'Las contraseñas nuevas no coinciden.',
        ]);

        $usuario = auth()->user();

        // Verificar contraseña actual
        if (!Hash::check($request->password_actual, $usuario->password_hash)) {
            return back()->withErrors(['password_actual' => 'La contraseña actual es incorrecta.']);
        }

        $usuario->password_hash = Hash::make($request->password_nueva);
        $usuario->save();

        Bitacora::registrar(
            'Seguridad / Perfil',
            'UPDATE',
            $usuario->id_usuario,
            json_encode(['evento' => 'Cambio de contraseña', 'usuario' => $usuario->username])
        );

        return redirect()->route('perfil.password')
            ->with('success', 'Contraseña actualizada exitosamente.');
    }
}
