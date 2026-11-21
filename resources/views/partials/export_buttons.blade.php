<a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
   class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm transition text-decoration-none no-print">
    <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
</a>
<a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
   class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition text-decoration-none no-print">
    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
</a>
