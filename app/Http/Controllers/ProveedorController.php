<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Proveedor;
use App\Models\NotaCompra;
use App\Helpers\Bitacora;

class ProveedorController extends Controller
{
    /**
     * CU19: Listado de proveedores mayoristas con filtros y paginación.
     */
    public function index(Request $request)
    {
        $query = Proveedor::query();

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('razon_social', 'ilike', "%{$buscar}%")
                  ->orWhere('nit', 'ilike', "%{$buscar}%")
                  ->orWhere('contacto_nombre', 'ilike', "%{$buscar}%")
                  ->orWhere('telefono', 'ilike', "%{$buscar}%");
            });
        }

        if ($request->filled('condicion')) {
            $query->where('condicion_comercial', $request->condicion);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado === 'activo');
        }

        $proveedores = $query->orderBy('razon_social', 'asc')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    /**
     * CU19: Formulario para crear un nuevo proveedor.
     */
    public function create()
    {
        return view('proveedores.create');
    }

    /**
     * CU19: Guardar un nuevo proveedor.
     */
    public function store(Request $request)
    {
        $request->validate([
            'razon_social'        => 'required|string|min:3|max:120',
            'nit'                 => 'nullable|string|max:30',
            'contacto_nombre'     => 'nullable|string|max:100',
            'telefono'            => 'nullable|string|max:20',
            'correo'              => 'nullable|email|max:100',
            'direccion'           => 'nullable|string|max:200',
            'condicion_comercial' => 'required|string|in:Contado,Crédito 15 días,Crédito 30 días,Crédito 60 días',
        ], [
            'razon_social.min'       => 'La razón social debe tener al menos 3 caracteres.',
            'condicion_comercial.in' => 'Seleccione una condición comercial válida.',
        ]);

        // Validar unicidad insensible a mayúsculas
        $existe = Proveedor::whereRaw('LOWER(TRIM(razon_social)) = LOWER(TRIM(?))', [$request->razon_social])->exists();
        if ($existe) {
            return back()->withInput()->withErrors(['razon_social' => 'Ya existe un proveedor registrado con esa Razón Social.']);
        }

        $proveedor = Proveedor::create([
            'razon_social'        => trim($request->razon_social),
            'nit'                 => $request->filled('nit') ? trim($request->nit) : null,
            'contacto_nombre'     => $request->filled('contacto_nombre') ? trim($request->contacto_nombre) : null,
            'telefono'            => $request->filled('telefono') ? trim($request->telefono) : null,
            'correo'              => $request->filled('correo') ? trim($request->correo) : null,
            'direccion'           => $request->filled('direccion') ? trim($request->direccion) : null,
            'condicion_comercial' => $request->condicion_comercial,
            'estado'              => true,
        ]);

        Bitacora::registrar(
            'Compras / Proveedores',
            'INSERT',
            $proveedor->id_proveedor,
            json_encode([
                'razon_social' => $proveedor->razon_social,
                'nit' => $proveedor->nit,
                'condicion' => $proveedor->condicion_comercial,
            ])
        );

        return redirect()->route('proveedores.index')
            ->with('success', "Proveedor '{$proveedor->razon_social}' registrado exitosamente.");
    }

    /**
     * CU19: Formulario de edición de proveedor.
     */
    public function edit($id)
    {
        $proveedor = Proveedor::findOrFail($id);
        return view('proveedores.edit', compact('proveedor'));
    }

    /**
     * CU19: Actualizar datos del proveedor.
     */
    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $request->validate([
            'razon_social'        => 'required|string|min:3|max:120',
            'nit'                 => 'nullable|string|max:30',
            'contacto_nombre'     => 'nullable|string|max:100',
            'telefono'            => 'nullable|string|max:20',
            'correo'              => 'nullable|email|max:100',
            'direccion'           => 'nullable|string|max:200',
            'condicion_comercial' => 'required|string|in:Contado,Crédito 15 días,Crédito 30 días,Crédito 60 días',
        ]);

        $duplicado = Proveedor::whereRaw('LOWER(TRIM(razon_social)) = LOWER(TRIM(?))', [$request->razon_social])
            ->where('id_proveedor', '<>', $id)
            ->exists();

        if ($duplicado) {
            return back()->withInput()->withErrors(['razon_social' => 'Ya existe otro proveedor registrado con esa Razón Social.']);
        }

        $proveedor->update([
            'razon_social'        => trim($request->razon_social),
            'nit'                 => $request->filled('nit') ? trim($request->nit) : null,
            'contacto_nombre'     => $request->filled('contacto_nombre') ? trim($request->contacto_nombre) : null,
            'telefono'            => $request->filled('telefono') ? trim($request->telefono) : null,
            'correo'              => $request->filled('correo') ? trim($request->correo) : null,
            'direccion'           => $request->filled('direccion') ? trim($request->direccion) : null,
            'condicion_comercial' => $request->condicion_comercial,
        ]);

        Bitacora::registrar(
            'Compras / Proveedores',
            'UPDATE',
            $proveedor->id_proveedor,
            json_encode([
                'razon_social' => $proveedor->razon_social,
                'nit' => $proveedor->nit,
                'condicion' => $proveedor->condicion_comercial,
            ])
        );

        return redirect()->route('proveedores.index')
            ->with('success', "Proveedor '{$proveedor->razon_social}' actualizado exitosamente.");
    }

    /**
     * CU19: Baja lógica / reactivación con validación de deudas pendientes.
     */
    public function toggleStatus($id)
    {
        $proveedor = Proveedor::findOrFail($id);

        if ($proveedor->estado) {
            // Validar si tiene compras a crédito pendientes (CU19 paso 5.2)
            $deudaPendiente = NotaCompra::where('id_proveedor', $proveedor->id_proveedor)
                ->where('tipo_pago', 'Crédito')
                ->where('estado_pago', 'Pendiente')
                ->exists();

            if ($deudaPendiente) {
                return back()->with('error', "No se puede desactivar al proveedor '{$proveedor->razon_social}' porque mantiene facturas a crédito pendientes de liquidación.");
            }
        }

        $proveedor->estado = !$proveedor->estado;
        $proveedor->save();

        $accion = $proveedor->estado ? 'reactivado' : 'desactivado';

        Bitacora::registrar(
            'Compras / Proveedores',
            'UPDATE',
            $proveedor->id_proveedor,
            json_encode([
                'accion' => "Proveedor {$accion}",
                'razon_social' => $proveedor->razon_social,
                'nuevo_estado' => $proveedor->estado,
            ])
        );

        return back()->with('success', "Proveedor '{$proveedor->razon_social}' {$accion} exitosamente.");
    }
}
