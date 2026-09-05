<?php

declare(strict_types=1);

namespace App\Casing\Form\Contribution;

use App\Casing\FormInterface\Contribution\CaseFormContributionInterface;
use App\Paying\Dto\Payment\PaymentPlacementFormData;
use App\Paying\Form\PaymentPlacementType;

final class PaymentCaseFormContributionProvider implements CaseFormContributionInterface
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
        return PaymentPlacementFormData::class;
    }

    public function createData(): object
    {
        return new PaymentPlacementFormData();
    }

    public function normalize(object $data): array
    {
        if (!$data instanceof PaymentPlacementFormData) {
            throw new \InvalidArgumentException('Payment contribution data must use PaymentPlacementFormData.');
        }

        return get_object_vars($data);
    }
}
