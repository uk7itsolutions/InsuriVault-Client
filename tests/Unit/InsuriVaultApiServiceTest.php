<?php

namespace Tests\Unit;

use App\Services\InsuriVaultApiService;
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
}
