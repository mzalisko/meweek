<?php

namespace App\Services\Publishing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoDatabasePublisher
{
    /** Надіслати .mmdb у bridge з HMAC і sha256. true — прийнято. */
    public function publish(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $url = (string) config('services.bridge.geodb_url');
        $secret = (string) config('services.bridge.publish_secret');
        if ($url === '' || $secret === '') {
            return false;
        }

        $bytes = (string) file_get_contents($path);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/octet-stream',
                'X-Signature' => hash_hmac('sha256', $bytes, $secret),
                'X-Geodb-Sha256' => hash('sha256', $bytes),
            ])->withBody($bytes, 'application/octet-stream')->post($url);
        } catch (\Throwable $e) {
            Log::warning('Geodb publish failed', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful();
    }
}
