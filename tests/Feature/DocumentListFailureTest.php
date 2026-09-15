<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentListFailureTest extends TestCase
{
    protected $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned for the same reason BiometricAuthTest pins them: InsuriVaultApiService reads these
        // in its constructor, so without them the suite reports on whoever's .env is present.
        config([
            'services.insurivault.url' => 'https://api.test',
            'services.insurivault.organization' => 'QA Organization',
            'services.insurivault.origin_host' => 'portal.test',
        ]);

        $this->baseUrl = config('services.insurivault.url');
    }

    private function signedIn()
    {
        return $this->withSession(['api_token' => 'a-valid-token', 'user_email' => 'client@example.com']);
    }

    // A 500 from the API used to redirect to logout, which destroyed a session the API had just
    // issued a token for. The client was returned to an empty login form and read it as a rejected
    // password. The token is still good, so the session has to survive a fault on the other end.
    public function test_a_failing_service_keeps_the_client_signed_in()
    {
        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response('boom', 500),
        ]);

        $response = $this->signedIn()->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee('could not be reached', false);
        $this->assertNotNull(session('api_token'));
    }

    // Http::post throws rather than returning a response when the host cannot be reached at all, so
    // an unreachable API takes a different path through the service than a 500 and has to be proved
    // separately.
    public function test_an_unreachable_service_keeps_the_client_signed_in()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $response = $this->signedIn()->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee('could not be reached', false);
        $this->assertNotNull(session('api_token'));
    }

    // The one case where logging out is right: the API refused the token, so the session really is
    // over. Keeping the client on the page would leave them looking at a document list they can no
    // longer load.
    public function test_a_refused_token_still_signs_the_client_out()
    {
        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response('', 401),
        ]);

        $response = $this->signedIn()->get(route('documents.index'));

        $response->assertRedirect(route('logout'));
    }

    // Telling a client they have no documents when the service could not be asked is worse than the
    // logout this replaced, so the failure message must not be rendered as an empty list.
    public function test_a_failure_is_not_shown_as_an_empty_document_list()
    {
        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response('boom', 503),
        ]);

        $response = $this->signedIn()->get(route('documents.index'));

        $response->assertOk();
        $response->assertDontSee('No documents found.', false);
    }

    public function test_a_successful_list_is_unchanged()
    {
        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response([
                [
                    'account' => ['id' => 1, 'name' => 'Acme', 'accountType' => 'TaxForms'],
                    'files' => [],
                ],
            ], 200),
        ]);

        $response = $this->signedIn()->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee('Acme', false);
        $this->assertNotNull(session('api_token'));
    }
}
