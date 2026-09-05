<?php

declare(strict_types=1);

namespace App\Casing\DTO\Claim;

use App\Casing\Value\ServicePaymentSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class ServiceDisputeClaimDTO
{
    #[Assert\NotNull]
    public ?ServicePaymentSubject $subject = null;

    #[Assert\NotBlank]
    public string $typeCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $description = '';
}
