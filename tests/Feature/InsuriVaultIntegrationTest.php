<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsuriVaultIntegrationTest extends TestCase
{
    protected $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned for the same reason as in BiometricAuthTest: these reach the API as query
        // parameters, the tests assert on them, and inheriting them from the developer's .env made
        // the result depend on the machine rather than on the code.
        config([
            'services.insurivault.url' => 'https://api.test',
            'services.insurivault.organization' => 'QA Organization',
            'services.insurivault.origin_host' => 'portal.test',
        ]);

        $this->baseUrl = config('services.insurivault.url');
    }

    public function test_login_page_is_accessible()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Login');
    }

    public function test_successful_login()
    {
        $this->withoutMiddleware();
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => function ($request) {
                $this->assertEquals('test@example.com', $request['email']);
                $this->assertEquals('password123', $request['password']);
                $this->assertEquals('QA Organization', $request['organization']);
                $this->assertEquals('portal.test', $request['originHost']);
                return Http::response(['token' => 'fake-jwt-token'], 200);
            },
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response([], 200),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals('fake-jwt-token', session('api_token'));
        $this->assertEquals('test@example.com', session('user_email'));
    }

    public function test_failed_login()
    {
        $this->withoutMiddleware();
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response(null, 401),
        ]);

        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);
        $this->assertNull(session('api_token'));
    }

    // The bug this pins down: a portal whose address was not allowlisted was told its password was
    // wrong. The refusal happens before the API reads the email, so the password was never checked
    // and was in fact correct. The message has to send the operator to the log instead, and must
    // not quote what the API said — the login page is unauthenticated.
    public function test_a_portal_the_api_refuses_is_not_told_its_password_is_wrong()
    {
        $this->withoutMiddleware();
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response('Service not active for this caller.', 401),
        ]);

        $response = $this->post('/login', [
            'email' => 'qa@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors(['email' => 'Service not active for the calling host.']);
        $this->assertNull(session('api_token'));
    }

    // The message names the condition and nothing else. The API's own body carried the resolved
    // address and the organization until PR 53 removed them, and the login page is unauthenticated,
    // so neither may reappear here by way of the portal.
    public function test_the_refusal_message_carries_no_address_or_organization()
    {
        $this->withoutMiddleware();
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response(
                "Service not active for the current host: 203.0.113.7. Please ensure this IP is whitelisted for the organization 'QA Organization'.",
                401
            ),
        ]);

        $this->post('/login', [
            'email' => 'qa@example.com',
            'password' => 'correct-password',
        ]);

        $message = session('errors')->first('email');
        $this->assertStringNotContainsString('203.0.113.7', $message);
        $this->assertStringNotContainsString('QA Organization', $message);
    }

    // A faulted or unreachable API is not the same condition and must not claim the host is
    // unauthorised — that would send an operator to the allowlist for a problem that is not there.
    // The client is referred to the organization by the name it chose to be known by, which is
    // deliberately not the name the API resolves the tenant with.
    public function test_an_unreachable_service_refers_the_client_to_the_organization()
    {
        $this->withoutMiddleware();
        config(['portal.organization_display_name' => 'Acme Insurance']);
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response('Internal server error', 500),
        ]);

        $this->post('/login', [
            'email' => 'qa@example.com',
            'password' => 'correct-password',
        ]);

        $message = session('errors')->first('email');
        $this->assertStringContainsString('temporarily unavailable', $message);
        $this->assertStringContainsString('Acme Insurance', $message);
        $this->assertStringNotContainsString('Service not active', $message);
        $this->assertStringNotContainsString('credentials do not match', $message);
    }

    // A self-hosted portal that never set the display name would otherwise end the sentence on
    // nothing, on the one screen that has to stay legible.
    public function test_an_unset_display_name_still_leaves_a_readable_sentence()
    {
        $this->withoutMiddleware();
        config(['portal.organization_display_name' => null]);
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response('Internal server error', 500),
        ]);

        $this->post('/login', [
            'email' => 'qa@example.com',
            'password' => 'correct-password',
        ]);

        $this->assertStringContainsString('contact your administrator.', session('errors')->first('email'));
    }

    // The display name is presentation only. Sending it to the API instead of the configured
    // organization would break tenant resolution on every deployment where the two differ, which
    // is the case this configuration exists to allow.
    public function test_the_display_name_is_never_sent_to_the_api()
    {
        $this->withoutMiddleware();
        config(['portal.organization_display_name' => 'Acme Insurance']);
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response(['token' => 'jwt'], 200),
        ]);

        $this->post('/login', [
            'email' => 'qa@example.com',
            'password' => 'correct-password',
        ]);

        Http::assertSent(function ($request) {
            return !str_contains($request->body(), 'Acme Insurance')
                && json_decode($request->body(), true)['organization'] === 'QA Organization';
        });
    }

    // The login page posts as JSON for the biometric enrolment flow and renders body.error in a
    // toast, so that path carries the message too and needs the same distinction.
    public function test_the_json_login_path_reports_a_refused_portal_as_unauthorised()
    {
        $this->withoutMiddleware();
        Http::fake([
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response('Service not active for this caller.', 401),
        ]);

        $response = $this->postJson('/login', [
            'email' => 'qa@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Service not active for the calling host', $response->json('error'));
        $this->assertStringNotContainsString('credentials do not match', $response->json('error'));
    }

    public function test_document_listing()
    {
        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response([
                [
                    'account' => [
                        'id' => 1,
                        'name' => 'John Doe',
                        'isActive' => true,
                        'isDisabled' => false,
                        'creationDate' => '2025-12-13T08:05:40',
                        'updateDate' => '2026-04-05T04:09:55'
                    ],
                    'files' => [
                        [
                            'fileId' => 'file-123',
                            'originalFileName' => 'test.pdf',
                            'fileCategory' => 'Statement',
                            'year' => 2025,
                            'month' => 1,
                            'contentType' => 'application/pdf',
                            'uploadedAtUtc' => '2025-01-01T10:00:00Z'
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withSession([
            'api_token' => 'fake-token',
            'user_email' => 'john.doe@example.com'
        ])
            ->get('/');

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('test.pdf');
    }

    public function test_document_view()
    {
        $fileId = 'file-123';
        $accountId = 1;

        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/Download" => Http::response(json_encode('fake-base64-content'), 200),
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response([
                [
                    'account' => [
                        'id' => $accountId,
                        'name' => 'John Doe',
                        'isActive' => true,
                        'isDisabled' => false,
                        'creationDate' => '2025-12-13T08:05:40',
                        'updateDate' => '2026-04-05T04:09:55'
                    ],
                    'files' => [
                        [
                            'fileId' => $fileId,
                            'originalFileName' => 'test.pdf',
                            'fileCategory' => 'Statement',
                            'year' => 2025,
                            'month' => 1,
                            'contentType' => 'application/pdf',
                            'uploadedAtUtc' => '2025-01-01T10:00:00Z'
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withSession(['api_token' => 'fake-token'])
            ->get("/documents/{$accountId}/{$fileId}");

        $response->assertStatus(200);
        $response->assertSee('test.pdf');
        $response->assertViewHas('base64Content', 'fake-base64-content');
    }

    public function test_document_download()
    {
        $fileId = 'file-123';
        $accountId = 1;

        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/Download" => Http::response('binary-content', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="test.pdf"',
            ]),
        ]);

        $response = $this->withSession(['api_token' => 'fake-token'])
            ->get("/documents/{$accountId}/{$fileId}/download");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="test.pdf"');
        $this->assertEquals('binary-content', $response->getContent());
    }

    public function test_unauthenticated_user_cannot_access_documents()
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_logout()
    {
        $response = $this->withSession(['api_token' => 'fake-token'])
            ->get('/logout');

        $response->assertRedirect('/login');
        $this->assertNull(session('api_token'));
    }
}
