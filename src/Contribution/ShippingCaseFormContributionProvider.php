<?php

declare(strict_types=1);

namespace App\Casing\Contribution;

use App\Casing\Contract\CaseFormContributionInterface;
use App\Shipping\DTO\ShipmentPlacementFormData;
use App\Shipping\Form\ShipmentPlacementType;

final class ShippingCaseFormContributionProvider implements CaseFormContributionInterface
{
    public function key(): string
    {
        return 'shipping.placement';
    }

    public function formType(): string
    {
        return ShipmentPlacementType::class;
    }

    public function dataClass(): string
    {
        return ShipmentPlacementFormData::class;
    }

    public function createData(): object
    {
        return new ShipmentPlacementFormData();
    }

    public function normalize(object $data): array
    {
        if (!$data instanceof ShipmentPlacementFormData) {
            throw new \InvalidArgumentException('Shipping contribution data must use ShipmentPlacementFormData.');
        }

        return get_object_vars($data);
    }
}
