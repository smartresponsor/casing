<?php

declare(strict_types=1);

namespace App\Casing\DTO\Claim;

use App\Casing\Value\CaseServicePaymentSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class CaseServiceDisputeClaimDTO
{
    #[Assert\NotNull]
    public ?CaseServicePaymentSubject $subject = null;

    #[Assert\NotBlank]
    public string $typeCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $description = '';
}
