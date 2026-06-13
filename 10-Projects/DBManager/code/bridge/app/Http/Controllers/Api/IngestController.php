<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverPingJob;
use App\Models\PublishedSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.publish.secret');
        abort_if(! $secret, 500, 'Publish secret is not configured');

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(
            hash_equals($expected, (string) $request->header('X-Signature')),
            401,
            'Invalid signature'
        );

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'token_hash' => ['required', 'string', 'max:64'],
            'ping_url' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:0'],
            'payload' => ['required', 'array'],
        ]);

        $existing = PublishedSite::where('domain', $data['domain'])->first();

        if ($existing && $data['version'] <= $existing->version) {
            return response()->json(['message' => 'Stale version'], 409);
        }

        $site = PublishedSite::updateOrCreate(
            ['domain' => $data['domain']],
            [
                'token_hash' => $data['token_hash'],
                'ping_url' => $data['ping_url'],
                'version' => $data['version'],
                'payload' => $data['payload'],
            ]
        );

        DeliverPingJob::dispatch($site->id);

        return response()->json(['stored_version' => $site->version]);
    }
}
