<?php

declare(strict_types=1);

namespace App\Graph\Domain;

enum ArtifactStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
