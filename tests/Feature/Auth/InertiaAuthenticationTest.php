<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\Header;
use Tests\TestCase;

class InertiaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_inertia_patch_returns_location_header_for_login(): void
    {
        $response = $this->withHeaders([
            Header::INERTIA => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->patch('/profile', [
            'name' => 'Test User',
        ]);

        $response->assertStatus(409);
        $response->assertHeader(Header::LOCATION, url('/login'));
    }
}
