<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Vehiculo;
use App\Helpers\Bitacora;

class ProductoController extends Controller
{
    /**
     * CU08, CU12, CU13, CU14: Listar productos con búsqueda técnica multicriterio y coordenadas de almacén.
     */
    public function index(Request $request)
    {
        $query = Producto::with(['categoria', 'codigosAlternativos']);

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_interno', 'ilike', "%{$buscar}%")
                  ->orWhere('codigo_oem', 'ilike', "%{$buscar}%")
                  ->orWhere('nombre_pieza', 'ilike', "%{$buscar}%")
                  ->orWhere('marca_fabricante', 'ilike', "%{$buscar}%")
                  ->orWhereHas('codigosAlternativos', function ($ca) use ($buscar) {
                      $ca->where('codigo_referencia', 'ilike', "%{$buscar}%")
                         ->orWhere('marca_alternativa', 'ilike', "%{$buscar}%");
                  });
            });
        }

        if ($request->filled('id_categoria')) {
            $query->where('id_categoria', $request->id_categoria);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado === 'activo');
        }

        $productos = $query->orderBy('id_producto')->paginate(15)->withQueryString();
        $categorias = Categoria::orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias'));
    }

    /**
     * CU08: Formulario de creación de producto.
     */
    public function create()
    {
        $categorias = Categoria::orderBy('nombre')->get();
        return view('productos.create', compact('categorias'));
    }

    /**
     * CU08: Almacenar nuevo producto.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_categoria'          => 'required|integer|exists:categorias,id_categoria',
            'codigo_interno'        => 'required|string|min:2|max:40',
            'codigo_oem'            => 'required|string|min:2|max:60',
            'nombre_pieza'          => 'required|string|min:3|max:150',
            'marca_fabricante'      => 'nullable|string|max:80',
            'procedencia'           => 'nullable|string|max:50',
            'precio_venta_sugerido' => 'required|numeric|min:0.01',
            'margen_ganancia'       => 'required|numeric|min:0',
            'unidad_medida'         => 'required|string|max:20',
            'stock_minimo'          => 'required|integer|min:0',
            'stock_seguridad'       => 'required|integer|min:0',
            'pasillo'               => 'nullable|string|max:20',
            'estante'               => 'nullable|string|max:20',
            'nivel_gaveta'          => 'nullable|string|max:20',
        ], [
            'codigo_interno.min'        => 'El código interno debe tener al menos 2 caracteres.',
            'codigo_oem.min'            => 'El código OEM debe tener al menos 2 caracteres.',
            'nombre_pieza.min'          => 'El nombre de la pieza debe tener al menos 3 caracteres.',
            'precio_venta_sugerido.min' => 'El precio debe ser mayor a 0.',
        ]);

        $producto = Producto::create($request->only([
            'id_categoria', 'codigo_interno', 'codigo_oem', 'nombre_pieza',
            'marca_fabricante', 'procedencia', 'precio_venta_sugerido',
            'margen_ganancia', 'unidad_medida', 'stock_minimo', 'stock_seguridad',
            'pasillo', 'estante', 'nivel_gaveta',
        ]));

        Bitacora::registrar(
            'Catálogo / Productos',
            'INSERT',
            $producto->id_producto,
            json_encode(['producto_creado' => $producto->nombre_pieza, 'codigo' => $producto->codigo_interno])
        );

        return redirect()->route('productos.index')
            ->with('success', 'Producto "' . $producto->nombre_pieza . '" creado exitosamente.');
    }

    /**
     * CU08: Formulario de edición de producto.
     * Incluye tabs de códigos alternativos y compatibilidades.
     */
    public function edit($id)
    {
        $producto = Producto::with(['categoria', 'codigosAlternativos', 'compatibilidades.vehiculo'])
            ->findOrFail($id);
        $categorias = Categoria::orderBy('nombre')->get();
        $vehiculos = \App\Models\Vehiculo::orderBy('marca')->orderBy('modelo')->get();

        return view('productos.edit', compact('producto', 'categorias', 'vehiculos'));
    }

    /**
     * CU08: Actualizar producto existente.
     */
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $request->validate([
            'id_categoria'          => 'required|integer|exists:categorias,id_categoria',
            'codigo_interno'        => 'required|string|min:2|max:40',
            'codigo_oem'            => 'required|string|min:2|max:60',
            'nombre_pieza'          => 'required|string|min:3|max:150',
            'marca_fabricante'      => 'nullable|string|max:80',
            'procedencia'           => 'nullable|string|max:50',
            'precio_venta_sugerido' => 'required|numeric|min:0.01',
            'margen_ganancia'       => 'required|numeric|min:0',
            'unidad_medida'         => 'required|string|max:20',
            'stock_minimo'          => 'required|integer|min:0',
            'stock_seguridad'       => 'required|integer|min:0',
            'pasillo'               => 'nullable|string|max:20',
            'estante'               => 'nullable|string|max:20',
            'nivel_gaveta'          => 'nullable|string|max:20',
        ]);

        $producto->update($request->only([
            'id_categoria', 'codigo_interno', 'codigo_oem', 'nombre_pieza',
            'marca_fabricante', 'procedencia', 'precio_venta_sugerido',
            'margen_ganancia', 'unidad_medida', 'stock_minimo', 'stock_seguridad',
            'pasillo', 'estante', 'nivel_gaveta',
        ]));

        Bitacora::registrar(
            'Catálogo / Productos',
            'UPDATE',
            $producto->id_producto,
            json_encode(['producto_editado' => $producto->nombre_pieza, 'codigo' => $producto->codigo_interno])
        );

        return redirect()->route('productos.edit', $producto->id_producto)
            ->with('success', 'Producto "' . $producto->nombre_pieza . '" actualizado.');
    }

    /**
     * CU08: Activar/desactivar producto.
     */
    public function toggleStatus($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->estado = !$producto->estado;
        $producto->save();

        $estado = $producto->estado ? 'activado' : 'desactivado';

        Bitacora::registrar(
            'Catálogo / Productos',
            'UPDATE',
            $producto->id_producto,
            json_encode(['accion' => 'Toggle estado', 'nuevo_estado' => $estado, 'producto' => $producto->nombre_pieza])
        );

        return redirect()->route('productos.index')
            ->with('success', 'Producto "' . $producto->nombre_pieza . '" ' . $estado . '.');
    }

    /**
     * CU15: Asignar o actualizar coordenadas físicas de almacenamiento (Pasillo, Estante, Gaveta).
     */
    public function actualizarUbicacion(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $request->validate([
            'pasillo'      => 'nullable|string|max:20',
            'estante'      => 'nullable|string|max:20',
            'nivel_gaveta' => 'nullable|string|max:20',
        ]);

        $anterior = "{$producto->pasillo} / {$producto->estante} / {$producto->nivel_gaveta}";

        $producto->update([
            'pasillo'      => $request->filled('pasillo') ? trim($request->pasillo) : null,
            'estante'      => $request->filled('estante') ? trim($request->estante) : null,
            'nivel_gaveta' => $request->filled('nivel_gaveta') ? trim($request->nivel_gaveta) : null,
        ]);

        $nuevo = "{$producto->pasillo} / {$producto->estante} / {$producto->nivel_gaveta}";

        Bitacora::registrar(
            'Almacén / Zonificación',
            'UPDATE',
            $producto->id_producto,
            json_encode([
                'accion' => 'Actualizar coordenadas de almacenamiento',
                'producto' => $producto->nombre_pieza,
                'codigo' => $producto->codigo_interno,
                'ubicacion_anterior' => $anterior,
                'ubicacion_nueva' => $nuevo,
            ])
        );

        return back()->with('success', "Ubicación de '{$producto->nombre_pieza}' actualizada: [{$nuevo}].");
    }
}
