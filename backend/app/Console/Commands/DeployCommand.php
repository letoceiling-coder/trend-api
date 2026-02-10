<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployCommand extends Command
{
    protected $signature = 'deploy
        {--message= : Commit message (default: deploy: YYYY-MM-DD HH:MM)}
        {--branch= : Git branch (overrides DEPLOY_GIT_BRANCH)}
        {--skip-push : Skip git commit and push}
        {--skip-migrate : Skip database migrations}
        {--skip-frontend : Skip frontend build}
        {--skip-composer : Skip composer install}
        {--skip-cache : Skip config/route/view cache}
        {--skip-queue : Skip queue:restart}
        {--only=all : Stage to run: push|server|all}
        {--dry-run : Print commands without executing}
        {--force : Commit and push even when there are uncommitted changes}';

    protected $description = 'Deploy: local git push + remote git pull, composer, migrate, frontend build, caches';

    private bool $dryRun = false;
    private array $errors = [];
    private float $stepStartedAt = 0;

    /** @var bool|null true = pushed, false = skipped/failed, null = not run */
    private ?bool $localPushDone = null;
    /** @var string|null commit hash on server after update, empty = not updated/skipped */
    private ?string $serverCommitHash = null;
    /** @var bool remote stage ran (even if dry-run) */
    private bool $remoteStageRan = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        if ($this->dryRun) {
            $this->warn('DRY RUN: no commands will be executed.');
        }

        if ($fail = $this->validateConfig()) {
            $this->error('Deploy configuration is incomplete:');
            foreach ($fail as $msg) {
                $this->line('  - ' . $msg);
            }
            $this->line('See DEPLOY.md and .env (DEPLOY_* variables).');
            $this->printFinalStatus(self::FAILURE);
            return self::FAILURE;
        }

        $only = $this->option('only');
        if (! in_array($only, ['all', 'push', 'server'], true)) {
            $this->error('--only must be one of: all, push, server');
            $this->printFinalStatus(self::FAILURE);
            return self::FAILURE;
        }

        $repoRoot = $this->getRepoRoot();
        $branch = $this->option('branch') ?: config('deploy.git_branch');
        $remote = config('deploy.git_remote');

        // ----- Local stage (push) -----
        if (in_array($only, ['all', 'push'], true) && ! $this->option('skip-push')) {
            $localOk = $this->runLocalStage($repoRoot, $remote, $branch);
            $this->localPushDone = $localOk;
            if (! $localOk) {
                $this->printFinalStatus(self::FAILURE);
                return self::FAILURE;
            }
        } elseif ($only === 'push' && $this->option('skip-push')) {
            $this->line('Skipping push (--skip-push).');
        }

        // ----- Remote stage -----
        if (in_array($only, ['all', 'server'], true)) {
            $remoteOk = $this->runRemoteStage($branch, $remote);
            $this->remoteStageRan = true;
            if (! $remoteOk) {
                $this->printFinalStatus(self::FAILURE);
                return self::FAILURE;
            }
        }

        $this->printFinalStatus(self::SUCCESS);
        return self::SUCCESS;
    }

    /**
     * Print explicit final status so it's clear whether deploy succeeded and code was updated.
     */
    private function printFinalStatus(int $exitCode): void
    {
        $this->newLine();
        $this->line('----------');
        if ($exitCode === self::SUCCESS) {
            $this->info('STATUS: SUCCESS — deploy completed.');
        } else {
            $this->error('STATUS: FAILURE — deploy did not complete.');
        }

        if ($this->localPushDone !== null) {
            $this->line('Local push: ' . ($this->localPushDone ? 'yes (code pushed to remote)' : 'no'));
        }
        if ($this->remoteStageRan) {
            if ($exitCode !== self::SUCCESS) {
                $this->line('Server code: not updated (deploy step failed)');
            } elseif ($this->serverCommitHash !== null && $this->serverCommitHash !== '') {
                $this->line('Server code: updated to commit ' . $this->serverCommitHash);
            } elseif (! $this->dryRun) {
                $this->line('Server code: updated (git pull/reset applied)');
            } else {
                $this->line('Server code: [dry-run] would be updated');
            }
        }
        $this->line('----------');

        if ($exitCode === self::SUCCESS) {
            $this->line('Logs: server storage/logs, Nginx/php-fpm logs.');
        }
    }

    private function validateConfig(): array
    {
        $required = [
            'DEPLOY_SSH_HOST' => config('deploy.ssh_host'),
            'DEPLOY_REMOTE_BACKEND_PATH' => config('deploy.remote_backend_path'),
            'DEPLOY_REMOTE_FRONTEND_PATH' => config('deploy.remote_frontend_path'),
        ];
        $missing = [];
        foreach ($required as $name => $value) {
            if ($value === null || $value === '') {
                $missing[] = $name . ' is required';
            }
        }
        return $missing;
    }

    private function getRepoRoot(): string
    {
        $backendPath = base_path();
        return realpath(dirname($backendPath)) ?: dirname($backendPath);
    }

    private function getFrontendPath(): string
    {
        $repoRoot = $this->getRepoRoot();
        $frontendPath = $repoRoot . DIRECTORY_SEPARATOR . 'frontend';
        return is_dir($frontendPath) ? $frontendPath : '';
    }

    private function runLocalStage(string $repoRoot, string $remote, string $branch): bool
    {
        $this->stepStart('Local git check');
        if (! is_dir($repoRoot . DIRECTORY_SEPARATOR . '.git')) {
            $this->stepFail('Not a git repository: ' . $repoRoot);
            $this->line('  Run: git init');
            return false;
        }

        $statusProcess = new Process(['git', 'status', '--porcelain'], $repoRoot);
        $statusProcess->run();
        $porcelain = trim($statusProcess->getOutput() ?? '');
        $hasChanges = $porcelain !== '';

        $remoteProcess = new Process(['git', 'remote', 'get-url', $remote], $repoRoot);
        $remoteProcess->run();
        if (! $remoteProcess->isSuccessful() || trim($remoteProcess->getOutput()) === '') {
            $this->stepFail('Git remote "' . $remote . '" is not set.');
            $this->line('  Run: git remote add origin https://github.com/letoceiling-coder/trend-api.git');
            return false;
        }
        $this->stepOk();

        if ($hasChanges && ! $this->option('force')) {
            $this->warn('You have uncommitted changes. Commit them manually or run deploy with --force to commit and push.');
            $this->line('  git status:');
            foreach (array_slice(explode("\n", $porcelain), 0, 15) as $line) {
                $this->line('    ' . $line);
            }
            if (substr_count($porcelain, "\n") >= 15) {
                $this->line('    ...');
            }
            return false;
        }

        $message = $this->option('message') ?: 'deploy: ' . now()->format('Y-m-d H:i');
        $commands = [];
        if ($hasChanges) {
            $commands[] = ['git', 'add', '-A'];
            $commands[] = ['git', 'commit', '-m', $message];
        }
        $commands[] = ['git', 'push', $remote, $branch];

        foreach ($commands as $cmd) {
            $this->stepStart('Local: ' . implode(' ', $cmd));
            if ($this->dryRun) {
                $this->line('  [dry-run] ' . implode(' ', $cmd));
                $this->stepOk();
                continue;
            }
            $process = new Process($cmd, $repoRoot);
            $process->setTimeout(120);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->stepFail($process->getErrorOutput() ?: $process->getOutput());
                return false;
            }
            $this->stepOk();
        }
        return true;
    }

    private function runRemoteStage(string $branch, string $remote): bool
    {
        $host = config('deploy.ssh_host');
        $user = config('deploy.ssh_user');
        $port = (int) config('deploy.ssh_port');
        $backendPath = rtrim(config('deploy.remote_backend_path'), '/');
        $frontendPath = rtrim(config('deploy.remote_frontend_path'), '/');
        $repoRoot = config('deploy.remote_repo_root');
        $gitDir = $repoRoot !== null && $repoRoot !== '' ? rtrim($repoRoot, '/') : $backendPath;

        $php = config('deploy.remote_php');
        $composer = config('deploy.remote_composer');
        $npm = config('deploy.remote_npm');

        $parts = [];
        $parts[] = 'set -e';
        $parts[] = sprintf('cd %s', $this->shellArg($gitDir));
        $parts[] = sprintf('git fetch %s 2>/dev/null || true', $this->shellArg($remote));
        $parts[] = sprintf('git reset --hard %s/%s', $this->shellArg($remote), $this->shellArg($branch));

        if (! $this->option('skip-composer')) {
            $parts[] = sprintf('cd %s', $this->shellArg($backendPath));
            $parts[] = $this->buildRemoteComposerCmd();
        }

        if (! $this->option('skip-migrate')) {
            $parts[] = sprintf('cd %s', $this->shellArg($backendPath));
            $parts[] = sprintf('%s artisan migrate --force', $this->shellArg($php));
        }

        if (! $this->option('skip-cache')) {
            $parts[] = sprintf('cd %s', $this->shellArg($backendPath));
            $parts[] = sprintf('%s artisan config:cache', $this->shellArg($php));
            $parts[] = sprintf('%s artisan route:cache', $this->shellArg($php));
            $parts[] = sprintf('%s artisan view:cache', $this->shellArg($php));
        }

        if (! $this->option('skip-queue') && config('deploy.run_queue_restart')) {
            $parts[] = sprintf('cd %s', $this->shellArg($backendPath));
            $parts[] = sprintf('%s artisan queue:restart', $this->shellArg($php));
        }

        if (! $this->option('skip-frontend')) {
            $parts[] = sprintf('cd %s', $this->shellArg($frontendPath));
            $parts[] = config('deploy.frontend_build_cmd');
        }

        $parts[] = sprintf('cd %s && echo DEPLOY_COMMIT=$(git rev-parse --short HEAD)', $this->shellArg($gitDir));

        $script = implode(' && ', $parts);
        $sshCmd = $this->buildSshCommand($script);

        $this->stepStart('Remote: SSH deploy script');
        if ($this->dryRun) {
            $sshPreview = $this->buildSshCommand("echo '...'");
            $this->line('  SSH: ' . implode(' ', array_map(function ($arg) {
                return strpos($arg, ' ') !== false ? '"' . $arg . '"' : $arg;
            }, $sshPreview)));
            $this->line('  Script (summary): git fetch/reset, composer, migrate, cache, queue:restart?, frontend build');
            $this->stepOk();
            return true;
        }

        $process = new Process($sshCmd);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->stepFail($process->getErrorOutput() ?: $process->getOutput());
            $this->line('  Command: ' . implode(' ', array_map(function ($c) {
                return strlen($c) > 60 ? substr($c, 0, 60) . '...' : $c;
            }, $sshCmd)));
            return false;
        }
        $out = trim($process->getOutput());
        if (preg_match('/DEPLOY_COMMIT=(\S+)/', $out, $m)) {
            $this->serverCommitHash = $m[1];
            $out = trim(preg_replace('/\n?DEPLOY_COMMIT=\S+\n?/', "\n", $out));
        }
        if ($out !== '') {
            $this->line($out);
        }
        $this->stepOk();
        return true;
    }

    private function buildSshCommand(string $remoteScript): array
    {
        $port = (int) config('deploy.ssh_port');
        $user = config('deploy.ssh_user');
        $host = config('deploy.ssh_host');
        $args = ['ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes'];
        if ($port !== 22) {
            $args[] = '-p';
            $args[] = (string) $port;
        }
        $args[] = $user . '@' . $host;
        $args[] = $remoteScript;
        return $args;
    }

    private function buildRemoteComposerCmd(): string
    {
        $cmd = config('deploy.backend_composer_cmd');
        $composer = config('deploy.remote_composer');
        if (strpos($cmd, 'composer') === 0) {
            return $composer . ' ' . substr($cmd, 8);
        }
        return $cmd;
    }

    private function shellArg(string $s): string
    {
        return "'" . str_replace("'", "'\\''", $s) . "'";
    }

    private function stepStart(string $label): void
    {
        $this->stepStartedAt = microtime(true);
        $this->line('[' . ($this->dryRun ? 'DRY' : '...') . '] ' . $label);
    }

    private function stepOk(): void
    {
        $elapsed = round((microtime(true) - $this->stepStartedAt) * 1000);
        $this->line('  [OK]' . ($elapsed > 0 ? " ({$elapsed}ms)" : ''));
    }

    private function stepFail(string $message): void
    {
        $this->line('  [FAIL]');
        $this->error($message);
    }
}
