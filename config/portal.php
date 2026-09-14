<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organization Display Name
    |--------------------------------------------------------------------------
    |
    | The name this organization is known by to the people signing in. It names
    | the portal itself — the navigation bar and the browser tab — and is shown
    | wherever the portal has to tell a client who to contact. It is presentation
    | only and is never sent to the API: the name InsuriVault resolves a tenant
    | by is services.insurivault.organization, which the two must be free to
    | differ from without one following the other. Unset, the portal names
    | itself InsuriVault.
    |
    */

    'organization_display_name' => env('ORGANIZATION_DISPLAY_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Base Theme
    |--------------------------------------------------------------------------
    |
    | A complete design to start from, named rather than switched on so further
    | themes can be added without retiring a setting operators already carry.
    | An unrecognised name leaves the portal as it ships, like any other value
    | here. The colours below are written over whichever theme is chosen, so a
    | base theme is a starting point rather than an alternative to them.
    |
    */

    'base_theme' => env('PORTAL_BASE_THEME'),

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | The colours an operator may set without editing a view or running a build.
    | Each is either a hex value (#0f172b) or a standard Tailwind colour name
    | (slate-900); anything else is ignored and the portal keeps its own default,
    | since a value that is not a colour must never reach the page. Unset, these
    | leave the portal exactly as it ships.
    |
    | The navigation pair travels together: setting a light background without a
    | dark text colour produces an unreadable bar, and the portal will not second
    | guess the choice.
    |
    */

    'theme' => [

        'navigation_background_color' => env('PORTAL_NAVIGATION_BACKGROUND_COLOR'),

        'navigation_text_color' => env('PORTAL_NAVIGATION_TEXT_COLOR'),

        'accent_color' => env('PORTAL_ACCENT_COLOR'),

    ],

];
