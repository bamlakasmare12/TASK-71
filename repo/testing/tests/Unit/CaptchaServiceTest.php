<?php

namespace Tests\Unit;

use App\Services\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptchaServiceTest extends TestCase
{
    private CaptchaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CaptchaService();
    }

    public function test_generate_returns_base64_image(): void
    {
        $image = $this->service->generate();
        $this->assertStringStartsWith('data:image/png;base64,', $image);
    }

    public function test_verify_returns_false_without_prior_generation(): void
    {
        $this->assertFalse($this->service->verify('anything'));
    }

    public function test_verify_is_case_insensitive(): void
    {
        // Generate CAPTCHA and get code from session
        $this->service->generate();
        $code = session('captcha_code');

        $this->assertTrue($this->service->verify(strtoupper($code)));
    }

    public function test_verify_consumes_code_single_use(): void
    {
        $this->service->generate();
        $code = session('captcha_code');

        $this->assertTrue($this->service->verify($code));
        // Second attempt with same code should fail
        $this->assertFalse($this->service->verify($code));
    }
}
