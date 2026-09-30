<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Compatibilidad;
use App\Helpers\Bitacora;

class CompatibilidadController extends Controller
{
    /**
     * CU11: Asignar compatibilidad producto ↔ vehículo.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_producto'    => 'required|integer|exists:productos,id_producto',
            'id_vehiculo'    => 'required|integer|exists:vehiculos,id_vehiculo',
            'notas_posicion' => 'nullable|string|max:255',
        ]);

        // PostgreSQL tiene UNIQUE(id_producto, id_vehiculo), capturamos duplicado
        try {
            $compat = Compatibilidad::create($request->only([
                'id_producto', 'id_vehiculo', 'notas_posicion'
            ]));

            Bitacora::registrar(
                'Catálogo / Compatibilidades',
                'INSERT',
                $compat->id_compatibilidad,
                json_encode([
                    'id_producto' => $compat->id_producto,
                    'id_vehiculo' => $compat->id_vehiculo,
                    'notas'       => $compat->notas_posicion,
                ])
            );

            return redirect()->route('productos.edit', $request->id_producto)
                ->with('success', 'Compatibilidad vehicular registrada exitosamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('productos.edit', $request->id_producto)
                ->with('error', 'Esta compatibilidad ya existe para este producto y vehículo.');
        }
    }

    /**
     * CU11: Eliminar asignación de compatibilidad.
     */
    public function destroy($id)
    {
        $compat = Compatibilidad::findOrFail($id);
        $idProducto = $compat->id_producto;

        $compat->delete();

        Bitacora::registrar(
            'Catálogo / Compatibilidades',
            'DELETE',
            $id,
            json_encode(['id_producto' => $idProducto, 'id_vehiculo' => $compat->id_vehiculo])
        );

        return redirect()->route('productos.edit', $idProducto)
            ->with('success', 'Compatibilidad vehicular eliminada.');
    }
}
