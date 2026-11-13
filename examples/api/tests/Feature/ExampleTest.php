<?php

declare(strict_types=1);

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_documented_jokes_endpoint_returns_json(): void
    {
        $this->getJson('/api/jokes')
            ->assertOk()
            ->assertJsonPath('type', 'programming')
            ->assertJsonStructure(['joke', 'type', 'setup', 'punchline']);
    }
}
