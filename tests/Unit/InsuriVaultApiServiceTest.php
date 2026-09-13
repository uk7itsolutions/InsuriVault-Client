<?php

namespace Tests\Unit;

use App\Exceptions\AuthenticationServiceException;
use App\Exceptions\HostNotActiveException;
use App\Services\InsuriVaultApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsuriVaultApiServiceTest extends TestCase
{
    public function test_list_files_sends_correct_payload_when_empty()
    {
        Http::fake([
            '*/AccountFileStorage/List' => Http::response(['success' => true], 200),
        ]);

        $service = app(InsuriVaultApiService::class);
        $service->listFiles('token');

        Http::assertSent(function ($request) {
            // echo "URL: [" . $request->url() . "]\n";
            // echo "Body: [" . $request->body() . "]\n";
            return str_ends_with($request->url(), '/AccountFileStorage/List') &&
                   $request->method() === 'POST' &&
                   trim($request->body()) === '{}';
        });
    }

    public function test_list_files_sends_correct_payload_with_parameters()
    {
        Http::fake([
            '*/AccountFileStorage/List' => Http::response(['success' => true], 200),
        ]);

        $service = app(InsuriVaultApiService::class);
        $service->listFiles('token', 1, 2024);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $data['accountId'] === 1 && $data['year'] === 2024;
        });
    }

    // The browser sends clientExtensionResults as an empty object, but $request->all() hands this
    // service an empty PHP array and json_encode writes that back as []. The API binds the field
    // into a map, so the array is refused before its action runs and the portal reports only
    // "Registration failed". Asserting on the raw body is the point: a decoded assertion cannot
    // tell the two shapes apart, which is the same blindness that caused the bug.
    public function test_complete_registration_sends_empty_extension_results_as_an_object()
    {
        Http::fake([
            '*BiometricAuthentication/CompleteRegistration*' => Http::response([], 200),
        ]);

        $service = app(InsuriVaultApiService::class);
        $service->completeRegistration('token', 'challenge', [
            'id' => 'credential-id',
            'type' => 'public-key',
            'clientExtensionResults' => [],
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->body(), '"clientExtensionResults":{}')
                && !str_contains($request->body(), '"clientExtensionResults":[]');
        });
    }

    public function test_complete_assertion_sends_empty_extension_results_as_an_object()
    {
        Http::fake([
            '*BiometricAuthentication/CompleteAssertion*' => Http::response(['token' => 'jwt'], 200),
        ]);

        $service = app(InsuriVaultApiService::class);
        $service->completeAssertion('qa@example.com', 'challenge', [
            'id' => 'credential-id',
            'type' => 'public-key',
            'clientExtensionResults' => [],
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->body(), '"clientExtensionResults":{}')
                && !str_contains($request->body(), '"clientExtensionResults":[]');
        });
    }

    // A populated map is not ambiguous and must survive untouched — converting it would be a
    // different bug, and a silent one.
    public function test_populated_extension_results_are_left_alone()
    {
        Http::fake([
            '*BiometricAuthentication/CompleteRegistration*' => Http::response([], 200),
        ]);

        $service = app(InsuriVaultApiService::class);
        $service->completeRegistration('token', 'challenge', [
            'id' => 'credential-id',
            'clientExtensionResults' => ['credProps' => ['rk' => false]],
        ]);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $data['clientExtensionResults']['credProps']['rk'] === false;
        });
    }

    // The field is absent on any client that does not send it; nothing should be invented.
    public function test_absent_extension_results_are_not_added()
    {
        Http::fake([
            '*BiometricAuthentication/CompleteRegistration*' => Http::response([], 200),
        ]);

        $service = app(InsuriVaultApiService::class);
        $service->completeRegistration('token', 'challenge', ['id' => 'credential-id']);

        Http::assertSent(function ($request) {
            return !str_contains($request->body(), 'clientExtensionResults');
        });
    }

    // originHost tells the API which deployment the request speaks for, and it is how the API
    // resolves the tenant. Configuring it lets a portal running somewhere else — a local copy under
    // test, say — claim the hostname a master account is registered under. Nothing covered that,
    // which is how the feature tests came to depend on the fallback without saying so.
    public function test_configured_origin_host_is_sent_verbatim()
    {
        config(['services.insurivault.origin_host' => 'demo.insuri-vault.com']);

        Http::fake(['*UserAuthentication/GetToken*' => Http::response(['token' => 'jwt'], 200)]);

        app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');

        Http::assertSent(function ($request) {
            return json_decode($request->body(), true)['originHost'] === 'demo.insuri-vault.com';
        });
    }

    // Left empty, the service falls back to the host of the request being served, so an
    // unconfigured portal speaks for whatever hostname it is reached on.
    public function test_origin_host_falls_back_to_the_request_host_when_not_configured()
    {
        config(['services.insurivault.origin_host' => null]);

        Http::fake(['*UserAuthentication/GetToken*' => Http::response(['token' => 'jwt'], 200)]);

        app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');

        $requestHost = request()->getHttpHost();

        Http::assertSent(function ($request) use ($requestHost) {
            return json_decode($request->body(), true)['originHost'] === $requestHost;
        });
    }

    // The organisation is the other half of how the API resolves a tenant, and it is configuration
    // the tests previously read rather than set.
    public function test_configured_organization_is_sent()
    {
        config(['services.insurivault.organization' => 'Some Other Organization']);

        Http::fake(['*UserAuthentication/GetToken*' => Http::response(['token' => 'jwt'], 200)]);

        app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');

        Http::assertSent(function ($request) {
            return json_decode($request->body(), true)['organization'] === 'Some Other Organization';
        });
    }

    // A wrong password is the one failure the person signing in can correct, and the only one that
    // may keep the credentials message. The API answers it with a bare 401 and no body.
    public function test_get_token_returns_null_when_the_api_refuses_the_credentials()
    {
        Http::fake(['*UserAuthentication/GetToken*' => Http::response(null, 401)]);

        $this->assertNull(app(InsuriVaultApiService::class)->getToken('qa@example.com', 'wrong'));
    }

    // The allowlist refusal is a 401 as well, which is why the status on its own cannot decide
    // this. The API reaches it before it reads the email address, so telling it apart discloses
    // nothing about who holds an account — and it is the failure that had a correctly configured
    // portal reporting a password that was in fact right.
    public function test_get_token_throws_when_the_caller_is_not_allowlisted()
    {
        Http::fake([
            '*UserAuthentication/GetToken*' => Http::response('Service not active for this caller.', 401),
        ]);

        $this->expectException(HostNotActiveException::class);

        app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');
    }

    // Sent when neither the organization nor the origin host resolves a tenant. That is portal
    // configuration, and no email address reaches the database before it is decided, so it shares
    // the inactive-host message rather than the one for an API that could not answer.
    public function test_get_token_throws_when_the_api_cannot_resolve_the_organization()
    {
        Http::fake([
            '*UserAuthentication/GetToken*' => Http::response('Organization or valid OriginHost is required.', 400),
        ]);

        $this->expectException(HostNotActiveException::class);

        app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');
    }

    // A fault is not a refusal. Reporting it as an inactive host would send an operator to the
    // allowlist for a problem that is not there, so the two throw different types.
    public function test_get_token_throws_when_the_api_faults()
    {
        Http::fake(['*UserAuthentication/GetToken*' => Http::response('Internal server error', 500)]);

        $this->expectException(AuthenticationServiceException::class);
        $this->expectExceptionMessage('Internal server error');

        try {
            app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');
        } catch (HostNotActiveException $exception) {
            $this->fail('A server fault must not be reported as an inactive host.');
        }
    }

    // A refused connection or a timeout throws out of the HTTP client instead of returning a
    // response, so before this it left the controller uncaught and became an error page rather
    // than anything the login form could show.
    public function test_get_token_throws_when_the_api_cannot_be_reached()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(AuthenticationServiceException::class);

        app(InsuriVaultApiService::class)->getToken('qa@example.com', 'secret');
    }
}
