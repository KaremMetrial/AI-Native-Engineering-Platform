<?php

declare(strict_types=1);

namespace App\Graph\Infrastructure;

use App\Graph\Domain\ApprovalRepository;
use App\Graph\Domain\ArtifactLinkRepository;
use App\Graph\Domain\ArtifactRepository;
use App\Graph\Domain\ArtifactVersionFinder;
use App\Graph\Domain\ImpactTraversal;
use App\Graph\Domain\ProjectRepository;
use Illuminate\Support\ServiceProvider;

class GraphServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProjectRepository::class, EloquentProjectRepository::class);
        $this->app->bind(ArtifactRepository::class, EloquentArtifactRepository::class);
        $this->app->bind(ArtifactVersionFinder::class, EloquentArtifactVersionFinder::class);
        $this->app->bind(ArtifactLinkRepository::class, EloquentArtifactLinkRepository::class);
        $this->app->bind(ApprovalRepository::class, EloquentApprovalRepository::class);
        $this->app->bind(ImpactTraversal::class, PostgresImpactTraversal::class);
    }
}
