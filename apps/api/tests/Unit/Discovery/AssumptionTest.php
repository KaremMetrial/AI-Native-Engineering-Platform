<?php

declare(strict_types=1);

namespace Tests\Unit\Discovery;

use App\Discovery\Domain\Assumption;
use Tests\TestCase;

class AssumptionTest extends TestCase
{
    public function test_capture_creates_an_assumption_bound_to_a_session(): void
    {
        $assumption = Assumption::capture('assumption-1', 'tenant-1', 'session-1', 'Stakeholders will respond within 48 hours.', 'user-1');

        $this->assertSame('session-1', $assumption->sessionId);
        $this->assertSame('Stakeholders will respond within 48 hours.', $assumption->statement);
    }
}
