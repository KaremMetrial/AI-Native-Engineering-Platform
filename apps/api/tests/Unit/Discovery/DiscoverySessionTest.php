<?php

declare(strict_types=1);

namespace Tests\Unit\Discovery;

use App\Discovery\Domain\DiscoverySession;
use App\Discovery\Domain\SessionStatus;
use DomainException;
use Tests\TestCase;

class DiscoverySessionTest extends TestCase
{
    public function test_start_produces_an_in_progress_session_with_no_completed_at(): void
    {
        $session = DiscoverySession::start('session-1', 'tenant-1', 'project-1', 'Acme kickoff', 'user-1');

        $this->assertSame(SessionStatus::InProgress, $session->status());
        $this->assertNull($session->completedAt());
    }

    public function test_complete_transitions_to_completed_and_sets_completed_at(): void
    {
        $session = DiscoverySession::start('session-1', 'tenant-1', 'project-1', 'Acme kickoff', 'user-1');

        $session->complete();

        $this->assertSame(SessionStatus::Completed, $session->status());
        $this->assertNotNull($session->completedAt());
    }

    public function test_completing_an_already_completed_session_throws(): void
    {
        $session = DiscoverySession::start('session-1', 'tenant-1', 'project-1', 'Acme kickoff', 'user-1');
        $session->complete();

        $this->expectException(DomainException::class);

        $session->complete();
    }
}
