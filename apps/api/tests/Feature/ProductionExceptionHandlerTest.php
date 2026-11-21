<?php

namespace Tests\Feature;

use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/test-error-simulation', function () {
            throw new \RuntimeException('Database host credentials leaked in failure: secret_password');
        });

        Route::get('/api/v1/test-error-403', function () {
            abort(403, 'Akses modul ini dibatasi.');
        });

        Route::post('/api/v1/test-error-post-only', function () {
            return response()->json(['ok' => true]);
        });

        Route::get('/api/v1/test-invalid-signature', function () {
            throw new InvalidSignatureException;
        });
    }

    public function test_production_exception_handler_sanitizes_500_errors(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/test-error-simulation');

        $response->assertStatus(500)
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('error.code', 'INTERNAL_SERVER_ERROR')
            ->assertJsonPath('error.message', 'Terjadi kesalahan internal server');

        $content = $response->getContent();
        $this->assertStringNotContainsString('secret_password', $content);
        $this->assertStringNotContainsString('RuntimeException', $content);
    }

    public function test_http_exception_403_renders_canonical_envelope(): void
    {
        $response = $this->getJson('/api/v1/test-error-403');

        $response->assertStatus(403)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('FORBIDDEN', $response->json('error.code'));
        $this->assertSame('Akses modul ini dibatasi.', $response->json('error.message'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
    }

    public function test_http_exception_404_renders_canonical_envelope(): void
    {
        $response = $this->getJson('/api/v1/non-existent-route-for-handler-regression-test');

        $response->assertStatus(404)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('NOT_FOUND', $response->json('error.code'));
        $this->assertNotEmpty($response->json('error.message'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
    }

    public function test_http_exception_405_preserves_allow_header_and_renders_canonical_envelope(): void
    {
        $response = $this->getJson('/api/v1/test-error-post-only');

        $response->assertStatus(405)
            ->assertHeader('X-Request-Id')
            ->assertHeader('Allow')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('METHOD_NOT_ALLOWED', $response->json('error.code'));
        $this->assertNotEmpty($response->json('error.message'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
        $this->assertStringContainsString('POST', (string) $response->headers->get('Allow'));
    }

    public function test_invalid_signature_exception_renders_403_forbidden(): void
    {
        $response = $this->getJson('/api/v1/test-invalid-signature');

        $response->assertStatus(403)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('FORBIDDEN', $response->json('error.code'));
        $this->assertSame('Tautan tidak valid atau tanda tangan telah kedaluwarsa', $response->json('error.message'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
    }
}
