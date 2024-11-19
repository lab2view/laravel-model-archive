<?php

namespace Lab2view\ModelArchive;

use Illuminate\Support\ServiceProvider;
use Lab2view\ModelArchive\Console\Commands\ArchiveModel;
use Lab2view\ModelArchive\Console\Commands\ValidateArchiveModel;

class ModelArchiveServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void {}

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish commands to archive archivable models and verify that archives is completed successfully
            $this->commands([ValidateArchiveModel::class, ArchiveModel::class]);

            // Publish config file
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('model-archive.php'),
            ], 'config');

            // Publsh Migration of archives table
            $this->publishes([
                __DIR__.'/../database/migrations/create_archive_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_archive_table.php'),
            ], 'migrations');
        }
    }
}
