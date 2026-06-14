<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeoDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoIngestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.publish.secret');
        abort_if(! $secret, 500, 'Publish secret is not configured');

        $bytes = $request->getContent();
        abort_unless(
            hash_equals(hash_hmac('sha256', $bytes, $secret), (string) $request->header('X-Signature')),
            401,
            'Invalid signature'
        );

        $sha = hash('sha256', $bytes);
        GeoDatabase::updateOrCreate(['sha256' => $sha], ['bytes' => base64_encode($bytes)]);
        // Лишаємо лише найновішу.
        GeoDatabase::where('sha256', '!=', $sha)->delete();

        return response()->json(['stored_sha' => $sha]);
    }
}
