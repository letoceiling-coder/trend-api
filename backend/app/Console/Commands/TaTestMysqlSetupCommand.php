<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TaTestMysqlSetupCommand extends Command
{
    protected $signature = 'ta:test:mysql:setup';

    protected $description = 'Bootstrap .env.testing.mysql: copy from example and prompt DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD (interactive).';

    public function handle(): int
    {
        $envFile = base_path('.env.testing.mysql');
        $exampleFile = base_path('.env.testing.mysql.example');

        if (is_file($envFile)) {
            $this->info('Already configured.');
            $this->line('Run: php artisan ta:test:mysql:init then php artisan ta:test:mysql');
            return self::SUCCESS;
        }

        if (! is_file($exampleFile)) {
            $this->error('File .env.testing.mysql.example not found.');
            return self::FAILURE;
        }

        if (! copy($exampleFile, $envFile)) {
            $this->error('Failed to copy .env.testing.mysql.example to .env.testing.mysql');
            return self::FAILURE;
        }

        $this->info('Created .env.testing.mysql from example. Enter values (password is hidden).');

        $host = $this->ask('DB_HOST', '127.0.0.1');
        $port = $this->ask('DB_PORT', '3306');
        $username = $this->ask('DB_USERNAME');
        if ($username === null || trim($username) === '') {
            $this->error('DB_USERNAME is required.');
            return self::FAILURE;
        }
        $username = trim($username);

        $password = $this->secret('DB_PASSWORD');
        if ($password === null) {
            $this->error('DB_PASSWORD is required.');
            return self::FAILURE;
        }

        $this->updateEnvFile($envFile, [
            'DB_HOST' => trim((string) $host),
            'DB_PORT' => trim((string) $port),
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ]);

        $this->info('Saved. Next: php artisan ta:test:mysql:init then php artisan ta:test:mysql');
        return self::SUCCESS;
    }

    /**
     * Update key=value pairs in .env file. Values are written as-is; password is written quoted and escaped.
     * Never logs or outputs secret values.
     *
     * @param array<string, string> $vars
     */
    private function updateEnvFile(string $path, array $vars): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        $secretKeys = ['DB_PASSWORD'];

        foreach ($vars as $key => $value) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $content)) {
                $escaped = in_array($key, $secretKeys, true)
                    ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"'
                    : $value;
                $content = preg_replace(
                    '/^(\s*)' . preg_quote($key, '/') . '\s*=.*/m',
                    '$1' . $key . '=' . $escaped,
                    $content,
                    1
                );
            } else {
                $escaped = in_array($key, $secretKeys, true)
                    ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"'
                    : $value;
                $content = rtrim($content) . "\n" . $key . '=' . $escaped . "\n";
            }
        }

        file_put_contents($path, $content);
    }
}
