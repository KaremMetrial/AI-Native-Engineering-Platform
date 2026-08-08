<?php

declare(strict_types=1);

namespace Tests\Unit\Discovery;

use App\Discovery\Domain\Constraint;
use Tests\TestCase;

class ConstraintTest extends TestCase
{
    public function test_capture_creates_a_constraint_bound_to_a_session(): void
    {
        $constraint = Constraint::capture('constraint-1', 'tenant-1', 'session-1', 'Must integrate with the client\'s existing Jira instance.', 'user-1');

        $this->assertSame('session-1', $constraint->sessionId);
        $this->assertSame('Must integrate with the client\'s existing Jira instance.', $constraint->statement);
    }
}
