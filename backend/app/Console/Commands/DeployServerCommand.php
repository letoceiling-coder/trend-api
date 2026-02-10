<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployServerCommand extends Command
{
    protected $signature = 'deploy:server
        {--pull : Git pull in repo root before deploy}
        {--skip-composer : Skip composer install}
        {--skip-migrate : Skip migrations}
        {--skip-cache : Skip config/route/view cache}
        {--skip-frontend : Skip frontend build}
        {--skip-queue : Skip queue:restart}
        {--dry-run : Print commands only}';

    protected $description = 'Run deploy steps on this server (cd backend && php artisan deploy:server)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $backendPath = base_path();
        $repoRoot = realpath(dirname($backendPath)) ?: dirname($backendPath);
        $frontendPath = $repoRoot . DIRECTORY_SEPARATOR . 'frontend';

        if ($dryRun) {
            $this->warn('DRY RUN: commands will not be executed.');
        }

        // --- Git pull (optional) ---
        if ($this->option('pull')) {
            $this->step('Git pull', function () use ($repoRoot, $dryRun) {
                if (! is_dir($repoRoot . DIRECTORY_SEPARATOR . '.git')) {
                    $this->warn('  Not a git repo, skip.');
                    return true;
                }
                if ($dryRun) {
                    $this->line('  [dry-run] git pull');
                    return true;
                }
                $p = new Process(['git', 'pull', '--ff-only'], $repoRoot);
                $p->setTimeout(60);
                $p->run();
                if (! $p->isSuccessful()) {
                    $this->error($p->getErrorOutput() ?: $p->getOutput());
                    return false;
                }
                $this->line('  ' . trim($p->getOutput()));
                return true;
            });
            if ($this->hasFailed()) {
                return self::FAILURE;
            }
        }

        // --- Composer ---
        if (! $this->option('skip-composer')) {
            $this->step('Composer install', function () use ($backendPath, $dryRun) {
                $cmd = ['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader', '--no-dev'];
                if ($dryRun) {
                    $this->line('  [dry-run] ' . implode(' ', $cmd));
                    return true;
                }
                $p = new Process($cmd, $backendPath);
                $p->setTimeout(300);
                $p->run(fn ($type, $out) => $this->line('  ' . $out));
                return $p->isSuccessful();
            });
            if ($this->hasFailed()) {
                return self::FAILURE;
            }
        }

        // --- Migrate ---
        if (! $this->option('skip-migrate')) {
            $this->step('Migrations', function () use ($backendPath, $dryRun) {
                if ($dryRun) {
                    $this->line('  [dry-run] php artisan migrate --force');
                    return true;
                }
                $p = new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], $backendPath);
                $p->setTimeout(120);
                $p->run(fn ($type, $out) => $this->line('  ' . $out));
                return $p->isSuccessful();
            });
            if ($this->hasFailed()) {
                return self::FAILURE;
            }
        }

        // --- Cache ---
        if (! $this->option('skip-cache')) {
            foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
                $this->step($cmd, function () use ($cmd, $backendPath, $dryRun) {
                    if ($dryRun) {
                        $this->line('  [dry-run] php artisan ' . $cmd);
                        return true;
                    }
                    $p = new Process([PHP_BINARY, 'artisan', $cmd], $backendPath);
                    $p->setTimeout(60);
                    $p->run(fn ($type, $out) => $this->line('  ' . $out));
                    return $p->isSuccessful();
                });
                if ($this->hasFailed()) {
                    return self::FAILURE;
                }
            }
        }

        // --- Queue restart ---
        if (! $this->option('skip-queue')) {
            $this->step('Queue restart', function () use ($backendPath, $dryRun) {
                if ($dryRun) {
                    $this->line('  [dry-run] php artisan queue:restart');
                    return true;
                }
                $p = new Process([PHP_BINARY, 'artisan', 'queue:restart'], $backendPath);
                $p->setTimeout(30);
                $p->run(fn ($type, $out) => $this->line('  ' . $out));
                return $p->isSuccessful();
            });
            if ($this->hasFailed()) {
                return self::FAILURE;
            }
        }

        // --- Frontend build ---
        if (! $this->option('skip-frontend') && is_dir($frontendPath)) {
            $this->step('Frontend build', function () use ($frontendPath, $dryRun) {
                if ($dryRun) {
                    $this->line('  [dry-run] npm ci && npm run build');
                    return true;
                }
                $maxAttempts = 2;
                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    if ($attempt > 1) {
                        $this->line('  Retry ' . $attempt . '/' . $maxAttempts . ' (waiting 3s after file lock)...');
                        sleep(3);
                    }
                    $p = new Process(['npm', 'ci', '--prefer-offline', '--no-audit'], $frontendPath);
                    $p->setTimeout(300);
                    $p->run(fn ($type, $out) => $this->line('  ' . $out));
                    if (! $p->isSuccessful()) {
                        if ($attempt < $maxAttempts && (str_contains($p->getErrorOutput() . $p->getOutput(), 'EBUSY') || str_contains($p->getErrorOutput() . $p->getOutput(), 'resource busy'))) {
                            continue;
                        }
                        $this->line('  Tip: On Windows, if esbuild.exe is locked, close IDE/dev server or run with --skip-frontend (frontend builds on server via php artisan deploy).');
                        return false;
                    }
                    $p = new Process(['npm', 'run', 'build'], $frontendPath);
                    $p->setTimeout(300);
                    $p->run(fn ($type, $out) => $this->line('  ' . $out));
                    if (! $p->isSuccessful()) {
                        if ($attempt < $maxAttempts && (str_contains($p->getErrorOutput() . $p->getOutput(), 'EBUSY') || str_contains($p->getErrorOutput() . $p->getOutput(), 'resource busy'))) {
                            continue;
                        }
                        $this->line('  Tip: On Windows, if esbuild.exe is locked, close IDE/dev server or run with --skip-frontend.');
                        return false;
                    }
                    return true;
                }
                return false;
            });
            if ($this->hasFailed()) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        if ($this->anyStepFailed) {
            $this->error('Deploy finished with errors.');
            return self::FAILURE;
        }
        $this->info('Deploy finished successfully.');
        return self::SUCCESS;
    }

    private bool $anyStepFailed = false;

    private function step(string $label, callable $run): void
    {
        $this->line('');
        $this->line('--- ' . $label . ' ---');
        $ok = $run();
        if (! $ok) {
            $this->error('  FAILED');
            $this->anyStepFailed = true;
        }
    }

    private function hasFailed(): bool
    {
        return $this->anyStepFailed;
    }
}
