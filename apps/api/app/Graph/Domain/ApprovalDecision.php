<?php

declare(strict_types=1);

namespace App\Graph\Domain;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
