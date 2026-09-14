<?php

namespace App\Support;

class PortalTheme
{
    private const DEFAULT_PORTAL_NAME = 'InsuriVault';

    private const DEFAULT_BASE = 'light';

    private const TAILWIND_COLOR_FAMILIES = [
        'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime',
        'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia',
        'pink', 'rose',
    ];

    private const TAILWIND_COLOR_SHADES = [
        '50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950',
    ];

    private const BASES = [

        'light' => [
            'surfaces' => [],
            'accent' => null,
        ],

        'dark' => [
            'surfaces' => [
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
            ],
            'accent' => 'var(--color-sky-500)',
        ],

    ];

    private const SCHEMES = [

        'red' => ['family' => 'red', 'light' => '700', 'dark' => '400'],
        'orange' => ['family' => 'orange', 'light' => '700', 'dark' => '400'],
        'yellow' => ['family' => 'amber', 'light' => '700', 'dark' => '300'],
        'green' => ['family' => 'emerald', 'light' => '700', 'dark' => '400'],
        'blue' => ['family' => 'blue', 'light' => '700', 'dark' => '400'],
        'indigo' => ['family' => 'indigo', 'light' => '700', 'dark' => '400'],
        'violet' => ['family' => 'violet', 'light' => '700', 'dark' => '400'],

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
     * The custom properties the layout writes, in the order they are meant to win: the base, then
     * the colour scheme tinting it, then the operator's own colours over both. A property is
     * absent whenever no layer claimed it, which leaves the stylesheet's own default standing —
     * and a value that is not a colour never reaches the document.
     */
    public static function customProperties()
    {
        $base = self::baseName();

        return array_merge(
            self::baseProperties($base),
            self::schemeProperties($base),
            self::operatorProperties()
        );
    }

    /**
     * The base an operator chose, or light when they chose nothing or named something that does
     * not exist. Light is a real base rather than an absence: a scheme has to know which one it is
     * tinting, and the two read very differently.
     */
    private static function baseName()
    {
        $name = config('portal.base_theme');

        if (!is_string($name)) {
            return self::DEFAULT_BASE;
        }

        $name = strtolower(trim($name));

        return array_key_exists($name, self::BASES) ? $name : self::DEFAULT_BASE;
    }

    private static function baseProperties($base)
    {
        $definition = self::BASES[$base];

        if ($definition['accent'] === null) {
            return $definition['surfaces'];
        }

        return array_merge($definition['surfaces'], self::accentFamily($definition['accent']));
    }

    /**
     * A scheme tints the base rather than replacing it: the surfaces keep the base's weight and
     * take the scheme's hue, so green over light is a pale green page and green over dark is a
     * deep one. The shades are chosen per base for that reason — a tint that reads over white is
     * not the one that reads over near-black.
     *
     * A scheme's name and the palette it draws from are separate on purpose. An operator asks for
     * yellow; a true yellow is illegible as a button colour and unpleasant as a page, so yellow
     * draws from amber. They get the colour they meant rather than the one they named.
     */
    private static function schemeProperties($base)
    {
        $name = config('portal.color_scheme');

        if (!is_string($name)) {
            return [];
        }

        $name = strtolower(trim($name));

        if (!array_key_exists($name, self::SCHEMES)) {
            return [];
        }

        $family = self::SCHEMES[$name]['family'];
        $accent = self::palette($family, self::SCHEMES[$name][$base]);

        if ($base === 'dark') {
            return array_merge([
                '--portal-page' => self::palette($family, '950'),
                '--portal-surface' => self::palette($family, '900'),
                '--portal-surface-muted' => self::palette($family, '800'),
                '--portal-surface-subtle' => self::palette($family, '800'),
                '--portal-border' => self::palette($family, '700'),
                '--portal-input-border' => self::palette($family, '600'),
                '--portal-badge' => self::palette($family, '600'),
                '--portal-nav-surface' => self::mix(self::palette($family, '950'), 65, 'black'),
                '--portal-nav-text-muted' => self::palette($family, '200'),
                '--portal-nav-border' => self::palette($family, '700'),
            ], self::accentFamily($accent));
        }

        return array_merge([
            '--portal-page' => self::palette($family, '50'),
            '--portal-surface-muted' => self::palette($family, '100'),
            '--portal-surface-subtle' => self::palette($family, '50'),
            '--portal-border' => self::palette($family, '200'),
            '--portal-input-border' => self::palette($family, '300'),
            '--portal-badge' => self::palette($family, '700'),
            '--portal-nav-surface' => self::palette($family, '900'),
            '--portal-nav-text' => self::palette($family, '50'),
            '--portal-nav-text-muted' => self::palette($family, '200'),
            '--portal-nav-border' => self::palette($family, '700'),
        ], self::accentFamily($accent));
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
            $properties = array_merge($properties, self::accentFamily($accent));
        }

        return $properties;
    }

    /**
     * The shades that hang off one accent — its hover, its focus ring, the pair used on the dark
     * bar, and the tinted panel behind an empty state. They are derived rather than settable so
     * that one colour gives a coherent family, and the tints mix towards the surface and text
     * tokens so that whichever layer set those decides what the tint lands against.
     */
    private static function accentFamily($accent)
    {
        return [
            '--portal-accent' => $accent,
            '--portal-accent-hover' => self::mix($accent, 88, 'black'),
            '--portal-accent-ring' => self::mix($accent, 85, 'white'),
            '--portal-accent-bright' => self::mix($accent, 62, 'white'),
            '--portal-accent-bright-text' => self::mix($accent, 48, 'white'),
            '--portal-accent-surface' => self::mix($accent, 12, 'var(--portal-surface)'),
            '--portal-accent-border' => self::mix($accent, 35, 'var(--portal-surface)'),
            '--portal-accent-text' => self::mix($accent, 35, 'var(--portal-text)'),
        ];
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

    private static function palette($family, $shade)
    {
        return sprintf('var(--color-%s-%s)', $family, $shade);
    }

    /**
     * Mixes towards another token rather than towards white or black wherever the result sits on a
     * surface: the base decides what that surface is, so a tint written against white turns into a
     * pale box on a dark portal. Mixing towards the token keeps the operator's accent following
     * whichever base is underneath it.
     */
    private static function mix($color, $percentage, $towards)
    {
        return sprintf('color-mix(in oklab, %s %d%%, %s)', $color, $percentage, $towards);
    }
}
