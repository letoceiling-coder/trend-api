<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Console\Command;
use PDO;

class TaTestMysqlInitCommand extends Command
{
    protected $signature = 'ta:test:mysql:init
                            {--show-sql : Only output SQL for manual execution}
                            {--allow-remote : Allow user@\'%\' (otherwise only localhost/127.0.0.1)}';

    protected $description = 'Create MySQL test DB and user (from .env.testing.mysql). Uses DB_INIT_* if set; else prints SQL.';

    public function handle(): int
    {
        $envFile = base_path('.env.testing.mysql');

        if (! is_file($envFile)) {
            $this->error('File .env.testing.mysql not found. Copy from .env.testing.mysql.example first.');
            return self::FAILURE;
        }

        try {
            Dotenv::createMutable(base_path(), '.env.testing.mysql')->load();
        } catch (InvalidFileException $e) {
            $this->error('Invalid .env.testing.mysql: ' . $e->getMessage());
            return self::FAILURE;
        }

        $database = env('DB_DATABASE', 'trend_api_test');
        $username = env('DB_USERNAME', 'trend_api_test');
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');

        $allowRemote = $this->option('allow-remote')
            || filter_var(env('DB_ALLOW_REMOTE'), FILTER_VALIDATE_BOOLEAN);

        $hostParts = $this->hostPartsForSql($host, $allowRemote);

        $initUser = env('DB_INIT_USERNAME');
        $initPassword = env('DB_INIT_PASSWORD');

        $shouldOutputSql = $this->option('show-sql')
            || ($initUser === null || $initUser === '');

        $pdo = null;
        if (! $shouldOutputSql) {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
            try {
                $pdo = new PDO($dsn, $initUser, $initPassword ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (\Throwable $e) {
                $this->warn('Cannot connect as DB_INIT_USERNAME (' . $e->getMessage() . '). Outputting SQL for manual execution.');
                $shouldOutputSql = true;
            }
        }

        if ($shouldOutputSql) {
            $this->outputSqlBlock($database, $username, $hostParts);
            return self::SUCCESS;
        }

        $password = env('DB_PASSWORD', '');
        $dbId = '`' . str_replace('`', '``', $database) . '`';
        $userQuoted = $pdo->quote($username);
        $passQuoted = $pdo->quote($password);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbId} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        foreach ($hostParts as $hostPart) {
            $hostQuoted = $pdo->quote($hostPart);
            $pdo->exec("CREATE USER IF NOT EXISTS {$userQuoted}@{$hostQuoted} IDENTIFIED BY {$passQuoted}");
            $pdo->exec("GRANT ALL ON {$dbId}.* TO {$userQuoted}@{$hostQuoted}");
        }
        $pdo->exec('FLUSH PRIVILEGES');

        $this->info('Database "' . $database . '" and user "' . $username . '" created for ' . implode(', ', $hostParts) . '.');
        return self::SUCCESS;
    }

    /**
     * Host parts for CREATE USER / GRANT based on DB_HOST. By default only localhost/127.0.0.1; '%' only if allow-remote.
     *
     * @return array<int, string>
     */
    private function hostPartsForSql(string $host, bool $allowRemote): array
    {
        $normalized = strtolower(trim($host));
        $parts = [];

        if ($normalized === '127.0.0.1') {
            $parts = ['127.0.0.1', 'localhost'];
        } elseif ($normalized === 'localhost') {
            $parts = ['localhost'];
        } else {
            $parts = [$host];
        }

        if ($allowRemote) {
            $parts[] = '%';
        }

        return array_values(array_unique($parts));
    }

    private function outputSqlBlock(string $database, string $username, array $hostParts): void
    {
        $dbId = '`' . str_replace('`', '``', $database) . '`';
        $userEsc = addslashes($username);

        if ($this->option('show-sql')) {
            $this->line("CREATE DATABASE IF NOT EXISTS {$dbId} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            foreach ($hostParts as $h) {
                $hostEsc = addslashes($h);
                $this->line("CREATE USER IF NOT EXISTS '{$userEsc}'@'{$hostEsc}' IDENTIFIED BY 'YOUR_PASSWORD';");
                $this->line("GRANT ALL PRIVILEGES ON {$dbId}.* TO '{$userEsc}'@'{$hostEsc}';");
            }
            $this->line('FLUSH PRIVILEGES;');
            return;
        }

        $this->info('Run the following SQL as MySQL admin (e.g. mysql -u root -p -h ' . env('DB_HOST', '127.0.0.1') . '). Replace YOUR_PASSWORD with the desired password:');
        $this->newLine();
        $this->line("CREATE DATABASE IF NOT EXISTS {$dbId} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        foreach ($hostParts as $h) {
            $hostEsc = addslashes($h);
            $this->line("CREATE USER IF NOT EXISTS '{$userEsc}'@'{$hostEsc}' IDENTIFIED BY 'YOUR_PASSWORD';");
            $this->line("GRANT ALL PRIVILEGES ON {$dbId}.* TO '{$userEsc}'@'{$hostEsc}';");
        }
        $this->line('FLUSH PRIVILEGES;');
        $this->newLine();
        $this->line('Then set DB_USERNAME and DB_PASSWORD in .env.testing.mysql.');
    }
}
