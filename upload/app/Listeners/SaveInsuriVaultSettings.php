<?php

namespace App\Listeners;

use RachidLaasri\LaravelInstaller\Events\EnvironmentSaved;
use Illuminate\Support\Facades\File;

class SaveInsuriVaultSettings
{
    /**
     * Handle the event.
     *
     * @param  EnvironmentSaved  $event
     * @return void
     */
    public function handle(EnvironmentSaved $event)
    {
        $request = $event->getRequest();

        if ($request->has('insurivault_api_url')) {
            $envPath = base_path('.env');
            $content = File::get($envPath);

            $extraContent = "\n";
            $extraContent .= 'INSURIVAULT_API_URL="' . $request->input('insurivault_api_url') . "\"\n";
            $extraContent .= 'INSURIVAULT_ORGANIZATION="' . $request->input('insurivault_organization') . "\"\n";
            $extraContent .= 'INSURIVAULT_ORIGIN_HOST="' . $request->input('insurivault_origin_host') . "\"\n";
            $extraContent .= 'INSURIVAULT_VERIFY_SSL=' . $request->input('insurivault_verify_ssl') . "\n";
            $extraContent .= 'INSURIVAULT_API_TIMEOUT=300' . "\n";

            File::append($envPath, $extraContent);
        }
    }
}
