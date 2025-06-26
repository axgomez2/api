<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clear {--keep-days=0 : Number of days to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Laravel log files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs');
        $keepDays = (int) $this->option('keep-days');

        if (!File::exists($logPath)) {
            $this->error('Log directory does not exist!');
            return 1;
        }

        $files = File::files($logPath);
        $deletedCount = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            $fileName = $file->getFilename();

            // Skip if not a log file
            if (!str_ends_with($fileName, '.log')) {
                continue;
            }

            // Fix: Use filemtime() instead of getCTime() to avoid Carbon issues
            $fileModified = \Carbon\Carbon::createFromTimestamp(filemtime($file->getRealPath()));
            $fileAge = now()->diffInDays($fileModified);
            $fileSize = $file->getSize();

            // If keeping days specified, only delete old files
            if ($keepDays > 0 && $fileAge < $keepDays) {
                continue;
            }

            try {
                File::delete($file->getRealPath());
                $deletedCount++;
                $totalSize += $fileSize;

                $this->info("Deleted: {$fileName} (" . $this->formatBytes($fileSize) . ")");
            } catch (\Exception $e) {
                $this->error("Failed to delete {$fileName}: " . $e->getMessage());
            }
        }

        if ($deletedCount > 0) {
            $this->info("\n✅ Successfully deleted {$deletedCount} log file(s)");
            $this->info("💾 Freed up " . $this->formatBytes($totalSize) . " of disk space");
        } else {
            $this->info("No log files found to delete.");
        }

        return 0;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
