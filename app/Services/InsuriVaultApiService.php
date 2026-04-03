<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsuriVaultApiService
{
    protected $baseUrl;
    protected $originHost;
    protected $verifySsl;

    public function __construct()
    {
        $this->baseUrl = config('services.insurivault.url');
        $this->originHost = config('services.insurivault.origin_host');
        $this->verifySsl = config('services.insurivault.verify_ssl');
    }

    private function http()
    {
        return Http::when(!$this->verifySsl, fn ($r) => $r->withoutVerifying());
    }

    public function getToken($email, $password)
    {
        $response = $this->http()->post("{$this->baseUrl}/UserAuthentication/GetToken", [
            'email' => $email,
            'password' => $password,
            'originHost' => $this->originHost,
        ]);

        if ($response->successful()) {
            return $response->json('token');
        }

        Log::error('InsuriVault API Login Failed', [
            'url' => "{$this->baseUrl}/UserAuthentication/GetToken",
            'status' => $response->status(),
            'body' => $response->body(),
            'email' => $email,
            'originHost' => $this->originHost,
        ]);

        return null;
    }

    public function getRegisterOptions($token)
    {
        $response = $this->http()->withToken($token)
            ->post("{$this->baseUrl}/BiometricAuthentication/RegisterOptions", [
                'originHost' => $this->originHost,
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('InsuriVault API RegisterOptions Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'originHost' => $this->originHost,
        ]);

        return null;
    }

    public function completeRegistration($token, $challenge, array $attestationRawResponse)
    {
        $response = $this->http()->withToken($token)
            ->withQueryParameters([
                'challenge' => $challenge,
                'originHost' => $this->originHost,
            ])
            ->post("{$this->baseUrl}/BiometricAuthentication/CompleteRegistration", $attestationRawResponse);

        if ($response->successful()) {
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
        $response = $this->http()->withQueryParameters([
                'originHost' => $this->originHost,
            ])
            ->post("{$this->baseUrl}/BiometricAuthentication/AssertionOptions", [
                'email' => $email,
            ]);

        if ($response->successful()) {
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
        $response = $this->http()->withQueryParameters([
                'challenge' => $challenge,
                'email' => $email,
                'originHost' => $this->originHost,
            ])
            ->post("{$this->baseUrl}/BiometricAuthentication/CompleteAssertion", $assertionRawResponse);

        if ($response->successful()) {
            return $response->json('token');
        }

        Log::error('InsuriVault API CompleteAssertion Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'email' => $email,
        ]);

        return null;
    }

    public function listFiles($token)
    {
        $response = $this->http()->withToken($token)
            ->post("{$this->baseUrl}/AccountFileStorage/List", [
                'originHost' => $this->originHost,
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('InsuriVault API ListFiles Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'originHost' => $this->originHost,
        ]);

        return null;
    }

    public function downloadFile($token, $accountId, $fileId, $format = 'BinaryFile')
    {
        $response = $this->http()->withToken($token)
            ->post("{$this->baseUrl}/AccountFileStorage/Download", [
                'accountId' => $accountId,
                'fileId' => $fileId,
                'downloadFormat' => $format,
                'originHost' => $this->originHost,
            ]);

        if ($response->successful()) {
            if ($format === 'BinaryFile') {
                return $response;
            }
            return $response->json();
        }

        Log::error('InsuriVault API DownloadFile Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'accountId' => $accountId,
            'fileId' => $fileId,
            'originHost' => $this->originHost,
        ]);

        return null;
    }
}
