<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RefreshLocalDatabase extends Command
{
    protected $signature = 'drms:refresh-local
        {--force : Confirm permanent replacement of the approved local DRMS database}';

    protected $description = 'Refresh the approved local DRMS database and seed only the Level 2 administrator';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Pass --force to confirm permanent replacement of the local DRMS database.');

            return self::FAILURE;
        }

        if (! app()->environment('local')) {
            $this->error('This command may run only in the local environment.');

            return self::FAILURE;
        }

        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);

        if (
            $connectionName !== 'mysql'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['host'] ?? null) !== '127.0.0.1'
            || (string) ($connection['port'] ?? '') !== '3307'
            || ($connection['database'] ?? null) !== 'drms'
        ) {
            $this->error('Refusing to refresh a database outside the approved local MySQL target 127.0.0.1:3307/drms.');

            return self::FAILURE;
        }

        if (blank(config('drms.demo_password'))) {
            $this->error('Set DRMS_DEMO_PASSWORD in the untracked local .env before refreshing.');

            return self::FAILURE;
        }

        $refreshSentinel = storage_path('framework/drms-refreshing');
        File::ensureDirectoryExists(dirname($refreshSentinel));
        File::put($refreshSentinel, now()->toIso8601String());

        $this->warn('Refreshing 127.0.0.1:3307/drms. Existing local data will be permanently removed.');

        try {
            $this->callSilently('queue:restart');
            $this->call('down');

            if ($this->call('migrate:fresh', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            if ($this->call('db:seed', [
                '--class' => AdminUserSeeder::class,
                '--force' => true,
            ]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            $totalUsers = User::withTrashed()->count();
            $levelTwoUsers = User::query()->where('role', UserRole::LevelTwo->value)->count();
            $levelOneUsers = User::query()->where('role', UserRole::LevelOne->value)->count();

            if ($totalUsers !== 1 || $levelTwoUsers !== 1 || $levelOneUsers !== 0) {
                $this->error('The refreshed database did not contain exactly one Level 2 administrator and zero Level 1 users.');

                return self::FAILURE;
            }
        } finally {
            $this->callSilently('up');
            File::delete($refreshSentinel);
        }

        $this->callSilently('queue:restart');
        $this->info('Local database refreshed. The Level 2 administrator is ready and the notification worker may resume.');

        return self::SUCCESS;
    }
}
