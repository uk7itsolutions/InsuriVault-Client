<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class InsuriVaultApiService
{
    protected $baseUrl;
    protected $originHost;

    public function __construct()
    {
        $this->baseUrl = config('services.insurivault.url');
        $this->originHost = config('services.insurivault.origin_host');
    }

    public function getToken($email, $password)
    {
        $response = Http::post("{$this->baseUrl}/UserAuthentication/GetToken", [
            'Email' => $email,
            'Password' => $password,
            'OriginHost' => $this->originHost,
        ]);

        if ($response->successful()) {
            return $response->json('token');
        }

        return null;
    }

    public function listFiles($token)
    {
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/AccountFileStorage/List", []);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    public function downloadFile($token, $accountId, $fileId, $format = 'BinaryFile')
    {
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/AccountFileStorage/Download", [
                'accountId' => $accountId,
                'fileId' => $fileId,
                'downloadFormat' => $format,
            ]);

        if ($response->successful()) {
            if ($format === 'BinaryFile') {
                return $response;
            }
            return $response->json();
        }

        return null;
    }
}
