<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Contract\CaseFormContributionRegistry;
use App\Casing\Contribution\PaymentCaseFormContributionProvider;
use App\Casing\Contribution\ShippingCaseFormContributionProvider;
use App\Casing\Service\CaseFormContributionService;
use App\Paying\Dto\Payment\PaymentPlacementFormData;
use App\Paying\Form\PaymentPlacementType;
use App\Shipping\DTO\ShipmentPlacementFormData;
use App\Shipping\Form\ShipmentPlacementType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class CaseFormContributionRegistryTest extends TestCase
{
    public function testRegistryExposesOwnerFormTypesAndDtosWithoutFieldCopies(): void
    {
        $registry = new CaseFormContributionRegistry([
            new ShippingCaseFormContributionProvider(),
            new PaymentCaseFormContributionProvider(),
        ]);

        $shipping = $registry->get('shipping.placement');
        self::assertSame(ShipmentPlacementType::class, $shipping->formType());
        self::assertSame(ShipmentPlacementFormData::class, $shipping->dataClass());
        self::assertInstanceOf(ShipmentPlacementFormData::class, $shipping->createData());

        $payment = $registry->get('payment.placement');
        self::assertSame(PaymentPlacementType::class, $payment->formType());
        self::assertSame(PaymentPlacementFormData::class, $payment->dataClass());
        self::assertInstanceOf(PaymentPlacementFormData::class, $payment->createData());
    }

    public function testContributionServiceBuildsActualOwnerForms(): void
    {
        $registry = new CaseFormContributionRegistry([
            new ShippingCaseFormContributionProvider(),
            new PaymentCaseFormContributionProvider(),
        ]);
        $service = new CaseFormContributionService(Forms::createFormFactory(), $registry);

        self::assertSame(['weightKg', 'priority', 'currency'], array_keys($service->create('shipping.placement')->all()));
        self::assertSame(['amount', 'currency', 'provider'], array_keys($service->create('payment.placement')->all()));
    }
}
