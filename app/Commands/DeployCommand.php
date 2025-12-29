<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;

final class DeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy the application';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting deployment...');

        // Deployment logic goes here

        $this->comment('Deployment completed successfully!');

        return self::SUCCESS;
    }
}
