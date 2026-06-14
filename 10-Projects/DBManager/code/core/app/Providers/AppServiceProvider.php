<?php

namespace App\Providers;

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
        // Blade directive @svg('name') → inline SVG using the icon sprite.
        // Usage: @svg('bell') or @svg($iconVar)
        // Renders: <svg ...><use href="#i-{name}"/></svg>
        Blade::directive('svg', function (string $expression) {
            // $expression is the raw argument: a quoted literal ('bell') or a variable ($icon).
            // We output a PHP echo that calls our helper at render time so both work.
            return "<?php echo \\App\\Helpers\\SvgIcon::render({$expression}); ?>";
        });
    }
}
