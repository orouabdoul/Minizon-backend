<?php

namespace App\Providers;

use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override the pgsql connector to support Neon's endpoint ID workaround
        // for old libpq clients that lack SNI support (e.g. XAMPP).
        // Set DB_NEON_ENDPOINT_ID in .env to activate; no-op in production.
        $this->app->bind('db.connector.pgsql', fn () => new class extends PostgresConnector {
            protected function getDsn(array $config): string
            {
                $dsn = parent::getDsn($config);
                if (! empty($config['neon_endpoint_id'])) {
                    $dsn .= ";options='endpoint=" . $config['neon_endpoint_id'] . "'";
                }
                return $dsn;
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
