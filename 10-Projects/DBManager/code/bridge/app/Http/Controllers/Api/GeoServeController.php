<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeoDatabase;
use App\Models\PublishedSite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GeoServeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rawToken = (string) $request->header('X-Site-Token');
        abort_if($rawToken === '', 401, 'Missing token');
        $site = PublishedSite::where('token_hash', hash('sha256', $rawToken))->first();
        abort_unless($site, 401, 'Unknown token');

        $db = GeoDatabase::latest('id')->first();
        abort_unless($db, 404, 'No geo database');

        $etag = '"' . $db->sha256 . '"';
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        // Geo підписуємо тим самим per-site push_secret, що й дані (плагін перевіряє ним).
        $signingSecret = (string) $site->push_secret;
        abort_if($signingSecret === '', 500);

        $bytes = base64_decode($db->bytes);
        $signature = hash_hmac('sha256', $bytes, $signingSecret);

        return response($bytes, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('ETag', $etag)
            ->header('X-Signature', $signature);
    }
}
