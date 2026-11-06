<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Usuario;
use App\Helpers\Bitacora;

class ClienteController extends Controller
{
    /**
     * Listado paginado de clientes particulares, talleres mecánicos y empresas/flotas.
     */
    public function index(Request $request)
    {
        $query = Cliente::withCount(['ventas as ventas_completadas_count' => function ($q) {
            $q->where('estado', 'Completada');
        }, 'cotizaciones'])
        ->withSum(['ventas as total_consumido' => function ($q) {
            $q->where('estado', 'Completada');
        }], 'total_venta');

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre_razon_social', 'ilike', "%{$buscar}%")
                  ->orWhere('ci_nit', 'ilike', "%{$buscar}%")
                  ->orWhere('telefono', 'ilike', "%{$buscar}%");
            });
        }

        if ($request->filled('tipo_cliente')) {
            $tipo = strtoupper(trim($request->tipo_cliente));
            $mapaTipo = [
                'PARTICULAR'       => 'Particular',
                'TALLER'           => 'Taller/Mecánico',
                'TALLER/MECÁNICO'  => 'Taller/Mecánico',
                'TALLER/MECANICO'  => 'Taller/Mecánico',
                'EMPRESA'          => 'Empresa/Flota',
                'EMPRESA/FLOTA'    => 'Empresa/Flota',
            ];
            $valorReal = $mapaTipo[$tipo] ?? $request->tipo_cliente;
            $query->where('tipo_cliente', $valorReal);
        }

        if ($request->filled('con_descuento') && $request->con_descuento === '1') {
            $query->where('descuento_frecuente', '>', 0);
        }

        $clientes = $query->orderBy('id_cliente', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Cruzar con usuarios web registrados (Rol 5) por teléfono o nombre
        $usuariosWeb = Usuario::where('id_rol', 5)->get();

        // Métricas resumen
        $stats = (object)[
            'total_clientes'   => Cliente::count(),
            'total_talleres'   => Cliente::where('tipo_cliente', 'Taller/Mecánico')->count(),
            'total_empresas'   => Cliente::where('tipo_cliente', 'Empresa/Flota')->count(),
            'total_particulares' => Cliente::where('tipo_cliente', 'Particular')->count(),
        ];

        return view('clientes.index', compact('clientes', 'usuariosWeb', 'stats'));
    }

    /**
     * Formulario de registro de nuevo cliente o taller aliado.
     */
    public function create(Request $request)
    {
        $usuarioWeb = null;
        if ($request->filled('id_usuario_web')) {
            $usuarioWeb = Usuario::where('id_rol', 5)->find($request->id_usuario_web);
        }
        return view('clientes.create', compact('usuarioWeb'));
    }

    /**
     * Guardar un nuevo cliente (soporta petición tradicional y creación rápida JSON desde POS/Cotizaciones).
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre_razon_social' => 'required|string|min:3|max:120',
            'ci_nit'              => 'nullable|string|max:30',
            'telefono'            => 'nullable|string|max:20',
            'tipo_cliente'        => 'required|string|in:Particular,Taller/Mecánico,Empresa/Flota',
            'descuento_frecuente' => 'required|numeric|min:0|max:50',
        ], [
            'nombre_razon_social.required' => 'El nombre completo o razón social del cliente es obligatorio.',
            'nombre_razon_social.min'      => 'El nombre o razón social debe tener al menos 3 caracteres.',
            'tipo_cliente.in'              => 'Seleccione una categoría de cliente válida.',
            'descuento_frecuente.max'      => 'El límite de descuento comercial no puede superar el 50%.',
        ]);

        if ($request->filled('ci_nit') && trim($request->ci_nit) !== '0000000' && trim($request->ci_nit) !== '0') {
            $existeNit = Cliente::whereRaw('LOWER(TRIM(ci_nit)) = LOWER(TRIM(?))', [$request->ci_nit])->exists();
            if ($existeNit) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Ya existe un cliente registrado con ese CI/NIT.'], 422);
                }
                return back()->withInput()->withErrors(['ci_nit' => 'Ya existe un cliente registrado con este número de CI / NIT.']);
            }
        }

        $cliente = Cliente::create([
            'nombre_razon_social' => trim($request->nombre_razon_social),
            'ci_nit'              => $request->filled('ci_nit') ? trim($request->ci_nit) : 'S/N',
            'telefono'            => $request->filled('telefono') ? trim($request->telefono) : null,
            'tipo_cliente'        => $request->tipo_cliente,
            'descuento_frecuente' => (float)$request->descuento_frecuente,
        ]);

        Bitacora::registrar(
            'Ventas / Clientes',
            'INSERT',
            $cliente->id_cliente,
            json_encode([
                'nombre_razon_social' => $cliente->nombre_razon_social,
                'ci_nit'              => $cliente->ci_nit,
                'tipo_cliente'        => $cliente->tipo_cliente,
                'descuento_frecuente' => $cliente->descuento_frecuente,
            ])
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'cliente' => $cliente,
                'message' => "Cliente '{$cliente->nombre_razon_social}' registrado exitosamente.",
            ]);
        }

        return redirect()->route('clientes.index')
            ->with('success', "Cliente '{$cliente->nombre_razon_social}' registrado exitosamente en la cartera comercial.");
    }

    /**
     * Historial detallado de compras, cotizaciones y vínculo web del cliente.
     */
    public function show($id)
    {
        $cliente = Cliente::with([
            'ventas' => function ($q) {
                $q->with(['vendedor', 'detalles.producto'])->orderByDesc('fecha_hora');
            },
            'cotizaciones' => function ($q) {
                $q->with(['usuario', 'detalles.producto'])->orderByDesc('fecha_emision');
            }
        ])->findOrFail($id);

        // Buscar cuenta web (Rol 5) asociada por teléfono o coincidencia de nombre
        $cuentaWeb = Usuario::where('id_rol', 5)
            ->where(function ($q) use ($cliente) {
                $q->whereRaw('LOWER(TRIM(nombre_completo)) = LOWER(TRIM(?))', [$cliente->nombre_razon_social]);
                if (!empty($cliente->telefono) && $cliente->telefono !== '00000000') {
                    $q->orWhere('telefono', trim($cliente->telefono));
                }
            })->first();

        $resumen = (object)[
            'total_compras'      => $cliente->ventas->where('estado', 'Completada')->sum('total_venta'),
            'total_descuentos'   => $cliente->ventas->where('estado', 'Completada')->sum('descuento_total'),
            'cantidad_ventas'    => $cliente->ventas->where('estado', 'Completada')->count(),
            'total_cotizaciones' => $cliente->cotizaciones->count(),
            'cotizaciones_vigentes' => $cliente->cotizaciones->filter(fn($c) => $c->estado_visual === 'VIGENTE')->count(),
        ];

        return view('clientes.show', compact('cliente', 'cuentaWeb', 'resumen'));
    }

    /**
     * Formulario de edición de cliente.
     */
    public function edit($id)
    {
        $cliente = Cliente::findOrFail($id);
        return view('clientes.edit', compact('cliente'));
    }

    /**
     * Actualizar datos y condición comercial del cliente.
     */
    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);

        $request->validate([
            'nombre_razon_social' => 'required|string|min:3|max:120',
            'ci_nit'              => 'nullable|string|max:30',
            'telefono'            => 'nullable|string|max:20',
            'tipo_cliente'        => 'required|string|in:Particular,Taller/Mecánico,Empresa/Flota',
            'descuento_frecuente' => 'required|numeric|min:0|max:50',
        ]);

        if ($request->filled('ci_nit') && !in_array(trim($request->ci_nit), ['0000000', '0', 'S/N'])) {
            $duplicado = Cliente::whereRaw('LOWER(TRIM(ci_nit)) = LOWER(TRIM(?))', [$request->ci_nit])
                ->where('id_cliente', '<>', $id)
                ->exists();
            if ($duplicado) {
                return back()->withInput()->withErrors(['ci_nit' => 'Otro cliente ya tiene registrado este número de CI / NIT.']);
            }
        }

        $anterior = [
            'tipo_cliente'        => $cliente->tipo_cliente,
            'descuento_frecuente' => $cliente->descuento_frecuente,
        ];

        $cliente->update([
            'nombre_razon_social' => trim($request->nombre_razon_social),
            'ci_nit'              => $request->filled('ci_nit') ? trim($request->ci_nit) : 'S/N',
            'telefono'            => $request->filled('telefono') ? trim($request->telefono) : null,
            'tipo_cliente'        => $request->tipo_cliente,
            'descuento_frecuente' => (float)$request->descuento_frecuente,
        ]);

        Bitacora::registrar(
            'Ventas / Clientes',
            'UPDATE',
            $cliente->id_cliente,
            json_encode([
                'nombre_razon_social' => $cliente->nombre_razon_social,
                'ci_nit'              => $cliente->ci_nit,
                'anterior'            => $anterior,
                'nuevo'               => [
                    'tipo_cliente'        => $cliente->tipo_cliente,
                    'descuento_frecuente' => $cliente->descuento_frecuente,
                ],
            ])
        );

        return redirect()->route('clientes.index')
            ->with('success', "Ficha comercial de '{$cliente->nombre_razon_social}' actualizada correctamente.");
    }
}

