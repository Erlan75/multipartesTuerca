<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rol;
use App\Helpers\Bitacora;

class RolController extends Controller
{
    /**
     * CU04: Listar roles (vista con modales para crear/editar).
     */
    public function index()
    {
        $roles = Rol::withCount('usuarios')->orderBy('id_rol')->get();
        return view('roles.index', compact('roles'));
    }

    /**
     * CU04: Crear nuevo rol.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre_rol'  => 'required|string|min:3|max:50',
            'descripcion' => 'nullable|string|max:255',
        ], [
            'nombre_rol.required' => 'El nombre del rol es obligatorio.',
            'nombre_rol.min'      => 'El nombre del rol debe tener al menos 3 caracteres.',
        ]);

        $rol = Rol::create($request->only(['nombre_rol', 'descripcion']));

        Bitacora::registrar(
            'Seguridad / Roles',
            'INSERT',
            $rol->id_rol,
            json_encode(['rol_creado' => $rol->nombre_rol])
        );

        return redirect()->route('roles.index')
            ->with('success', 'Rol "' . $rol->nombre_rol . '" creado exitosamente.');
    }

    /**
     * CU04: Actualizar rol existente.
     */
    public function update(Request $request, $id)
    {
        $rol = Rol::findOrFail($id);

        $request->validate([
            'nombre_rol'  => 'required|string|min:3|max:50',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $rol->update($request->only(['nombre_rol', 'descripcion']));

        Bitacora::registrar(
            'Seguridad / Roles',
            'UPDATE',
            $rol->id_rol,
            json_encode(['rol_editado' => $rol->nombre_rol])
        );

        return redirect()->route('roles.index')
            ->with('success', 'Rol "' . $rol->nombre_rol . '" actualizado.');
    }

    /**
     * CU04: Eliminar rol.
     * PostgreSQL lanzará RESTRICT si el rol tiene usuarios asignados.
     */
    public function destroy($id)
    {
        $rol = Rol::findOrFail($id);

        try {
            $rol->delete();

            Bitacora::registrar(
                'Seguridad / Roles',
                'DELETE',
                $id,
                json_encode(['rol_eliminado' => $rol->nombre_rol])
            );

            return redirect()->route('roles.index')
                ->with('success', 'Rol "' . $rol->nombre_rol . '" eliminado.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('roles.index')
                ->with('error', 'No se puede eliminar el rol "' . $rol->nombre_rol . '" porque tiene usuarios asignados.');
        }
    }
}
