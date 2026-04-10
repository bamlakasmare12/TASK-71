<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Router;

/**
 * Internal API client that routes Livewire requests through
 * the REST API controllers with the full route middleware stack
 * (role checks, step-up verification, password-expiry enforcement,
 * model binding, validation, and audit logging) without HTTP overhead.
 *
 * Auth is inherited from the current Livewire session context.
 * All other route-level middleware (role, step-up, password.not-expired)
 * is executed to ensure parity with external API consumers.
 */
class InternalApiClient
{
    /**
     * Middleware classes that should NOT be re-executed because they
     * depend on a full HTTP kernel lifecycle or are already handled
     * by the Livewire web middleware stack.
     */
    private const SKIP_MIDDLEWARE = [
        \Illuminate\Auth\Middleware\Authenticate::class,
        'auth',
        'auth:sanctum',
    ];

    public function get(string $uri, array $query = []): array
    {
        return $this->call('GET', $uri, $query);
    }

    public function post(string $uri, array $data = []): array
    {
        return $this->call('POST', $uri, $data);
    }

    public function put(string $uri, array $data = []): array
    {
        return $this->call('PUT', $uri, $data);
    }

    public function delete(string $uri, array $data = []): array
    {
        return $this->call('DELETE', $uri, $data);
    }

    private function call(string $method, string $uri, array $data = []): array
    {
        $uri = '/api/' . ltrim($uri, '/');
        $currentRequest = request();

        $internalRequest = Request::create(
            $uri,
            $method,
            $data,
            $currentRequest->cookies->all(),
            [],
            $currentRequest->server->all(),
        );

        $internalRequest->headers->set('Accept', 'application/json');
        $internalRequest->setUserResolver(fn () => auth()->user());
        if ($currentRequest->hasSession()) {
            $internalRequest->setLaravelSession($currentRequest->session());
        }

        // Match the API route
        $router = app(Router::class);
        $route = $router->getRoutes()->match($internalRequest);
        $internalRequest->setRouteResolver(fn () => $route);
        $route->bind($internalRequest);

        // Swap the application request so controllers see the right context
        $previousRequest = app('request');
        app()->instance('request', $internalRequest);

        try {
            // Gather all route middleware and filter out auth-related ones
            // that are already enforced by the Livewire web middleware stack.
            // This keeps: role, step-up, password.not-expired, SubstituteBindings
            $allMiddleware = $router->gatherRouteMiddleware($route);
            $middleware = array_values(array_filter($allMiddleware, function ($m) {
                $name = is_string($m) ? $m : get_class($m);
                foreach (self::SKIP_MIDDLEWARE as $skip) {
                    if ($name === $skip || str_starts_with($name, $skip . ':')) {
                        return false;
                    }
                }
                return true;
            }));

            // Dispatch through the middleware pipeline then the controller
            $response = (new Pipeline(app()))
                ->send($internalRequest)
                ->through($middleware)
                ->then(fn ($req) => $route->run());

            if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
                $status = $response->getStatusCode();
                $body = json_decode($response->getContent(), true) ?? [];
            } else {
                $body = json_decode(json_encode($response), true) ?? [];
                $status = 200;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ['status' => 422, 'ok' => false, 'data' => [], 'error' => 'Validation failed.', 'message' => $e->getMessage(), 'body' => ['errors' => $e->errors()]];
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ['status' => 403, 'ok' => false, 'data' => [], 'error' => 'Forbidden.', 'message' => $e->getMessage(), 'body' => []];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ['status' => $e->getStatusCode(), 'ok' => false, 'data' => [], 'error' => $e->getMessage(), 'message' => $e->getMessage(), 'body' => []];
        } catch (\DomainException $e) {
            return ['status' => 422, 'ok' => false, 'data' => [], 'error' => $e->getMessage(), 'message' => $e->getMessage(), 'body' => []];
        } finally {
            app()->instance('request', $previousRequest);
        }

        return [
            'status' => $status,
            'ok' => $status >= 200 && $status < 300,
            'data' => $body['data'] ?? $body,
            'error' => $body['error'] ?? null,
            'message' => $body['message'] ?? null,
            'body' => $body,
        ];
    }
}
