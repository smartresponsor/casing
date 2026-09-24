<?php

declare(strict_types=1);

namespace App\Casing\Provider\Contribution;

use App\Casing\FormInterface\Contribution\CaseFormContributionInterface;
use App\Paying\DTO\PaymentPlacementFormDTO;
use App\Paying\Form\PaymentPlacementType;

final class CasePaymentFormContributionProvider implements CaseFormContributionInterface
{
    public function key(): string
    {
        return 'payment.placement';
    }

    public function formType(): string
    {
        return PaymentPlacementType::class;
    }

    public function dataClass(): string
    {
        return PaymentPlacementFormDTO::class;
    }

    public function createData(): object
    {
        return new PaymentPlacementFormDTO();
    }

    public function normalize(object $data): array
    {
        if (!$data instanceof PaymentPlacementFormDTO) {
            throw new \InvalidArgumentException('Payment contribution data must use PaymentPlacementFormDTO.');
        }

        return get_object_vars($data);
    }
}
