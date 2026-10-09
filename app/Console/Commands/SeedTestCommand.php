<?php

namespace App\Console\Commands;

use Database\Seeders\Testing\TestSeeder;
use Illuminate\Console\Command;

/**
 * Seeds the deterministic test dataset (spec §10.1). Refuses to run in
 * production. Accounts and identifiers: Database\Seeders\Testing\TestDataset.
 */
class SeedTestCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'seed:test
                            {--fresh : Drop all tables and re-run migrations first}
                            {--force : Allow running in production (never do this against real data)}';

    /**
     * @var string
     */
    protected $description = 'Seed the deterministic test dataset (one user per role, students in known states)';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->components->error('seed:test refuses to run in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        }

        $this->call('db:seed', ['--class' => TestSeeder::class, '--force' => true, '--no-interaction' => true]);

        $this->components->info('Test dataset seeded. All accounts use the password "password".');

        return self::SUCCESS;
    }
}
