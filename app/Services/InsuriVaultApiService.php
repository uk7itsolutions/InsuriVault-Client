<?php

namespace App\Services;

use App\Exceptions\AuthenticationServiceException;
use App\Exceptions\DocumentServiceException;
use App\Exceptions\HostNotActiveException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsuriVaultApiService
{
    private const CALLER_NOT_ADMITTED_MESSAGE = 'Service not active for this caller.';

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

    /**
     * Restores an empty clientExtensionResults to a JSON object. PHP cannot tell an empty JSON
     * object from an empty JSON array — json_decode renders both as [] and json_encode writes that
     * back as [] — so the browser's {} becomes [] simply by passing through this portal. The API
     * binds the field into a map and refuses an array, failing the request before its action runs.
     * Only the empty case is ambiguous: a populated map decodes to an associative array and encodes
     * back as an object on its own.
     */
    private function withClientExtensionResultsAsObject(array $payload): array
    {
        if (($payload['clientExtensionResults'] ?? null) === []) {
            $payload['clientExtensionResults'] = new \stdClass();
        }

        return $payload;
    }

    /**
     * Returns the token, or null when the API refused the credentials — something the person
     * signing in can correct themselves. Every other failure throws instead, as HostNotActiveException
     * when the API admitted no host for this portal and as AuthenticationServiceException when it
     * faulted or could not be reached. None of those depend on the email address, which is what
     * makes reporting them separately safe.
     */
    public function getToken($email, $password)
    {
        if (config('app.debug')) {
            Log::debug('InsuriVault API getToken called', ['email' => $email]);
        }

        $url = "{$this->baseUrl}/UserAuthentication/GetToken";

        try {
            $response = $this->http()->post($url, [
                'email' => $email,
                'password' => $password,
                'organization' => $this->organization,
                'originHost' => $this->originHost,
            ]);
        } catch (ConnectionException $exception) {
            Log::error('InsuriVault API Login Failed', [
                'url' => $url,
                'status' => null,
                'body' => $exception->getMessage(),
                'email' => $email,
                'organization' => $this->organization,
            ]);

            throw new AuthenticationServiceException($exception->getMessage(), 0, $exception);
        }

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API getToken success', ['token' => $response->json('token')]);
            }
            return $response->json('token');
        }

        Log::error('InsuriVault API Login Failed', [
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->body(),
            'email' => $email,
            'organization' => $this->organization,
        ]);

        if ($this->hostIsNotActive($response)) {
            throw new HostNotActiveException($response->body());
        }

        if ($this->serviceCouldNotAnswer($response)) {
            throw new AuthenticationServiceException($response->body());
        }

        return null;
    }

    /**
     * True when the API admitted no host for this portal — its address is not allowlisted for the
     * organization, or no organization resolved at all. Both are settled before the API reads the
     * email address, so telling them apart on screen discloses nothing about who holds an account.
     * Every other 401 is left to read as a credential refusal: the API's "user not found" and
     * "user not allowed" bodies are on their way to becoming byte-identical to a wrong password,
     * so matching those would both break and disclose which addresses have accounts.
     */
    private function hostIsNotActive($response)
    {
        if ($response->status() === 400) {
            return true;
        }

        return $response->status() === 401
            && str_contains($response->body(), self::CALLER_NOT_ADMITTED_MESSAGE);
    }

    private function serviceCouldNotAnswer($response)
    {
        return $response->serverError();
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
            ->post("{$this->baseUrl}/BiometricAuthentication/CompleteRegistration", $this->withClientExtensionResultsAsObject($attestationRawResponse));

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
            ->post("{$this->baseUrl}/BiometricAuthentication/CompleteAssertion", $this->withClientExtensionResultsAsObject($assertionRawResponse));

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

    /**
     * Returns the caller's accounts and their files, or null when the API refused the token and the
     * session is genuinely over. A service that faulted or could not be reached throws
     * DocumentServiceException instead: the token is still good, so signing the client out would
     * destroy a working session over a problem on the other end.
     */
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

        $url = "{$this->baseUrl}/AccountFileStorage/List";

        try {
            $response = $this->http()->withToken($token)
                ->withBody(json_encode((object)$payload), 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            Log::error('InsuriVault API ListFiles Failed', [
                'url' => $url,
                'status' => null,
                'body' => $exception->getMessage(),
            ]);

            throw new DocumentServiceException($exception->getMessage(), 0, $exception);
        }

        if ($response->successful()) {
            if (config('app.debug')) {
                Log::debug('InsuriVault API listFiles success', ['response' => $response->json()]);
            }
            return $response->json();
        }

        Log::error('InsuriVault API ListFiles Failed', [
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->status() === 401) {
            return null;
        }

        throw new DocumentServiceException($response->body());
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
