<?php

declare(strict_types=1);

namespace App\Casing\Dto;

use App\Casing\Value\ServicePaymentSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class ServiceDisputeClaimData
{
    #[Assert\NotNull]
    public ?ServicePaymentSubject $subject = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $description = '';
}
