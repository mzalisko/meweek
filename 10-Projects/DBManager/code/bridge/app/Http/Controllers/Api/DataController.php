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

        // Дані підписуємо per-site push_secret — тим самим секретом, що бридж
        // використовує для ping (DeliverPingJob) і що DBManager віддає плагіну в
        // конект-блобі як signing_secret. Глобальний services.data.signing_secret тут
        // НЕ підходив: плагін перевіряє підпис per-site секретом, тож глобальний підпис
        // ніколи не збігся б (і порожній секрет повертав 500 — дані не доходили).
        // Без секрета serve не віддає дані (fail-closed); generic 500 без тексту.
        $signingSecret = (string) $site->push_secret;
        abort_if($signingSecret === '', 500);

        $body = json_encode($site->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $signingSecret);

        return response($body, 200)
            ->header('Content-Type', 'application/json')
            ->header('ETag', $etag)
            ->header('X-Signature', $signature);
    }
}
