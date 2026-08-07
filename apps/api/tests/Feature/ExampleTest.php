<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Harness proof: the HTTP kernel boots and the middleware pipeline
 * (docs/architecture/design/31-component-architecture.md) executes end to end.
 */
class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
