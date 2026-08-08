<?php

declare(strict_types=1);

namespace App\Graph\Domain;

enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
