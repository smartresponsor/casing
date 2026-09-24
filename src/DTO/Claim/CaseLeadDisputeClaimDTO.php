<?php

declare(strict_types=1);

namespace App\Casing\DTO\Claim;

use App\Casing\Value\CaseLeadSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class CaseLeadDisputeClaimDTO
{
    #[Assert\NotNull]
    public ?CaseLeadSubject $subject = null;

    #[Assert\NotBlank]
    public string $typeCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $description = '';
}
