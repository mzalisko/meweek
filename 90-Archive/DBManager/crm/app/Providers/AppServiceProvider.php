<?php

namespace App\Providers;

use App\Support\LocalDataRestorer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

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
        Blade::directive('svg', function (string $expression) {
            return "<?php echo \\App\\Helpers\\SvgIcon::render({$expression}); ?>";
        });

        if (app()->environment('local') && ! app()->runningInConsole()) {
            app(LocalDataRestorer::class)->restore();
        }

        // Логування подій авторизації користувачів
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            \App\Models\AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => $event->user->id,
                'action' => 'user.login',
                'new' => ['ip' => request()->ip()],
            ]);
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if ($event->user) {
                \App\Models\AuditLog::create([
                    'actor_type' => 'user',
                    'actor_id' => $event->user->id,
                    'action' => 'user.logout',
                    'new' => ['ip' => request()->ip()],
                ]);
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            \App\Models\AuditLog::create([
                'actor_type' => 'user',
                'action' => 'user.login_failed',
                'new' => [
                    'email' => $event->credentials['email'] ?? null,
                    'ip' => request()->ip(),
                ],
            ]);
        });
    }
}
