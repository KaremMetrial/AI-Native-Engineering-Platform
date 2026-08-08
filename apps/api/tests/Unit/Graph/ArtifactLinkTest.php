<?php

declare(strict_types=1);

namespace Tests\Unit\Graph;

use App\Graph\Domain\ArtifactLink;
use App\Graph\Domain\LinkType;
use DomainException;
use Tests\TestCase;

class ArtifactLinkTest extends TestCase
{
    public function test_create_sets_all_fields(): void
    {
        $link = ArtifactLink::create('link-1', 'tenant-1', 'v-1', 'v-2', LinkType::Implements, 'user-1');

        $this->assertSame('v-1', $link->fromVersionId);
        $this->assertSame('v-2', $link->toVersionId);
        $this->assertSame(LinkType::Implements, $link->linkType);
    }

    public function test_a_version_cannot_link_to_itself(): void
    {
        $this->expectException(DomainException::class);

        ArtifactLink::create('link-1', 'tenant-1', 'v-1', 'v-1', LinkType::References, 'user-1');
    }
}
