<?php

declare(strict_types=1);

namespace App\Identity\Domain;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
