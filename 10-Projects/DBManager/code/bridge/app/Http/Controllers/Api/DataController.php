<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublishedSite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DataController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rawToken = (string) $request->header('X-Site-Token');
        abort_if($rawToken === '', 401, 'Missing token');

        $site = PublishedSite::where('token_hash', hash('sha256', $rawToken))->first();
        abort_unless($site, 401, 'Unknown token');

        $etag = '"'.$site->version.'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        // Без секрета підпису serve не віддає дані (інакше підпис порожнім ключем).
        // Generic 500 без тексту — публічний ендпоінт не світить стан конфігу.
        abort_if(! config('services.data.signing_secret'), 500);

        $body = json_encode($site->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, (string) config('services.data.signing_secret'));

        return response($body, 200)
            ->header('Content-Type', 'application/json')
            ->header('ETag', $etag)
            ->header('X-Signature', $signature);
    }
}
