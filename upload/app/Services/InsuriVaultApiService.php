<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsuriVaultApiService
{
    protected $baseUrl;
    protected $organization;
    protected $originHost;
    protected $verifySsl;
    protected $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.insurivault.url');
        $this->organization = config('services.insurivault.organization');
        $this->originHost = config('services.insurivault.origin_host') ?: request()->getHttpHost();
        $this->verifySsl = config('services.insurivault.verify_ssl');
        $this->timeout = config('services.insurivault.timeout');
    }

    private function http()
    {
        return Http::timeout($this->timeout)
            ->when(!$this->verifySsl, fn ($r) => $r->withoutVerifying());
    }

    public function getToken($email, $password)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API getToken called', ['email' => $email]);
        }
        $response = $this->http()->post("{$this->baseUrl}/UserAuthentication/GetToken", [
            'email' => $email,
            'password' => $password,
            'organization' => $this->organization,
            'originHost' => $this->originHost,
        ]);

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API getToken success', ['token' => $response->json('token')]);
            }
            return $response->json('token');
        }

        Log::error('InsuriVault API Login Failed', [
            'url' => "{$this->baseUrl}/UserAuthentication/GetToken",
            'status' => $response->status(),
            'body' => $response->body(),
            'email' => $email,
            'organization' => $this->organization,
        ]);

        return null;
    }

    public function getRegisterOptions($token)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API getRegisterOptions called');
        }
        $response = $this->http()->withToken($token)
            ->withQueryParameters([
                'organization' => $this->organization,
                'originHost' => $this->originHost,
            ])
            ->post("{$this->baseUrl}/BiometricAuthentication/RegisterOptions");

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API getRegisterOptions success', ['response' => $response->json()]);
            }
            return $response->json();
        }

        Log::error('InsuriVault API RegisterOptions Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    public function completeRegistration($token, $challenge, array $attestationRawResponse)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API completeRegistration called', ['challenge' => $challenge]);
        }
        $response = $this->http()->withToken($token)
            ->withQueryParameters([
                'challenge' => $challenge,
                'organization' => $this->organization,
                'originHost' => $this->originHost,
            ])
            ->post("{$this->baseUrl}/BiometricAuthentication/CompleteRegistration", $attestationRawResponse);

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API completeRegistration success');
            }
            return true;
        }

        Log::error('InsuriVault API CompleteRegistration Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'challenge' => $challenge,
        ]);

        return false;
    }

    public function getAssertionOptions($email)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API getAssertionOptions called', ['email' => $email]);
        }
        $response = $this->http()
            ->withQueryParameters([
                'organization' => $this->organization,
                'originHost' => $this->originHost,
            ])
            ->withBody(json_encode($email), 'application/json')
            ->post("{$this->baseUrl}/BiometricAuthentication/AssertionOptions");

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API getAssertionOptions success', ['response' => $response->json()]);
            }
            return $response->json();
        }

        Log::error('InsuriVault API AssertionOptions Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'email' => $email,
        ]);

        return null;
    }

    public function completeAssertion($email, $challenge, array $assertionRawResponse)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API completeAssertion called', ['email' => $email, 'challenge' => $challenge]);
        }
        $response = $this->http()->withQueryParameters([
                'challenge' => $challenge,
                'email' => $email,
                'organization' => $this->organization,
                'originHost' => $this->originHost,
            ])
            ->post("{$this->baseUrl}/BiometricAuthentication/CompleteAssertion", $assertionRawResponse);

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API completeAssertion success', ['token' => $response->json('token')]);
            }
            return $response->json('token');
        }

        Log::error('InsuriVault API CompleteAssertion Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'email' => $email,
        ]);

        return null;
    }

    public function listFiles($token, $accountId = null, $year = null)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API listFiles called', ['accountId' => $accountId, 'year' => $year]);
        }

        $payload = [];
        if ($accountId !== null) {
            $payload['accountId'] = (int)$accountId;
        }
        if ($year !== null) {
            $payload['year'] = (int)$year;
        }

        $response = $this->http()->withToken($token)
            ->withBody(json_encode((object)$payload), 'application/json')
            ->post("{$this->baseUrl}/AccountFileStorage/List");

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API listFiles success', ['response' => $response->json()]);
            }
            return $response->json();
        }

        Log::error('InsuriVault API ListFiles Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    public function downloadFile($token, $accountId, $fileId, $format = 1)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API downloadFile called', [
                'accountId' => $accountId,
                'fileId' => $fileId,
                'format' => $format
            ]);
        }
        $response = $this->http()->withToken($token)
            ->post("{$this->baseUrl}/AccountFileStorage/Download", [
                'accountId' => (int)$accountId,
                'fileId' => $fileId,
                'downloadFormat' => (int)$format,
            ]);

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API downloadFile success', ['format' => $format]);
            }
            if ($format === 1) { // BinaryFile
                return $response;
            }
            return $response->json();
        }

        Log::error('InsuriVault API DownloadFile Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'accountId' => $accountId,
            'fileId' => $fileId,
        ]);

        return null;
    }
}
