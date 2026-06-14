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
        abort_unless(
            PublishedSite::where('token_hash', hash('sha256', $rawToken))->exists(),
            401,
            'Unknown token'
        );

        $db = GeoDatabase::latest('id')->first();
        abort_unless($db, 404, 'No geo database');

        $etag = '"' . $db->sha256 . '"';
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        abort_if(! config('services.data.signing_secret'), 500);

        $bytes = base64_decode($db->bytes);
        $signature = hash_hmac('sha256', $bytes, (string) config('services.data.signing_secret'));

        return response($bytes, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('ETag', $etag)
            ->header('X-Signature', $signature);
    }
}
