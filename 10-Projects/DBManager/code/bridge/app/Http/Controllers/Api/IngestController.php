<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverPingJob;
use App\Models\PublishedSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'push_secret' => ['required', 'string', 'min:32', 'max:128'],
            'ping_url' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:0'],
            'payload' => ['required', 'array'],
        ]);

        // Атомарна монотонність: lockForUpdate, щоб конкурентні публікації того
        // самого домену не перезаписали новішу версію старішою (race check-then-write).
        $result = DB::transaction(function () use ($data) {
            $existing = PublishedSite::where('domain', $data['domain'])
                ->lockForUpdate()
                ->first();

            $newConnection = $existing
                && ! hash_equals((string) $existing->token_hash, (string) $data['token_hash']);

            if ($existing && ! $newConnection && $data['version'] < $existing->version) {
                return ['stale' => true];
            }

            if ($existing && ! $newConnection && $data['version'] === $existing->version && $existing->payload !== $data['payload']) {
                return ['stale' => true];
            }

            $site = PublishedSite::updateOrCreate(
                ['domain' => $data['domain']],
                [
                    'token_hash' => $data['token_hash'],
                    'push_secret' => $data['push_secret'],
                    'ping_url' => $data['ping_url'],
                    'version' => $data['version'],
                    'payload' => $data['payload'],
                ]
            );

            return ['stale' => false, 'id' => $site->id, 'version' => $site->version];
        });

        if ($result['stale']) {
            return response()->json(['message' => 'Stale version'], 409);
        }

        // Пінг — поза транзакцією: job не має стартувати до коміту запису.
        DeliverPingJob::dispatch($result['id']);

        return response()->json(['stored_version' => $result['version']]);
    }
}
