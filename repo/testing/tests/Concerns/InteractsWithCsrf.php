<?php

namespace Tests\Concerns;

trait InteractsWithCsrf
{
    protected function postWithCsrf(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post($uri, $data);
    }
}
