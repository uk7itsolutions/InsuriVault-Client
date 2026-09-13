<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organization Display Name
    |--------------------------------------------------------------------------
    |
    | The name this organization is known by to the people signing in, shown
    | wherever the portal has to tell a client who to contact. It is presentation
    | only and is never sent to the API: the name InsuriVault resolves a tenant
    | by is services.insurivault.organization, which the two must be free to
    | differ from without one following the other.
    |
    */

    'organization_display_name' => env('ORGANIZATION_DISPLAY_NAME'),

];
