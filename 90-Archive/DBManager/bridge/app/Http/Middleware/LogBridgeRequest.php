<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LogBridgeRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            $this->log($request, $response->getStatusCode());
            return $response;
        } catch (HttpExceptionInterface $e) {
            $this->log($request, $e->getStatusCode());
            throw $e;
        }
    }

    private function log(Request $request, int $status): void
    {
        $rawToken = (string) $request->header('X-Site-Token');

        RequestLog::create([
            'token_hash' => $rawToken !== '' ? hash('sha256', $rawToken) : null,
            'ip'         => $request->ip(),
            'path'       => $request->path(),
            'status'     => $status,
        ]);
    }
}
