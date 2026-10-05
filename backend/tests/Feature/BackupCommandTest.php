<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    public function test_backup_creates_a_dump_file(): void
    {
        if (! trim((string) shell_exec('command -v pg_dump'))) {
            $this->markTestSkipped('pg_dump not installed.');
        }

        $dir = storage_path('app/backups');
        $before = glob($dir.'/*.dump') ?: [];

        $this->artisan('backup:database')->assertSuccessful();

        $after = glob($dir.'/*.dump') ?: [];
        $new = array_diff($after, $before);
        $this->assertCount(1, $new);
        $this->assertGreaterThan(0, filesize(array_values($new)[0]));

        foreach ($new as $f) {
            unlink($f);
        }
    }
}
