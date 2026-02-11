<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSH connection
    |--------------------------------------------------------------------------
    */
    'ssh_host' => env('DEPLOY_SSH_HOST'),
    'ssh_user' => env('DEPLOY_SSH_USER', 'root'),
    'ssh_port' => env('DEPLOY_SSH_PORT', 22),

    /*
    |--------------------------------------------------------------------------
    | Git
    |--------------------------------------------------------------------------
    */
    'git_remote' => env('DEPLOY_GIT_REMOTE', 'origin'),
    'git_branch' => env('DEPLOY_GIT_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Remote paths (on server)
    | When DEPLOY_REMOTE_REPO_ROOT is set, git fetch/reset runs there;
    | backend and frontend paths are still used for composer/npm.
    |--------------------------------------------------------------------------
    */
    'remote_repo_root' => env('DEPLOY_REMOTE_REPO_ROOT'),
    'remote_backend_path' => env('DEPLOY_REMOTE_BACKEND_PATH'),
    'remote_frontend_path' => env('DEPLOY_REMOTE_FRONTEND_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Remote binaries
    |--------------------------------------------------------------------------
    */
    'remote_php' => env('DEPLOY_REMOTE_PHP', '/usr/bin/php'),
    'remote_composer' => env('DEPLOY_REMOTE_COMPOSER', '/usr/local/bin/composer'),
    'remote_node' => env('DEPLOY_REMOTE_NODE', '/usr/bin/node'),
    'remote_npm' => env('DEPLOY_REMOTE_NPM', '/usr/bin/npm'),

    /*
    |--------------------------------------------------------------------------
    | Optional: web user for permissions (e.g. chown)
    |--------------------------------------------------------------------------
    */
    'remote_web_user' => env('DEPLOY_REMOTE_WEB_USER', 'www-data'),

    /*
    |--------------------------------------------------------------------------
    | Commands (overridable via env)
    |--------------------------------------------------------------------------
    */
    'run_queue_restart' => filter_var(env('DEPLOY_RUN_QUEUE_RESTART', true), FILTER_VALIDATE_BOOLEAN),
    'frontend_build_cmd' => env(
        'DEPLOY_FRONTEND_BUILD_CMD',
        'npm install && npm run build'
    ),
    'backend_composer_cmd' => env(
        'DEPLOY_BACKEND_COMPOSER_CMD',
        'composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev'
    ),
];
