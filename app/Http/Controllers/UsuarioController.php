<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;
use App\Models\Rol;
use App\Helpers\Bitacora;

class UsuarioController extends Controller
{
    /**
     * CU03: Listar usuarios con búsqueda.
     */
    public function index(Request $request)
    {
        $query = Usuario::with('rol');

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre_completo', 'ilike', "%{$buscar}%")
                  ->orWhere('username', 'ilike', "%{$buscar}%")
                  ->orWhere('correo', 'ilike', "%{$buscar}%");
            });
        }

        $usuarios = $query->orderBy('id_usuario')->paginate(15)->withQueryString();
        return view('usuarios.index', compact('usuarios'));
    }

    /**
     * CU03: Formulario de creación de usuario.
     */
    public function create()
    {
        $roles = Rol::orderBy('id_rol')->get();
        return view('usuarios.create', compact('roles'));
    }

    /**
     * CU03: Almacenar nuevo usuario.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_rol'           => 'required|integer|exists:roles,id_rol',
            'nombre_completo'  => 'required|string|min:3|max:120',
            'username'         => 'required|string|min:4|max:50|unique:usuarios,username',
            'correo'           => 'required|email|max:100|unique:usuarios,correo',
            'telefono'         => 'nullable|string|max:20',
            'password'         => 'required|string|min:6|confirmed',
        ], [
            'nombre_completo.min' => 'El nombre debe tener al menos 3 caracteres.',
            'username.min'        => 'El usuario debe tener al menos 4 caracteres.',
            'username.unique'     => 'Este nombre de usuario ya está en uso.',
            'correo.unique'       => 'Este correo electrónico ya está registrado.',
            'password.min'        => 'La contraseña debe tener al menos 6 caracteres.',
            'password.confirmed'  => 'Las contraseñas no coinciden.',
        ]);

        $usuario = Usuario::create([
            'id_rol'          => $request->id_rol,
            'nombre_completo' => $request->nombre_completo,
            'username'        => $request->username,
            'correo'          => $request->correo,
            'telefono'        => $request->telefono,
            'password_hash'   => Hash::make($request->password),
            'estado'          => true,
        ]);

        Bitacora::registrar(
            'Seguridad / Usuarios',
            'INSERT',
            $usuario->id_usuario,
            json_encode(['usuario_creado' => $usuario->username, 'rol' => $usuario->rol->nombre_rol])
        );

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario "' . $usuario->nombre_completo . '" creado exitosamente.');
    }

    /**
     * CU03: Formulario de edición de usuario.
     */
    public function edit($id)
    {
        $usuario = Usuario::findOrFail($id);
        $roles = Rol::orderBy('id_rol')->get();
        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    /**
     * CU03: Actualizar datos del usuario (sin contraseña).
     */
    public function update(Request $request, $id)
    {
        $usuario = Usuario::findOrFail($id);

        $request->validate([
            'id_rol'           => 'required|integer|exists:roles,id_rol',
            'nombre_completo'  => 'required|string|min:3|max:120',
            'username'         => 'required|string|min:4|max:50|unique:usuarios,username,' . $id . ',id_usuario',
            'correo'           => 'required|email|max:100|unique:usuarios,correo,' . $id . ',id_usuario',
            'telefono'         => 'nullable|string|max:20',
        ]);

        $usuario->update($request->only([
            'id_rol', 'nombre_completo', 'username', 'correo', 'telefono'
        ]));

        Bitacora::registrar(
            'Seguridad / Usuarios',
            'UPDATE',
            $usuario->id_usuario,
            json_encode(['usuario_editado' => $usuario->username])
        );

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario "' . $usuario->nombre_completo . '" actualizado correctamente.');
    }

    /**
     * CU03: Activar/desactivar usuario.
     */
    public function toggleStatus($id)
    {
        $usuario = Usuario::findOrFail($id);

        // No permitir desactivarse a sí mismo
        if ($usuario->id_usuario === auth()->id()) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puede desactivar su propia cuenta.');
        }

        $usuario->estado = !$usuario->estado;
        $usuario->save();

        $estado = $usuario->estado ? 'activado' : 'desactivado';

        Bitacora::registrar(
            'Seguridad / Usuarios',
            'UPDATE',
            $usuario->id_usuario,
            json_encode(['accion' => 'Toggle estado', 'nuevo_estado' => $estado, 'usuario' => $usuario->username])
        );

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario "' . $usuario->nombre_completo . '" ' . $estado . '.');
    }
}
