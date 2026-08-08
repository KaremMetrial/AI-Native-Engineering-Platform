<?php

declare(strict_types=1);

namespace Tests\Unit\Requirements;

use App\Requirements\Domain\AcceptanceCriterion;
use App\Requirements\Domain\Requirement;
use App\Requirements\Domain\RequirementAlreadyApproved;
use App\Requirements\Domain\RequirementStatus;
use Tests\TestCase;

class RequirementTest extends TestCase
{
    public function test_draft_creates_a_draft_requirement_with_its_acceptance_criteria(): void
    {
        $requirement = Requirement::draft(
            'requirement-1',
            'tenant-1',
            'document-1',
            'The system shall allow login via email and password.',
            [new AcceptanceCriterion('Given valid credentials, the user is logged in.')],
            'user-1',
        );

        $this->assertSame(RequirementStatus::Draft, $requirement->status());
        $this->assertCount(1, $requirement->acceptanceCriteria());
    }

    public function test_approve_transitions_to_approved(): void
    {
        $requirement = Requirement::draft('requirement-1', 'tenant-1', 'document-1', 'Text', [], 'user-1');

        $requirement->approve();

        $this->assertSame(RequirementStatus::Approved, $requirement->status());
    }

    public function test_approving_an_already_approved_requirement_throws(): void
    {
        $requirement = Requirement::draft('requirement-1', 'tenant-1', 'document-1', 'Text', [], 'user-1');
        $requirement->approve();

        $this->expectException(RequirementAlreadyApproved::class);

        $requirement->approve();
    }
}
