<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PhoneNumber;
use App\Models\Publication;
use App\Services\Failover\FailoverEngine;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        FailoverEngine $engine,
        SitePayloadCompiler $compiler,
        BridgePublisher $publisher,
    ): JsonResponse {
        $secret = config('services.monitoring.secret');
        abort_if(! $secret, 500, 'Monitoring secret is not configured');

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(
            hash_equals($expected, (string) $request->header('X-Signature')),
            401,
            'Invalid signature'
        );

        $data = $request->validate([
            'e164' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:down,active'],
        ]);

        $number = PhoneNumber::where('e164', $data['e164'])->first();
        if (! $number) {
            AuditLog::create([
                'actor_type' => 'webhook',
                'action' => 'webhook.unknown_number',
                'new' => ['e164' => $data['e164']],
            ]);

            return response()->json(['message' => 'Unknown number'], 422);
        }

        $sites = DB::transaction(function () use ($engine, $compiler, $number, $data) {
            $affectedSlots = $data['status'] === 'down'
                ? $engine->markNumberDown($number, 'webhook')
                : $engine->markNumberActive($number, 'webhook');

            $sites = $affectedSlots
                ->flatMap(fn ($slot) => $engine->sitesFor($slot))
                ->unique('id')
                ->values();

            $sites->each(fn ($site) => $compiler->publish($site));

            return $sites;
        });

        $sites->each(function ($site) use ($publisher) {
            $publication = Publication::where('site_id', $site->id)->latest('version')->first();
            if ($publication) {
                $publisher->push($publication);
            }
        });

        return response()->json(['affected_sites' => $sites->count()]);
    }
}
