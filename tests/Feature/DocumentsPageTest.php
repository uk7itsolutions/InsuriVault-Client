<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentsPageTest extends TestCase
{
    private const CLIENT_EMAIL = 'client@example.com';

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

    private function signedInWithAccounts()
    {
        Http::fake([
            "{$this->baseUrl}/AccountFileStorage/List" => Http::response([
                [
                    'account' => ['id' => 1, 'name' => 'Acme', 'accountType' => 'TaxForms'],
                    'files' => [],
                ],
                [
                    'account' => ['id' => 2, 'name' => 'Beta', 'accountType' => 'Insurance'],
                    'files' => [],
                ],
            ], 200),
        ]);

        return $this->withSession(['api_token' => 'a-valid-token', 'user_email' => self::CLIENT_EMAIL]);
    }

    // Every account on this page is one the client has access to, so pairing their address with each
    // account name repeated the only fact the page could not be telling them anything new about. The
    // assertion has to name the pairing rather than the address alone: the address itself is still on
    // the page, in the navbar, and asserting its absence would pass just as well if that broke.
    public function test_the_account_heading_does_not_repeat_the_signed_in_email()
    {
        $response = $this->signedInWithAccounts()->get(route('documents.index'));

        $response->assertOk();
        $response->assertDontSee('Acme (' . self::CLIENT_EMAIL . ')', false);
        $response->assertDontSee('Beta (' . self::CLIENT_EMAIL . ')', false);
    }

    // The navbar is where the client confirms who they are signed in as, which is effort 126's
    // outcome. Removing the duplication must not take that with it.
    public function test_the_navbar_still_shows_who_is_signed_in()
    {
        $response = $this->signedInWithAccounts()->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee(self::CLIENT_EMAIL, false);
    }

    // The heading still has to carry what distinguishes one account from another, so neither removal
    // took the account's own name or its type badge with it. Asserting the name as the heading's whole
    // content rather than as a bare string keeps this honest: "Acme" appears elsewhere in the markup,
    // so a looser assertion would pass with the heading emptied.
    public function test_the_account_heading_still_names_the_account_and_its_type()
    {
        $response = $this->signedInWithAccounts()->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee('>Acme</h5>', false);
        $response->assertSee('>Beta</h5>', false);
        $response->assertSee('TaxForms', false);
        $response->assertSee('Insurance', false);
    }

    // "Account:" was fixed text on every heading, distinguishing nothing — the card beneath it is that
    // account's file table and its type badge sits directly below. It is the only place in the views
    // that used the string, which is what makes asserting its absence outright a fair test.
    public function test_the_account_heading_carries_no_fixed_label()
    {
        $response = $this->signedInWithAccounts()->get(route('documents.index'));

        $response->assertOk();
        $response->assertDontSee('Account:', false);
    }
}
