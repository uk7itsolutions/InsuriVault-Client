<?php

namespace App\Support;

class PortalTheme
{
    private const DEFAULT_PORTAL_NAME = 'InsuriVault';

    private const TAILWIND_COLOR_FAMILIES = [
        'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime',
        'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia',
        'pink', 'rose',
    ];

    private const TAILWIND_COLOR_SHADES = [
        '50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950',
    ];

    private const BASE_THEMES = [

        'light' => [],

        'dark' => [
            '--portal-page' => 'var(--color-slate-900)',
            '--portal-surface' => 'var(--color-slate-800)',
            '--portal-surface-muted' => 'var(--color-slate-700)',
            '--portal-surface-subtle' => 'var(--color-slate-700)',
            '--portal-text' => 'var(--color-slate-50)',
            '--portal-text-muted' => 'var(--color-slate-300)',
            '--portal-text-soft' => 'var(--color-slate-300)',
            '--portal-text-subtle' => 'var(--color-slate-400)',
            '--portal-border' => 'var(--color-slate-600)',
            '--portal-input-border' => 'var(--color-slate-500)',
            '--portal-badge' => 'var(--color-slate-500)',
            '--portal-badge-text' => 'var(--color-slate-50)',
            '--portal-nav-surface' => 'var(--color-slate-950)',
            '--portal-nav-text' => 'var(--color-slate-50)',
            '--portal-nav-text-muted' => 'var(--color-slate-400)',
            '--portal-nav-border' => 'var(--color-slate-600)',
            '--portal-accent' => 'var(--color-sky-500)',
            '--portal-accent-hover' => 'var(--color-sky-400)',
            '--portal-accent-ring' => 'var(--color-sky-400)',
            '--portal-accent-bright' => 'var(--color-sky-400)',
            '--portal-accent-bright-text' => 'var(--color-sky-300)',
            '--portal-accent-surface' => 'color-mix(in oklab, var(--color-sky-500) 18%, var(--color-slate-800))',
            '--portal-accent-border' => 'color-mix(in oklab, var(--color-sky-500) 45%, var(--color-slate-800))',
            '--portal-accent-text' => 'var(--color-sky-100)',
        ],

    ];

    /**
     * The name shown in the navigation bar and the browser tab. An operator who has set no display
     * name gets the product's own name rather than an empty bar.
     */
    public static function name()
    {
        $configuredName = config('portal.organization_display_name');

        return filled($configuredName) ? $configuredName : self::DEFAULT_PORTAL_NAME;
    }

    /**
     * The custom properties the layout writes: the chosen base theme's values first, then the
     * operator's own colours over the top, so a base theme is a starting point rather than a
     * choice between it and the settings. A property is absent whenever nothing has claimed it,
     * which leaves the stylesheet's own default standing — and a value that is not a colour never
     * reaches the document.
     */
    public static function customProperties()
    {
        return array_merge(self::baseThemeProperties(), self::operatorProperties());
    }

    /**
     * The values of the named base, or nothing at all when none is named or the name is not one
     * that exists. Light is the portal as it ships and so claims no properties — it is spelled out
     * rather than left implicit so that an operator can state the choice, and so that a colour
     * scheme has a mode to be applied to. An unrecognised name leaves the portal as it ships
     * rather than failing, for the same reason an unrecognised colour does.
     */
    private static function baseThemeProperties()
    {
        $name = config('portal.base_theme');

        if (!is_string($name)) {
            return [];
        }

        $name = strtolower(trim($name));

        if (!array_key_exists($name, self::BASE_THEMES)) {
            return [];
        }

        return self::BASE_THEMES[$name];
    }

    private static function operatorProperties()
    {
        $properties = [];

        $navigationBackground = self::resolveColor(config('portal.theme.navigation_background_color'));

        if ($navigationBackground !== null) {
            $properties['--portal-nav-surface'] = $navigationBackground;
        }

        $navigationText = self::resolveColor(config('portal.theme.navigation_text_color'));

        if ($navigationText !== null) {
            $properties['--portal-nav-text'] = $navigationText;
            $properties['--portal-nav-text-muted'] = self::mix($navigationText, 72, 'transparent');
            $properties['--portal-nav-border'] = self::mix($navigationText, 40, 'transparent');
        }

        $accent = self::resolveColor(config('portal.theme.accent_color'));

        if ($accent !== null) {
            $properties['--portal-accent'] = $accent;
            $properties['--portal-accent-hover'] = self::mix($accent, 88, 'black');
            $properties['--portal-accent-ring'] = self::mix($accent, 85, 'white');
            $properties['--portal-accent-bright'] = self::mix($accent, 62, 'white');
            $properties['--portal-accent-bright-text'] = self::mix($accent, 48, 'white');
            $properties['--portal-accent-surface'] = self::mix($accent, 10, 'white');
            $properties['--portal-accent-border'] = self::mix($accent, 28, 'white');
            $properties['--portal-accent-text'] = self::mix($accent, 78, 'black');
        }

        return $properties;
    }

    /**
     * Turns an operator's setting into something safe to write inside a style block — a hex value,
     * or a Tailwind palette name resolved to the variable the stylesheet already carries. Null
     * means the setting was absent or was not a colour, which the caller treats as "use the
     * default" rather than as an error: a portal must render whatever is in the file.
     *
     * The hash is optional because a .env file treats an unquoted # as the start of a comment, so
     * PORTAL_ACCENT_COLOR=#1f2937 arrives here as an empty string. Accepting 1f2937 gives that
     * operator a spelling that cannot be eaten by the parser.
     */
    private static function resolveColor($value)
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^#?((?:[0-9a-f]{3}|[0-9a-f]{6}))$/', $value, $hexMatches) === 1) {
            return '#' . $hexMatches[1];
        }

        if ($value === 'black' || $value === 'white') {
            return sprintf('var(--color-%s)', $value);
        }

        if (preg_match('/^([a-z]+)-([0-9]{2,3})$/', $value, $matches) !== 1) {
            return null;
        }

        [, $family, $shade] = $matches;

        if (!in_array($family, self::TAILWIND_COLOR_FAMILIES, true)) {
            return null;
        }

        if (!in_array($shade, self::TAILWIND_COLOR_SHADES, true)) {
            return null;
        }

        return sprintf('var(--color-%s-%s)', $family, $shade);
    }

    private static function mix($color, $percentage, $towards)
    {
        return sprintf('color-mix(in oklab, %s %d%%, %s)', $color, $percentage, $towards);
    }
}
