<?php

declare(strict_types=1);

namespace Tests\Unit\Graph;

use App\Graph\Domain\Approval;
use App\Graph\Domain\ApprovalDecision;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    public function test_record_binds_to_a_specific_version(): void
    {
        $approval = Approval::record('approval-1', 'tenant-1', 'version-1', 'user-1', ApprovalDecision::Approved, 'Looks good');

        $this->assertSame('version-1', $approval->artifactVersionId);
        $this->assertSame(ApprovalDecision::Approved, $approval->decision);
        $this->assertSame('Looks good', $approval->comment);
    }

    public function test_comment_is_optional(): void
    {
        $approval = Approval::record('approval-1', 'tenant-1', 'version-1', 'user-1', ApprovalDecision::Rejected, null);

        $this->assertNull($approval->comment);
    }
}
