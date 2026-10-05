<?php

namespace Database\Seeders;

use Illuminate\Support\Str;

/**
 * Password for seeded accounts. Set SEED_DEFAULT_PASSWORD to choose it.
 * In local/testing it falls back to the documented demo password; in any
 * other environment a random one is generated (and printed once by the
 * seeder output) so a well-known password never reaches production.
 */
class SeedPassword
{
    private static ?string $generated = null;

    public static function resolve(): string
    {
        $configured = env('SEED_DEFAULT_PASSWORD');
        if ($configured) {
            return $configured;
        }

        if (app()->environment('local', 'testing')) {
            return 'Passw0rd!';
        }

        if (self::$generated === null) {
            self::$generated = Str::password(16);
            fwrite(STDERR, "\n[seed] SEED_DEFAULT_PASSWORD not set; generated password for seeded users: ".self::$generated."\n");
        }

        return self::$generated;
    }
}
