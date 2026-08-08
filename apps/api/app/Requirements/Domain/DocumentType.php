<?php

declare(strict_types=1);

namespace App\Requirements\Domain;

enum DocumentType: string
{
    case Brd = 'brd';
    case Srs = 'srs';
}
