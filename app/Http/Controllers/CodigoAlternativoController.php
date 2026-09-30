<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CodigoAlternativo;
use App\Helpers\Bitacora;

class CodigoAlternativoController extends Controller
{
    /**
     * CU09: Registrar código alternativo para un producto.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_producto'       => 'required|integer|exists:productos,id_producto',
            'codigo_referencia' => 'required|string|min:2|max:60',
            'marca_alternativa' => 'nullable|string|max:80',
        ], [
            'codigo_referencia.required' => 'El código de referencia es obligatorio.',
            'codigo_referencia.min'      => 'El código debe tener al menos 2 caracteres.',
        ]);

        $codigo = CodigoAlternativo::create($request->only([
            'id_producto', 'codigo_referencia', 'marca_alternativa'
        ]));

        Bitacora::registrar(
            'Catálogo / Códigos Alternativos',
            'INSERT',
            $codigo->id_codigo_alt,
            json_encode([
                'id_producto'       => $codigo->id_producto,
                'codigo_referencia' => $codigo->codigo_referencia,
                'marca_alternativa' => $codigo->marca_alternativa,
            ])
        );

        return redirect()->route('productos.edit', $request->id_producto)
            ->with('success', 'Código alternativo "' . $codigo->codigo_referencia . '" registrado.');
    }

    /**
     * CU09: Eliminar código alternativo.
     */
    public function destroy($id)
    {
        $codigo = CodigoAlternativo::findOrFail($id);
        $idProducto = $codigo->id_producto;

        $codigo->delete();

        Bitacora::registrar(
            'Catálogo / Códigos Alternativos',
            'DELETE',
            $id,
            json_encode(['codigo_eliminado' => $codigo->codigo_referencia, 'id_producto' => $idProducto])
        );

        return redirect()->route('productos.edit', $idProducto)
            ->with('success', 'Código alternativo eliminado.');
    }
}
