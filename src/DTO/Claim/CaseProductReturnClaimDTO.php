<?php

declare(strict_types=1);

namespace App\Casing\DTO\Claim;

use App\Casing\Value\CasePurchasedProductSubject;
use Symfony\Component\Validator\Constraints as Assert;

final class CaseProductReturnClaimDTO
{
    #[Assert\NotNull]
    public ?CasePurchasedProductSubject $subject = null;

    #[Assert\NotBlank]
    public string $typeCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public string $reason = '';

    #[Assert\Positive]
    public ?int $quantity = null;
}
