<?php

declare(strict_types=1);

namespace App\Casing\Enum;

enum CaseStatus: string
{
    case Submitted = 'submitted';
    case Processing = 'processing';
    case NeedsInformation = 'needs_information';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
