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
     * The custom properties the layout writes to honour the operator's colours. A property is
     * absent whenever its setting is unset or is not a colour, which leaves the stylesheet's own
     * default standing — a value that is not a colour never reaches the document.
     */
    public static function customProperties()
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
     */
    private static function resolveColor($value)
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value) === 1) {
            return $value;
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
