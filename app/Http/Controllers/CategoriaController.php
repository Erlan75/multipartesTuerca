<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categoria;
use App\Helpers\Bitacora;

class CategoriaController extends Controller
{
    /**
     * CU07: Listar categorías (vista con modales CRUD).
     */
    public function index()
    {
        $categorias = Categoria::withCount('productos')->orderBy('id_categoria')->get();
        return view('categorias.index', compact('categorias'));
    }

    /**
     * CU07: Crear categoría.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|min:3|max:60',
            'descripcion' => 'nullable|string',
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.min'      => 'El nombre debe tener al menos 3 caracteres.',
        ]);

        $categoria = Categoria::create($request->only(['nombre', 'descripcion']));

        Bitacora::registrar(
            'Catálogo / Categorías',
            'INSERT',
            $categoria->id_categoria,
            json_encode(['categoria_creada' => $categoria->nombre])
        );

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría "' . $categoria->nombre . '" creada exitosamente.');
    }

    /**
     * CU07: Actualizar categoría.
     */
    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'nombre'      => 'required|string|min:3|max:60',
            'descripcion' => 'nullable|string',
        ]);

        $categoria->update($request->only(['nombre', 'descripcion']));

        Bitacora::registrar(
            'Catálogo / Categorías',
            'UPDATE',
            $categoria->id_categoria,
            json_encode(['categoria_editada' => $categoria->nombre])
        );

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría "' . $categoria->nombre . '" actualizada.');
    }

    /**
     * CU07: Eliminar categoría.
     * PostgreSQL lanza RESTRICT si tiene productos asociados.
     */
    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);

        try {
            $categoria->delete();

            Bitacora::registrar(
                'Catálogo / Categorías',
                'DELETE',
                $id,
                json_encode(['categoria_eliminada' => $categoria->nombre])
            );

            return redirect()->route('categorias.index')
                ->with('success', 'Categoría "' . $categoria->nombre . '" eliminada.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('categorias.index')
                ->with('error', 'No se puede eliminar "' . $categoria->nombre . '" porque tiene productos asociados.');
        }
    }
}
