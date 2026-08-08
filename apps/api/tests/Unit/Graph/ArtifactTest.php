<?php

declare(strict_types=1);

namespace Tests\Unit\Graph;

use App\Graph\Domain\Artifact;
use App\Graph\Domain\ArtifactStatus;
use App\Graph\Domain\Lineage;
use DomainException;
use Tests\TestCase;

class ArtifactTest extends TestCase
{
    public function test_create_produces_an_active_artifact_with_one_version(): void
    {
        $artifact = Artifact::create(
            id: 'artifact-1',
            tenantId: 'tenant-1',
            projectId: 'project-1',
            type: 'brd',
            firstVersionId: 'version-1',
            content: 'Hello',
            createdBy: 'user-1',
        );

        $this->assertSame(ArtifactStatus::Active, $artifact->status());
        $this->assertSame('version-1', $artifact->currentVersionId());
        $this->assertCount(1, $artifact->versions());
        $this->assertSame(1, $artifact->versions()[0]->versionNumber);
        $this->assertSame('Hello', $artifact->versions()[0]->content);
    }

    public function test_record_new_version_increments_the_version_number_and_updates_current(): void
    {
        $artifact = Artifact::create('a-1', 't-1', 'p-1', 'brd', 'v-1', 'first', 'u-1');

        $second = $artifact->recordNewVersion('v-2', 'second', Lineage::human(), 'u-1');

        $this->assertSame(2, $second->versionNumber);
        $this->assertSame('v-2', $artifact->currentVersionId());
        $this->assertCount(2, $artifact->versions());
    }

    public function test_cannot_version_an_archived_artifact(): void
    {
        $artifact = Artifact::create('a-1', 't-1', 'p-1', 'brd', 'v-1', 'first', 'u-1');
        $artifact->archive();

        $this->expectException(DomainException::class);

        $artifact->recordNewVersion('v-2', 'second', Lineage::human(), 'u-1');
    }

    public function test_archive_changes_status(): void
    {
        $artifact = Artifact::create('a-1', 't-1', 'p-1', 'brd', 'v-1', 'first', 'u-1');

        $artifact->archive();

        $this->assertSame(ArtifactStatus::Archived, $artifact->status());
    }
}
