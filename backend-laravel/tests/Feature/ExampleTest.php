<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_spa_entry_point_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('id="app"', false);
    }

    public function test_vue_history_routes_return_the_spa(): void
    {
        $response = $this->get('/expedientes/15');

        $response->assertStatus(200);
        $response->assertSee('id="app"', false);
    }

    public function test_unknown_api_routes_are_not_captured_by_the_spa(): void
    {
        $this->getJson('/api/no-existe')->assertNotFound();
    }
}
