<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DeploySmartDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smartdb:deploy 
                            {--seed : Cargar datos iniciales de catalogo, usuarios y pruebas (Poblacion.sql)}
                            {--force : Ejecutar sin solicitar confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Despliega el esquema completo de Smart-DB (Script, Triggers, PA) en PostgreSQL (Neon Cloud / Local)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.default');
        $host = config("database.connections.{$connection}.host");
        $database = config("database.connections.{$connection}.database");

        $this->newLine();
        $this->info("===============================================================");
        $this->info("        DESPLIEGUE SMART-DB — MULTIPARTES EL TUERCA           ");
        $this->info("===============================================================");
        $this->line(" <fg=cyan>Conexión:</>  {$connection}");
        $this->line(" <fg=cyan>Host:</>      {$host}");
        $this->line(" <fg=cyan>Base Datos:</>{$database}");
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('¿Desea proceder con el despliegue del esquema Smart-DB en la base de datos configurada?', true)) {
            $this->warn('Operación cancelada por el usuario.');
            return self::SUCCESS;
        }

        $baseDir = database_path('smartdb');

        $archivos = [
            [
                'fase' => 'FASE 1/3: Tablas, Restricciones e Índices',
                'archivo' => 'Script.sql',
                'path' => $baseDir . DIRECTORY_SEPARATOR . 'Script.sql',
            ],
            [
                'fase' => 'FASE 2/3: Triggers de Stock y Auditoría',
                'archivo' => 'Triggers.sql',
                'path' => $baseDir . DIRECTORY_SEPARATOR . 'Triggers.sql',
            ],
            [
                'fase' => 'FASE 3/3: Procedimientos Almacenados (Smart-DB)',
                'archivo' => 'PA.sql',
                'path' => $baseDir . DIRECTORY_SEPARATOR . 'PA.sql',
            ],
        ];

        if ($this->option('seed')) {
            $archivos[] = [
                'fase' => 'FASE OPCIONAL: Población Inicial de Catálogo y Pruebas',
                'archivo' => 'Poblacion.sql',
                'path' => $baseDir . DIRECTORY_SEPARATOR . 'Poblacion.sql',
            ];
        }

        $pdo = DB::connection()->getPdo();
        $startTimeTotal = microtime(true);

        foreach ($archivos as $item) {
            $this->line("<fg=yellow>▶ Ejecutando {$item['fase']} ({$item['archivo']})...</>");

            if (!File::exists($item['path'])) {
                $this->error("❌ Error: Archivo no encontrado en {$item['path']}");
                return self::FAILURE;
            }

            $sql = File::get($item['path']);
            if (empty(trim($sql))) {
                $this->warn("⚠️ Archivo {$item['archivo']} está vacío, omitiendo.");
                continue;
            }

            $start = microtime(true);

            try {
                // PDO::exec permite la ejecución de bloques múltiples con comillas PL/pgSQL ($$)
                $pdo->exec($sql);
                $elapsed = round((microtime(true) - $start), 2);
                $this->info("  ✔ {$item['archivo']} ejecutado exitosamente en {$elapsed}s");
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("❌ ERROR CRÍTICO AL EJECUTAR {$item['archivo']}:");
                $this->line("  <fg=red>" . $e->getMessage() . "</>");
                $this->newLine();
                return self::FAILURE;
            }
        }

        $totalElapsed = round((microtime(true) - $startTimeTotal), 2);

        $this->newLine();
        $this->info("===============================================================");
        $this->info("  ✔ DESPLIEGUE SMART-DB COMPLETADO CON ÉXITO EN {$totalElapsed}s  ");
        $this->info("===============================================================");
        $this->newLine();

        return self::SUCCESS;
    }
}
