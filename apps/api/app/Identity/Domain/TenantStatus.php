<?php

declare(strict_types=1);

namespace App\Identity\Domain;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
