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
            "{$this->baseUrl}/UserAuthentication/GetToken" => Http::response(['token' => 'fake-jwt-token'], 200),
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

        $response->assertSessionHasErrors('email');
        $this->assertNull(session('api_token'));
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
