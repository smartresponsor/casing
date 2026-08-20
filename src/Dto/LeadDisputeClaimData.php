<?php

declare(strict_types=1);

namespace App\Casing\Dto;

use App\Casing\Value\LeadSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class LeadDisputeClaimData
{
    #[Assert\NotNull]
    public ?LeadSubject $subject = null;

    #[Assert\NotBlank]
    public string $typeCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $description = '';
}
