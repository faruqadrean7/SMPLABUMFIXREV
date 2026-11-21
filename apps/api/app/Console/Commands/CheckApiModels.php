<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class CheckApiModels extends Command
{
    protected $signature = 'check:models';

    protected $description = 'Check all Eloquent models against PostgreSQL schema';

    public function handle(): int
    {
        $models = glob(app_path('Models/*.php'));
        $this->info('Scanning '.count($models).' models...');

        $success = 0;
        $failed = 0;

        foreach ($models as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! class_exists($class)) {
                $this->warn("Class not found: {$class}");

                continue;
            }

            try {
                $instance = new $class;
                $table = $instance->getTable();
                $count = $instance->count();
                $this->line(" [OK] {$class} -> table `{$table}` (rows: {$count})");
                $success++;
            } catch (Throwable $e) {
                $this->error(" [FAIL] {$class}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Summary: {$success} OK, {$failed} Failed.");

        return $failed === 0 ? 0 : 1;
    }
}
