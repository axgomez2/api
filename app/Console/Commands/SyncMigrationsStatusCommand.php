<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncMigrationsStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:sync-migrations-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually marks pending migrations as ran if their tables already exist, fixing a desynchronized state.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration status synchronization...');

        // Obter o status de todas as migrações
        $this->call('migrate:status');
        $statusOutput = \Illuminate\Support\Facades\Artisan::output();

        $this->info("--- Raw migrate:status output ---");
        $this->line($statusOutput);
        $this->info("--- End raw output ---");

        $lines = explode("\n", trim($statusOutput));
        $pendingMigrations = [];

        // Extrair migrações pendentes da saída do comando
        foreach ($lines as $line) {
            // Updated logic to be more robust
            if (Str::contains($line, 'Pending')) {
                $parts = preg_split('/\s+/', $line);
                $migrationName = null;
                foreach($parts as $part) {
                    if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $part)) {
                        $migrationName = $part;
                        break;
                    }
                }

                if ($migrationName) {
                    $this->comment("Found pending migration candidate: {$migrationName}");
                    $pendingMigrations[] = $migrationName;
                }
            }
        }

        if (empty($pendingMigrations)) {
            $this->warn('No pending migrations found to process. Database might be synchronized or parsing failed.');
            return 0;
        }

        $this->info('Found ' . count($pendingMigrations) . ' pending migrations. Checking their tables...');

        $batch = DB::table('migrations')->max('batch') + 1;

        $syncedCount = 0;
        foreach ($pendingMigrations as $migration) {
            $tableName = $this->guessTableName($migration);

            if (Schema::hasTable($tableName)) {
                $this->line("  - Table '{$tableName}' exists for pending migration '{$migration}'.");

                try {
                    DB::table('migrations')->insert([
                        'migration' => $migration,
                        'batch' => $batch,
                    ]);
                    $this->info("    ✓ Marked '{$migration}' as ran.");
                    $syncedCount++;
                } catch (\Exception $e) {
                    $this->error("    ✗ Failed to mark '{$migration}' as ran. Error: " . $e->getMessage());
                }
            } else {
                $this->warn("  - Table '{$tableName}' does not exist for pending migration '{$migration}'. Skipping.");
            }
        }

        $this->info("Synchronization complete. {$syncedCount} migrations were marked as ran.");
        return 0;
    }

    /**
     * Adivinha o nome da tabela a partir do nome do arquivo da migração.
     * Ex: '2025_07_11_050541_recreate_market_analysis_table_with_country_columns' -> 'market_analysis'
     */
    private function guessTableName(string $migration): string
    {
        $parts = explode('_', $migration);
        // Remove data e 'create'/'recreate'/'add'/'from'/'to' etc.
        $keywords = ['create', 'recreate', 'add', 'from', 'to', 'in', 'table', 'with', 'columns'];
        $tableNameParts = [];
        // A partir da 5ª parte (depois da data e hora)
        for ($i = 4; $i < count($parts); $i++) {
            if (!in_array($parts[$i], $keywords)) {
                $tableNameParts[] = $parts[$i];
            }
        }
        $guessedName = implode('_', $tableNameParts);

        // Casos especiais conhecidos
        if ($migration === '2025_07_11_050541_recreate_market_analysis_table_with_country_columns') {
            return 'market_analysis';
        }
        if (Str::contains($migration, 'cache')) {
            return 'cache';
        }

        return Str::plural(implode('_', $tableNameParts));
    }
}
