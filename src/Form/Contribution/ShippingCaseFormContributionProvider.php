<?php

declare(strict_types=1);

namespace App\Casing\Form\Contribution;

use App\Casing\FormInterface\Contribution\CaseFormContributionInterface;
use App\Shipping\DTO\ShipmentPlacementFormDTO;
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
        return ShipmentPlacementFormDTO::class;
    }

    public function createData(): object
    {
        return new ShipmentPlacementFormDTO();
    }

    public function normalize(object $data): array
    {
        if (!$data instanceof ShipmentPlacementFormDTO) {
            throw new \InvalidArgumentException('Shipping contribution data must use ShipmentPlacementFormDTO.');
        }

        return get_object_vars($data);
    }
}
