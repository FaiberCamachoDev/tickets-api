<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        $response = $next($request);

        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);

        $this->logToFile($request, $response, $durationMs);

        if ($this->shouldLogToDatabase($request, $response)) {
            $this->logToDatabase($request);
        }

        return $response;
    }

    private function logToFile(Request $request, Response $response, int $durationMs): void
    {
        Log::channel('api_activity')->info('API Request', [
            'method'      => $request->method(),
            'path'        => $request->path(),
            'status'      => $response->getStatusCode(),
            'user_id'     => $request->user()?->id,
            'ip'          => $request->ip(),
            'duration_ms' => $durationMs,
            'user_agent'  => $request->userAgent(),
        ]);
    }

    // Auth events y GETs autenticados no son cubiertos por los Services
    private function shouldLogToDatabase(Request $request, Response $response): bool
    {
        if (! $response->isSuccessful()) {
            return false;
        }

        if ($request->is('api/register', 'api/login', 'api/logout')) {
            return true;
        }

        return $request->isMethod('GET') && $request->user() !== null;
    }

    private function logToDatabase(Request $request): void
    {
        $action = $this->resolveAction($request);

        ActivityLog::create([
            'user_id'     => $request->user()?->id,
            'action'      => $action,
            'description' => strtoupper($request->method()).' /'.$request->path(),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => ['query' => $request->query()],
        ]);
    }

    private function resolveAction(Request $request): string
    {
        return match (true) {
            $request->is('api/register')        => 'auth_register',
            $request->is('api/login')           => 'auth_login',
            $request->is('api/logout')          => 'auth_logout',
            $request->is('api/me')              => 'auth_profile_viewed',
            $request->is('api/tickets')         => 'ticket_list_viewed',
            $request->is('api/tickets/*')       => 'ticket_viewed',
            $request->is('api/devices')         => 'device_list_viewed',
            default                             => 'api_read',
        };
    }
}
