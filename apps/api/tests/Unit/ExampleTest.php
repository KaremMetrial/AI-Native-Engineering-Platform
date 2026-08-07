<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Harness proof: the unit test runner executes and asserts correctly.
 * Replaced by real domain-layer tests as Modules/*\/Domain lands
 * (docs/delivery/14-testing-strategy.md).
 */
class ExampleTest extends TestCase
{
    public function test_the_unit_test_harness_runs(): void
    {
        $this->assertTrue(true);
    }
}
