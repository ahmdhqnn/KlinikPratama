<?php

namespace App\Providers;

use App\Database\CaseInsensitivePostgresGrammar;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\PostgresConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::resolved(function ($manager): void {
            if (method_exists($manager, 'extend')) {
                foreach ($manager->getConnections() as $connection) {
                    if ($connection instanceof PostgresConnection) {
                        $connection->setQueryGrammar(new CaseInsensitivePostgresGrammar($connection));
                    }
                }
            }
        });

        PostgresConnection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            $conn = new PostgresConnection($connection, $database, $prefix, $config);
            $conn->setQueryGrammar(new CaseInsensitivePostgresGrammar($conn));

            return $conn;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : '';

            return Limit::perMinute(5)->by(Str::transliterate($email.'|'.$request->ip()));
        });
    }
}
