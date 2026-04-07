<?php

namespace Tests\Feature;

use App\Services\InsuriVaultApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BiometricAuthTest extends TestCase
{
    protected $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = config('services.insurivault.url');
    }

    public function test_get_assertion_options()
    {
        Http::fake([
            "{$this->baseUrl}/BiometricAuthentication/AssertionOptions*" => function ($request) {
                $url = parse_url($request->url());
                parse_str($url['query'] ?? '', $query);
                $this->assertEquals('QA Organization', $query['organization'] ?? null);
                $this->assertEquals('localhost:8000', $query['originHost'] ?? null);
                $this->assertEquals(json_encode('test@example.com'), $request->body());
                return Http::response([
                    'challenge' => 'fake-challenge',
                    'allowCredentials' => []
                ], 200);
            },
        ]);

        $response = $this->post('/biometric/assertion-options', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['challenge' => 'fake-challenge']);
        $this->assertEquals('fake-challenge', session('assertion_challenge'));
        $this->assertEquals('test@example.com', session('assertion_email'));
    }

    public function test_complete_assertion_success()
    {
        session([
            'assertion_challenge' => 'fake-challenge',
            'assertion_email' => 'test@example.com'
        ]);

        Http::fake([
            "{$this->baseUrl}/BiometricAuthentication/CompleteAssertion*" => function ($request) {
                $url = parse_url($request->url());
                parse_str($url['query'] ?? '', $query);
                $this->assertEquals('fake-challenge', $query['challenge'] ?? null);
                $this->assertEquals('test@example.com', $query['email'] ?? null);
                $this->assertEquals('QA Organization', $query['organization'] ?? null);
                $this->assertEquals('localhost:8000', $query['originHost'] ?? null);
                return Http::response([
                    'token' => 'biometric-jwt-token'
                ], 200);
            },
        ]);

        $response = $this->post('/biometric/complete-assertion', [
            'id' => 'cred-id',
            'rawId' => 'raw-id',
            'type' => 'public-key',
            'response' => []
        ]);

        $response->assertStatus(200);
        $response->assertJson(['token' => 'biometric-jwt-token']);
        $this->assertEquals('biometric-jwt-token', session('api_token'));
        $this->assertEquals('test@example.com', session('user_email'));
    }

    public function test_get_register_options()
    {
        Http::fake([
            "{$this->baseUrl}/BiometricAuthentication/RegisterOptions*" => function ($request) {
                $url = parse_url($request->url());
                parse_str($url['query'] ?? '', $query);
                $this->assertEquals('QA Organization', $query['organization'] ?? null);
                $this->assertEquals('localhost:8000', $query['originHost'] ?? null);
                return Http::response([
                    'challenge' => 'reg-challenge',
                    'user' => ['id' => 'user-id']
                ], 200);
            },
        ]);

        $response = $this->withSession(['api_token' => 'valid-token'])
            ->post('/biometric/register-options');

        $response->assertStatus(200);
        $response->assertJson(['challenge' => 'reg-challenge']);
        $this->assertEquals('reg-challenge', session('registration_challenge'));
    }

    public function test_complete_registration_success()
    {
        session([
            'api_token' => 'valid-token',
            'registration_challenge' => 'reg-challenge'
        ]);

        Http::fake([
            "{$this->baseUrl}/BiometricAuthentication/CompleteRegistration*" => function ($request) {
                $url = parse_url($request->url());
                parse_str($url['query'] ?? '', $query);
                $this->assertEquals('reg-challenge', $query['challenge'] ?? null);
                $this->assertEquals('QA Organization', $query['organization'] ?? null);
                $this->assertEquals('localhost:8000', $query['originHost'] ?? null);
                return Http::response(null, 200);
            },
        ]);

        $response = $this->post('/biometric/complete-registration', [
            'id' => 'new-cred-id',
            'response' => []
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertNull(session('registration_challenge'));
    }
}
