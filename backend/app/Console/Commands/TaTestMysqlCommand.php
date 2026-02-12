<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class TaTestMysqlCommand extends Command
{
    protected $signature = 'ta:test:mysql';

    protected $description = 'Run PHPUnit tests against MySQL test DB (loads .env.testing.mysql, migrate:fresh, then phpunit)';

    /** Allowed test database name (safety: never run on production DB). */
    private const ALLOWED_TEST_DATABASE = 'trend_api_test';

    public function handle(): int
    {
        $envFile = base_path('.env.testing.mysql');

        if (! is_file($envFile)) {
            $this->error('File .env.testing.mysql not found.');
            $this->line('Run: php artisan ta:test:mysql:setup');
            $this->line('Or copy manually: cp .env.testing.mysql.example .env.testing.mysql and set DB_* (DB_DATABASE=trend_api_test, DB_USERNAME, DB_PASSWORD).');
            return self::FAILURE;
        }

        try {
            Dotenv::createMutable(base_path(), '.env.testing.mysql')->load();
        } catch (InvalidFileException $e) {
            $this->error('Invalid .env.testing.mysql: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->reloadDatabaseConfigFromEnv();

        $appEnv = env('APP_ENV', '');
        if ($appEnv !== '' && $appEnv !== 'testing') {
            $this->warn('APP_ENV is "' . $appEnv . '" (not testing). PHPUnit will set APP_ENV=testing via phpunit.mysql.xml.');
        }

        $database = config('database.connections.mysql.database', env('DB_DATABASE', ''));
        if ($database !== self::ALLOWED_TEST_DATABASE) {
            $this->error('Safety: DB_DATABASE must be "' . self::ALLOWED_TEST_DATABASE . '" in .env.testing.mysql. Current: ' . ($database ?: '(empty)'));
            return self::FAILURE;
        }

        $this->info('Using database: ' . $database . ' on ' . config('database.connections.mysql.host', '127.0.0.1'));

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('MySQL connection failed: ' . $e->getMessage());
            $this->line('Check DB_HOST (use 127.0.0.1, not localhost), DB_USERNAME, DB_PASSWORD and that the DB/user exist. Run: php artisan ta:test:mysql:init');
            return self::FAILURE;
        }

        $this->info('Running migrate:fresh...');
        if ($this->call('migrate:fresh', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }

        $phpunit = base_path('vendor/bin/phpunit');
        if (! is_file($phpunit)) {
            $this->error('vendor/bin/phpunit not found. Run: composer install');
            return self::FAILURE;
        }

        $process = new Process(
            [PHP_BINARY, $phpunit, '-c', base_path('phpunit.mysql.xml'), '--testdox'],
            base_path(),
            array_merge($_ENV, [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => config('database.connections.mysql.host'),
                'DB_PORT' => config('database.connections.mysql.port'),
                'DB_DATABASE' => config('database.connections.mysql.database'),
                'DB_USERNAME' => config('database.connections.mysql.username'),
                'DB_PASSWORD' => config('database.connections.mysql.password'),
            ]),
            null,
            null
        );

        $process->setTimeout(300);
        $process->run(function (string $type, string $out): void {
            echo $out;
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    private function reloadDatabaseConfigFromEnv(): void
    {
        $mysql = config('database.connections.mysql', []);
        $mysql['database'] = env('DB_DATABASE', $mysql['database'] ?? '');
        $mysql['username'] = env('DB_USERNAME', $mysql['username'] ?? '');
        $mysql['password'] = env('DB_PASSWORD', $mysql['password'] ?? '');
        $mysql['host'] = env('DB_HOST', $mysql['host'] ?? '127.0.0.1');
        $mysql['port'] = env('DB_PORT', $mysql['port'] ?? '3306');
        Config::set('database.connections.mysql', $mysql);
        Config::set('database.default', env('DB_CONNECTION', 'mysql'));
    }
}
