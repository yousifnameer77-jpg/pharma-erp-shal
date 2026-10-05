<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Dumps the PostgreSQL database with pg_dump into storage/app/backups and
 * prunes dumps older than --keep-days. Scheduled daily in routes/console.php.
 * Restore with: pg_restore -d <db> <file>
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep-days=14 : Delete backups older than this many days}';

    protected $description = 'Create a PostgreSQL backup (pg_dump custom format) and prune old ones';

    public function handle(): int
    {
        $db = config('database.connections.pgsql');

        if (config('database.default') !== 'pgsql') {
            $this->error('backup:database supports the pgsql connection only.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $file = $dir.'/'.$db['database'].'_'.now()->format('Ymd_His').'.dump';

        $process = new Process(
            ['pg_dump', '-Fc', '-h', $db['host'], '-p', (string) $db['port'], '-U', $db['username'], '-f', $file, $db['database']],
            null,
            ['PGPASSWORD' => (string) $db['password']],
            null,
            300,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($file);
            $this->error('pg_dump failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info("Backup written: {$file}");

        $cutoff = now()->subDays((int) $this->option('keep-days'))->getTimestamp();
        foreach (glob($dir.'/*.dump') ?: [] as $old) {
            if (filemtime($old) < $cutoff) {
                unlink($old);
                $this->line('Pruned '.basename($old));
            }
        }

        return self::SUCCESS;
    }
}
