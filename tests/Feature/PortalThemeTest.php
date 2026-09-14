<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalThemeTest extends TestCase
{
    // Every setting is pinned to unset before each test. Without this the suite reads the
    // developer's own .env, so a machine with a base theme configured would fail the cases that
    // assert nothing is emitted — the result would depend on the machine rather than on the code.
    protected function setUp(): void
    {
        parent::setUp();

        $this->baseTheme(null);
        $this->colorScheme(null);
        $this->theme([]);
    }

    private function theme(array $theme): void
    {
        config(['portal.theme' => array_merge([
            'navigation_background_color' => null,
            'navigation_text_color' => null,
            'accent_color' => null,
        ], $theme)]);
    }

    private function baseTheme($name): void
    {
        config(['portal.base_theme' => $name]);
    }

    private function colorScheme($name): void
    {
        config(['portal.color_scheme' => $name]);
    }

    // The case every new operator hits: a blank .env must leave the portal exactly as it shipped.
    // Nothing is written into the document at all, so the stylesheet's own defaults stand and there
    // is no second place for the colours to drift from.
    public function test_an_unconfigured_portal_writes_no_theme_at_all()
    {
        config(['portal.organization_display_name' => null]);
        $this->theme([]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('<style>', false);
    }

    // The portal has to name itself something. An operator who set no display name gets the
    // product's name rather than an empty bar, in both the bar and the tab.
    public function test_an_unset_display_name_still_names_the_portal()
    {
        config(['portal.organization_display_name' => null]);

        $response = $this->get('/login');

        $response->assertSee('InsuriVault Client', false);
    }

    public function test_the_display_name_names_the_bar_and_the_tab()
    {
        config(['portal.organization_display_name' => 'Acme Insurance']);

        $response = $this->get('/login');

        $response->assertSee('<title>Acme Insurance Client</title>', false);
        $response->assertSee('>Acme Insurance</a>', false);
    }

    public function test_a_hex_colour_is_written_as_given()
    {
        $this->theme(['accent_color' => '#123ABC']);

        $response = $this->get('/login');

        $response->assertSee('--portal-accent:#123abc;', false);
    }

    // A .env file treats an unquoted # as the start of a comment, so PORTAL_ACCENT_COLOR=#123abc
    // reaches the config as an empty string and the operator's colour silently does nothing. The
    // hash is optional here so that a hex written without it cannot be eaten by the parser — the
    // documentation offers both spellings, and this is the one no quoting can break.
    public function test_a_hex_colour_may_be_written_without_its_hash()
    {
        $this->theme(['accent_color' => '123abc']);

        $response = $this->get('/login');

        $response->assertSee('--portal-accent:#123abc;', false);
    }

    // A Tailwind name resolves to the variable the stylesheet already carries, which is what lets a
    // colour change without a build. It only works because the full theme is emitted — Tailwind
    // prunes unused colour variables by default, and indigo is not otherwise used by any view.
    public function test_a_tailwind_colour_name_resolves_to_its_variable()
    {
        $this->theme(['accent_color' => 'indigo-700']);

        $response = $this->get('/login');

        $response->assertSee('--portal-accent:var(--color-indigo-700);', false);
    }

    // The value comes from a file the operator edits and lands inside a <style> block. Anything
    // that is not a colour has to fall back to the default rather than reach the document — this
    // asserts both halves, since falling back silently is only safe if the string is also absent.
    public function test_a_value_that_is_not_a_colour_falls_back_and_never_reaches_the_page()
    {
        $this->theme(['accent_color' => 'red; } body { display:none']);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('--portal-accent:', false);
        $response->assertDontSee('display:none', false);
    }

    public function test_an_unknown_palette_name_is_refused()
    {
        $this->theme(['accent_color' => 'brand-700']);

        $response = $this->get('/login');

        $response->assertDontSee('--portal-accent:', false);
    }

    // Setting the bar's text sets the tones that hang off it — the Logout link and the menu
    // button's border — so an operator changing one colour does not leave two others behind at a
    // shade that no longer suits the bar.
    public function test_the_navigation_text_colour_carries_its_muted_tones_with_it()
    {
        $this->theme(['navigation_text_color' => '#ffffff']);

        $response = $this->get('/login');

        $response->assertSee('--portal-nav-text:#ffffff;', false);
        $response->assertSee('--portal-nav-text-muted:color-mix(in oklab, #ffffff 72%, transparent);', false);
        $response->assertSee('--portal-nav-border:color-mix(in oklab, #ffffff 40%, transparent);', false);
    }

    // A base theme has to reach past the bar: the page, the cards and the text tones are what make
    // it a theme rather than a tinted navigation. If only the nav properties come through, the
    // portal renders a dark bar over a white page, which reads as broken rather than as dark.
    public function test_a_base_theme_repaints_the_whole_portal()
    {
        $this->baseTheme('dark');
        $this->theme([]);

        $response = $this->get('/login');

        $response->assertSee('--portal-page:var(--color-slate-900);', false);
        $response->assertSee('--portal-surface:var(--color-slate-800);', false);
        $response->assertSee('--portal-text:var(--color-slate-50);', false);
        $response->assertSee('--portal-nav-surface:var(--color-slate-950);', false);
        $response->assertSee('--portal-accent:var(--color-sky-500);', false);
    }

    // The ordering is the feature: a base theme is a starting point, not a choice between it and
    // the colour settings. Picking dark and then an accent must give a dark portal in that accent
    // — not a dark portal that ignores it, and not a light one. This is the assertion most likely
    // to be broken by a later change to how the two are combined.
    public function test_an_operator_colour_is_written_over_the_base_theme()
    {
        $this->baseTheme('dark');
        $this->theme(['accent_color' => 'emerald-500']);

        $response = $this->get('/login');

        $response->assertSee('--portal-accent:var(--color-emerald-500);', false);
        $response->assertDontSee('--portal-accent:var(--color-sky-500);', false);
        $response->assertSee('--portal-page:var(--color-slate-900);', false);
    }

    // Light is the portal as it ships, so naming it claims nothing and emits nothing. It exists as
    // a value an operator can write rather than as a blank they have to infer — and once colour
    // schemes land it is the mode a light scheme is applied to.
    public function test_naming_the_light_base_is_the_same_as_naming_none()
    {
        $this->baseTheme('light');
        $this->theme([]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('<style>', false);
    }

    // An accent brings tinted companions with it — the panel behind an empty state, its border,
    // its text. Those were originally mixed towards white and black, which is only correct on a
    // light portal: over dark they rendered as a pale box on a dark page. Mixing towards the
    // surface and text tokens instead means the base underneath decides, whichever it is.
    public function test_the_accents_tinted_companions_follow_the_base_beneath_them()
    {
        $this->baseTheme('dark');
        $this->theme(['accent_color' => 'emerald-500']);

        $response = $this->get('/login');

        $response->assertSee('--portal-accent-surface:color-mix(in oklab, var(--color-emerald-500) 12%, var(--portal-surface));', false);
        $response->assertSee('--portal-accent-text:color-mix(in oklab, var(--color-emerald-500) 35%, var(--portal-text));', false);
        $response->assertDontSee('12%, white', false);
    }

    public function test_an_unknown_base_theme_leaves_the_portal_as_it_ships()
    {
        $this->baseTheme('midnight');
        $this->theme([]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('<style>', false);
    }

    // The same scheme means something different on each base, which is the whole reason it is a
    // layer rather than a fourth base. Over light it takes the page and the borders, because a
    // white portal with only a coloured bar barely reads as themed. Over dark it takes the bar
    // and the accents and leaves the surfaces alone.
    public function test_a_scheme_tints_light_and_dark_differently()
    {
        $this->colorScheme('green');
        $light = $this->get('/login');

        $light->assertSee('--portal-page:color-mix(in oklab, var(--color-emerald-50) 45%, var(--color-white));', false);
        $light->assertSee('--portal-nav-surface:var(--color-emerald-900);', false);
        $light->assertSee('--portal-accent:var(--color-emerald-700);', false);

        $this->baseTheme('dark');
        $dark = $this->get('/login');

        $dark->assertSee('--portal-page:var(--color-slate-900);', false);
        $dark->assertSee('--portal-nav-surface:var(--color-emerald-950);', false);
        $dark->assertSee('--portal-accent:var(--color-emerald-400);', false);
    }

    // A scheme has to reach past the accent, or it is a name for something one setting already
    // did. These are the surfaces that make it a design rather than a tinted button.
    public function test_a_scheme_reaches_the_surfaces_and_not_only_the_accent()
    {
        $this->colorScheme('indigo');

        $response = $this->get('/login');

        $response->assertSee('--portal-surface-muted:color-mix(in oklab, var(--color-indigo-100) 60%, var(--color-white));', false);
        $response->assertSee('--portal-border:color-mix(in oklab, var(--color-indigo-200) 75%, var(--color-white));', false);
        $response->assertSee('--portal-badge:var(--color-indigo-700);', false);
    }

    // The name an operator writes and the palette it draws from are deliberately separate. A true
    // yellow is illegible as a button colour and unpleasant as a page, so yellow draws from amber
    // — the operator gets the colour they meant rather than the one they named, without having to
    // know that amber is what they wanted.
    public function test_yellow_draws_from_amber_rather_than_from_yellow()
    {
        $this->colorScheme('yellow');

        $response = $this->get('/login');

        $response->assertSee('--portal-page:color-mix(in oklab, var(--color-amber-50) 45%, var(--color-white));', false);
        $response->assertSee('--portal-accent:var(--color-amber-400);', false);
        $response->assertDontSee('--color-yellow-', false);
    }

    // The portal says "wrong" in rose — the login alert and the failure toast. A saturated red
    // theme would dress the brand in the same colour as its own error messages, so red sits a
    // step or two lighter than the shade rule the other six follow. The errors stay the loudest
    // red on the screen, which is the only way a client can still tell them apart at a glance.
    public function test_red_on_light_is_desaturated_rather_than_merely_lightened()
    {
        $this->colorScheme('red');

        $response = $this->get('/login');

        $response->assertSee('--portal-accent:color-mix(in oklab, var(--color-red-700) 82%, var(--color-slate-600));', false);
        $response->assertSee('--portal-page:color-mix(in oklab, var(--color-red-50) 55%, var(--color-white));', false);
        $response->assertDontSee('--portal-page:var(--color-red-50);', false);
    }

    // Over dark every scheme keeps the base's neutral surfaces and colours the bar, the badges
    // and the accents only. Checked across all seven rather than one, because the rule lives in
    // a shared table any scheme can override its way out of without anything noticing.
    public function test_no_scheme_tints_the_dark_background()
    {
        $this->baseTheme('dark');

        foreach (['red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'violet'] as $scheme) {
            $this->colorScheme($scheme);

            $response = $this->get('/login');

            $response->assertSee('--portal-page:var(--color-slate-900);', false);
            $response->assertSee('--portal-surface:var(--color-slate-800);', false);
            $response->assertSee('--portal-border:var(--color-slate-600);', false);
        }
    }

    // The accent is a button's background in some places and text on a card in others, and on a
    // dark portal those want opposite things: a red dark enough to carry white text cannot be
    // read as red text on a dark card. accent-on-surface mixes towards whatever the base set as
    // its text colour, so it darkens on light and lightens on dark from the same one accent.
    public function test_the_accent_used_as_text_resolves_against_the_bases_own_text_colour()
    {
        $this->baseTheme('dark');
        $this->colorScheme('red');

        $response = $this->get('/login');

        $response->assertSee('--portal-accent-on-surface:color-mix(in oklab, color-mix(in oklab, var(--color-red-600) 82%, var(--color-slate-500)) 62%, var(--portal-text));', false);
    }

    // The other half of the dark rule: leaving the surfaces alone must not leave the portal
    // untinted. The bar, the badges and the accent are what carry the hue there.
    public function test_a_scheme_still_colours_the_bar_and_the_accent_over_dark()
    {
        $this->baseTheme('dark');
        $this->colorScheme('violet');

        $response = $this->get('/login');

        $response->assertSee('--portal-nav-surface:var(--color-violet-950);', false);
        $response->assertSee('--portal-badge:var(--color-violet-600);', false);
        $response->assertSee('--portal-accent:var(--color-violet-400);', false);
    }

    // Every name in the documentation has to resolve to a scheme, in both directions: a rainbow
    // with a hole in it is worse than a shorter list, and a scheme nobody documented is one an
    // operator will never find.
    public function test_every_documented_scheme_renders()
    {
        foreach (['red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'violet'] as $scheme) {
            $this->colorScheme($scheme);

            $response = $this->get('/login');

            $response->assertStatus(200);
            $response->assertSee('--portal-accent:', false);
        }
    }

    // The stack, top to bottom, in one assertion: a dark base tinted blue, with the operator's own
    // accent over both. Each layer keeps what the next one did not claim.
    public function test_the_three_layers_stack_in_order()
    {
        $this->baseTheme('dark');
        $this->colorScheme('blue');
        $this->theme(['accent_color' => 'rose-500']);

        $response = $this->get('/login');

        $response->assertSee('--portal-page:var(--color-slate-900);', false);
        $response->assertSee('--portal-nav-surface:var(--color-blue-950);', false);
        $response->assertSee('--portal-accent:var(--color-rose-500);', false);
        $response->assertDontSee('--portal-accent:var(--color-blue-400);', false);
        $response->assertDontSee('--portal-accent:var(--color-sky-500);', false);
    }

    public function test_an_unknown_scheme_leaves_the_base_untinted()
    {
        $this->baseTheme('dark');
        $this->colorScheme('turquoise');

        $response = $this->get('/login');

        $response->assertSee('--portal-page:var(--color-slate-900);', false);
        $response->assertDontSee('emerald', false);
    }

    public function test_a_scheme_on_its_own_needs_no_base_to_be_named()
    {
        $this->colorScheme('green');

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('--portal-page:color-mix(in oklab, var(--color-emerald-50) 45%, var(--color-white));', false);
    }

    public function test_each_setting_is_independent_of_the_others()
    {
        $this->theme(['navigation_background_color' => 'zinc-950']);

        $response = $this->get('/login');

        $response->assertSee('--portal-nav-surface:var(--color-zinc-950);', false);
        $response->assertDontSee('--portal-accent:', false);
        $response->assertDontSee('--portal-nav-text:', false);
    }
}
