<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehiculo;
use App\Helpers\Bitacora;

class VehiculoController extends Controller
{
    /**
     * CU10: Listar vehículos (vista con modales CRUD).
     */
    public function index(Request $request)
    {
        $query = Vehiculo::withCount('compatibilidades');

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('marca', 'ilike', "%{$buscar}%")
                  ->orWhere('modelo', 'ilike', "%{$buscar}%")
                  ->orWhere('motor_cilindrada', 'ilike', "%{$buscar}%");
            });
        }

        $vehiculos = $query->orderBy('marca')->orderBy('modelo')->orderBy('anio_inicio')->paginate(15)->withQueryString();
        return view('vehiculos.index', compact('vehiculos'));
    }

    /**
     * CU10: Crear vehículo.
     */
    public function store(Request $request)
    {
        $request->validate([
            'marca'            => 'required|string|min:2|max:60',
            'modelo'           => 'required|string|min:1|max:80',
            'anio_inicio'      => 'required|integer|min:1950|max:2040',
            'anio_fin'         => 'required|integer|min:1950|max:2040|gte:anio_inicio',
            'motor_cilindrada' => 'nullable|string|max:50',
            'combustible'      => 'required|in:Gasolina,Diésel,GNV,Híbrido,Eléctrico',
        ], [
            'marca.min'       => 'La marca debe tener al menos 2 caracteres.',
            'anio_fin.gte'    => 'El año final debe ser mayor o igual al año inicial.',
            'combustible.in'  => 'Tipo de combustible inválido.',
        ]);

        $vehiculo = Vehiculo::create($request->only([
            'marca', 'modelo', 'anio_inicio', 'anio_fin', 'motor_cilindrada', 'combustible'
        ]));

        Bitacora::registrar(
            'Catálogo / Vehículos',
            'INSERT',
            $vehiculo->id_vehiculo,
            json_encode(['vehiculo' => "{$vehiculo->marca} {$vehiculo->modelo} ({$vehiculo->anio_inicio}-{$vehiculo->anio_fin})"])
        );

        return redirect()->route('vehiculos.index')
            ->with('success', "Vehículo {$vehiculo->marca} {$vehiculo->modelo} creado exitosamente.");
    }

    /**
     * CU10: Actualizar vehículo.
     */
    public function update(Request $request, $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        $request->validate([
            'marca'            => 'required|string|min:2|max:60',
            'modelo'           => 'required|string|min:1|max:80',
            'anio_inicio'      => 'required|integer|min:1950|max:2040',
            'anio_fin'         => 'required|integer|min:1950|max:2040|gte:anio_inicio',
            'motor_cilindrada' => 'nullable|string|max:50',
            'combustible'      => 'required|in:Gasolina,Diésel,GNV,Híbrido,Eléctrico',
        ]);

        $vehiculo->update($request->only([
            'marca', 'modelo', 'anio_inicio', 'anio_fin', 'motor_cilindrada', 'combustible'
        ]));

        Bitacora::registrar(
            'Catálogo / Vehículos',
            'UPDATE',
            $vehiculo->id_vehiculo,
            json_encode(['vehiculo_editado' => "{$vehiculo->marca} {$vehiculo->modelo}"])
        );

        return redirect()->route('vehiculos.index')
            ->with('success', "Vehículo {$vehiculo->marca} {$vehiculo->modelo} actualizado.");
    }

    /**
     * CU10: Eliminar vehículo (CASCADE elimina compatibilidades).
     */
    public function destroy($id)
    {
        $vehiculo = Vehiculo::findOrFail($id);
        $descripcion = "{$vehiculo->marca} {$vehiculo->modelo}";

        $vehiculo->delete();

        Bitacora::registrar(
            'Catálogo / Vehículos',
            'DELETE',
            $id,
            json_encode(['vehiculo_eliminado' => $descripcion])
        );

        return redirect()->route('vehiculos.index')
            ->with('success', "Vehículo {$descripcion} eliminado.");
    }
}
