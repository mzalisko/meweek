<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Локальне самовідновлення базових даних адмінки на свіжій БД.
 *
 * Викликається з AppServiceProvider лише в середовищі local і лише на веб-запитах.
 * Усі перевірки мають бути ідемпотентними: щойно дані вже наявні, повторного засіву
 * не відбувається. Інакше DemoDataSeeder перестворює сайти з новими auto-increment id
 * на кожному запиті й ламає посилання ?site= (крихти, «Керувати даними»).
 */
class LocalDataRestorer
{
    /** @var list<string> */
    private const BOOTSTRAP_EMAILS = [
        'admin@dbmanager.local',
        'manager@dbmanager.local',
        'viewer@dbmanager.local',
    ];

    private const DEMO_GROUP = 'Brand A';

    /** @var list<string> */
    private const DEMO_DOMAINS = ['domen.ro', 'domen.ua'];

    public function restore(): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        if ($this->needsBootstrapUsers()) {
            $this->seed(AdminUserSeeder::class);
        }

        if ($this->needsDemoData()) {
            $this->seed(DemoDataSeeder::class);
        }
    }

    public function needsBootstrapUsers(): bool
    {
        foreach (self::BOOTSTRAP_EMAILS as $email) {
            if (! User::query()
                ->where('email', $email)
                ->where('is_active', true)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    public function needsDemoData(): bool
    {
        // Лише на свіжій БД: щойно demo-група або її домени наявні, не пересіваємо.
        // Перевірка за вмістом (ключі значень) робила умову вічно істинною й
        // перестворювала сайти з новими id на кожному веб-запиті.
        return SiteGroup::query()->where('name', self::DEMO_GROUP)->doesntExist()
            && Site::query()->whereIn('domain', self::DEMO_DOMAINS)->doesntExist();
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasTable('sites')
            && Schema::hasTable('site_groups')
            && Schema::hasTable('data_values');
    }

    private function seed(string $class): void
    {
        Artisan::call('db:seed', [
            '--class' => $class,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }
}
