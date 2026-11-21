<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rol;
use App\Helpers\Bitacora;
use App\Services\ExportadorUniversalService;

class RolController extends Controller
{
    /**
     * CU04: Listar roles (vista con modales para crear/editar).
     */
    public function index(Request $request, ExportadorUniversalService $exportador)
    {
        $roles = Rol::withCount('usuarios')->orderBy('id_rol')->get();

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $encabezados = ['ID', 'Nombre del Rol', 'Descripción', 'Usuarios Asignados'];
            $filas = $roles->map(fn($r) => [
                (int) $r->id_rol,
                $r->nombre_rol,
                $r->descripcion ?? '-',
                (int) $r->usuarios_count,
            ])->toArray();

            return $exportador->exportarLista(
                $export,
                'roles_sistema_' . now()->format('Ymd_His'),
                'Roles y Perfiles de Acceso',
                ['Total roles configurados: ' . $roles->count()],
                $encabezados,
                $filas,
                [],
                ['TOTALES', '', '', (int) $roles->sum('usuarios_count')]
            );
        }

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
