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
        $query = Vehiculo::withCount('compatibilidades')
            ->with(['productos' => function ($q) {
                $q->select(
                    'productos.id_producto',
                    'productos.codigo_interno',
                    'productos.codigo_oem',
                    'productos.nombre_pieza',
                    'productos.marca_fabricante',
                    'productos.precio_venta_sugerido',
                    'productos.stock_actual',
                    'productos.pasillo',
                    'productos.estante',
                    'productos.nivel_gaveta'
                );
            }]);

        if ($request->filled('marca')) {
            $query->where('marca', $request->marca);
        }

        if ($request->filled('modelo')) {
            $modelo = trim($request->modelo);
            $query->where('modelo', 'ilike', "%{$modelo}%");
        }

        if ($request->filled('anio')) {
            $anio = (int)$request->anio;
            $query->where('anio_inicio', '<=', $anio)
                  ->where('anio_fin', '>=', $anio);
        }

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('marca', 'ilike', "%{$buscar}%")
                  ->orWhere('modelo', 'ilike', "%{$buscar}%")
                  ->orWhere('motor_cilindrada', 'ilike', "%{$buscar}%");
            });
        }

        $vehiculos = $query->orderBy('marca')->orderBy('modelo')->orderBy('anio_inicio')->paginate(15)->withQueryString();
        $marcas = Vehiculo::select('marca')->distinct()->orderBy('marca')->pluck('marca');

        return view('vehiculos.index', compact('vehiculos', 'marcas'));
    }

    /**
     * Retorna los repuestos compatibles en formato JSON para un vehículo.
     */
    public function repuestosCompatibles($id)
    {
        $vehiculo = Vehiculo::with(['productos' => function ($q) {
            $q->select(
                'productos.id_producto',
                'productos.codigo_interno',
                'productos.codigo_oem',
                'productos.nombre_pieza',
                'productos.marca_fabricante',
                'productos.precio_venta_sugerido',
                'productos.stock_actual',
                'productos.pasillo',
                'productos.estante',
                'productos.nivel_gaveta'
            );
        }])->findOrFail($id);

        return response()->json([
            'vehiculo' => [
                'id_vehiculo' => $vehiculo->id_vehiculo,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'anio_inicio' => $vehiculo->anio_inicio,
                'anio_fin' => $vehiculo->anio_fin,
                'motor_cilindrada' => $vehiculo->motor_cilindrada ?? 'N/A',
                'combustible' => $vehiculo->combustible,
            ],
            'productos' => $vehiculo->productos
        ]);
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
