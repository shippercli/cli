<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class JokesTest extends TestCase
{
    public function test_documented_jokes_endpoint_returns_json(): void
    {
        $this->getJson('/api/jokes')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'joke' => 'Why do programmers prefer dark mode? Because light attracts bugs!',
                'type' => 'programming',
                'setup' => 'Why do programmers prefer dark mode?',
                'punchline' => 'Because light attracts bugs!',
            ]);
    }
}
