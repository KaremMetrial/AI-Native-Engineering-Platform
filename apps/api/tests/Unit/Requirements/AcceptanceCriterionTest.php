<?php

declare(strict_types=1);

namespace Tests\Unit\Requirements;

use App\Requirements\Domain\AcceptanceCriterion;
use InvalidArgumentException;
use Tests\TestCase;

class AcceptanceCriterionTest extends TestCase
{
    public function test_stores_the_description(): void
    {
        $criterion = new AcceptanceCriterion('Given valid credentials, the user is logged in.');

        $this->assertSame('Given valid credentials, the user is logged in.', $criterion->description);
    }

    public function test_rejects_an_empty_description(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AcceptanceCriterion('   ');
    }
}
