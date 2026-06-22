<?php

namespace App\Console\Commands;

use App\Services\Publishing\GeoDatabasePublisher;
use Illuminate\Console\Command;

class PublishGeoDatabase extends Command
{
    protected $signature = 'geodb:publish';

    protected $description = 'Опублікувати MaxMind-базу у DataBridge';

    public function handle(GeoDatabasePublisher $publisher): int
    {
        $ok = $publisher->publish((string) config('geo.database_path'));
        $this->line($ok ? 'Geodb опубліковано' : 'Geodb НЕ опубліковано');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
