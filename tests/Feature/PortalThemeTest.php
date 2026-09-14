<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalThemeTest extends TestCase
{
    private function theme(array $theme): void
    {
        config(['portal.theme' => array_merge([
            'navigation_background_color' => null,
            'navigation_text_color' => null,
            'accent_color' => null,
        ], $theme)]);
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

    public function test_each_setting_is_independent_of_the_others()
    {
        $this->theme(['navigation_background_color' => 'zinc-950']);

        $response = $this->get('/login');

        $response->assertSee('--portal-nav-surface:var(--color-zinc-950);', false);
        $response->assertDontSee('--portal-accent:', false);
        $response->assertDontSee('--portal-nav-text:', false);
    }
}
