<?php

namespace App\Http\Controllers;

use App\Services\InsuriVaultApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class DocumentController extends Controller
{
    protected $apiService;

    public function __construct(InsuriVaultApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function index()
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('DocumentController index called');
        }
        $token = Session::get('api_token');
        $accountsWithFiles = $this->apiService->listFiles($token);

        if ($accountsWithFiles === null) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::debug('DocumentController index: listFiles failed, redirecting to logout');
            }
            return redirect()->route('logout');
        }

        return view('documents.index', compact('accountsWithFiles'));
    }

    public function show($accountId, $fileId)
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('DocumentController show called', ['accountId' => $accountId, 'fileId' => $fileId]);
        }
        $token = Session::get('api_token');
        // We use EncodedString (0) to show it on the page
        $base64Content = $this->apiService->downloadFile($token, $accountId, $fileId, 0);

        if ($base64Content === null) {
            abort(404, 'File not found or error downloading.');
        }

        // We also need the original file info to know the content type
        $accountsWithFiles = $this->apiService->listFiles($token);
        $fileInfo = null;
        foreach ($accountsWithFiles as $account) {
            if ($account['account']['id'] == $accountId) {
                foreach ($account['files'] as $file) {
                    if ($file['fileId'] == $fileId) {
                        $fileInfo = $file;
                        break 2;
                    }
                }
            }
        }

        return view('documents.show', compact('base64Content', 'fileInfo', 'accountId', 'fileId'));
    }

    public function download($accountId, $fileId)
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('DocumentController download called', ['accountId' => $accountId, 'fileId' => $fileId]);
        }
        $token = Session::get('api_token');
        $response = $this->apiService->downloadFile($token, $accountId, $fileId, 1);

        if ($response === null || !$response->successful()) {
            abort(404, 'File not found or error downloading.');
        }

        return response($response->body(), 200)
            ->header('Content-Type', $response->header('Content-Type'))
            ->header('Content-Disposition', $response->header('Content-Disposition'));
    }
}
