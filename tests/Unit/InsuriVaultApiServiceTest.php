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
}
