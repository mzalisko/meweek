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
    }
}
