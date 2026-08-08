<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

enum RequirementDocumentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
