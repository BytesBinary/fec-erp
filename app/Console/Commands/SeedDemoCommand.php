<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Seeds the demo dataset. Idempotent; never touches the test-only 2FA/MCP fixtures.
 */
class SeedDemoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'seed:demo
                            {--fresh : Drop all tables and re-run migrations first}
                            {--force : Allow running in production (never do this against real data)}';

    /**
     * @var string
     */
    protected $description = 'Seed the demo dataset (roles, students, results, clearances in every state)';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->components->error('seed:demo refuses to run in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true, '--no-interaction' => true]);

        $this->components->info('Demo dataset seeded. Every demo account uses the password "password" (see MORNING_REPORT.md).');

        return self::SUCCESS;
    }
}
