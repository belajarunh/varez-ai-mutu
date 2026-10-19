<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalTest extends TestCase
{
    public function test_portal_home_renders_the_responsive_portal_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Varez AI Mutu')
            ->assertSee('lang="id"', false)
            ->assertSee('viewport', false)
            ->assertSee('/build/assets/portal-', false);
    }

    public function test_admin_panel_requires_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }
}
