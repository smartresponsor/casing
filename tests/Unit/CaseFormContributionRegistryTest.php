<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Provider\Contribution\CasePaymentFormContributionProvider;
use App\Casing\Provider\Contribution\CaseShippingFormContributionProvider;
use App\Casing\Service\CaseFormContributionService;
use App\Casing\Service\Form\CaseFormContributionRegistry;
use App\Paying\DTO\PaymentPlacementFormDTO;
use App\Paying\Form\PaymentPlacementType;
use App\Shipping\DTO\ShipmentPlacementFormDTO;
use App\Shipping\Form\ShipmentPlacementType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class CaseFormContributionRegistryTest extends TestCase
{
    public function testRegistryExposesOwnerFormTypesAndDtosWithoutFieldCopies(): void
    {
        $registry = new CaseFormContributionRegistry([
            new CaseShippingFormContributionProvider(),
            new CasePaymentFormContributionProvider(),
        ]);

        $shipping = $registry->get('shipping.placement');
        self::assertSame(ShipmentPlacementType::class, $shipping->formType());
        self::assertSame(ShipmentPlacementFormDTO::class, $shipping->dataClass());
        self::assertInstanceOf(ShipmentPlacementFormDTO::class, $shipping->createData());

        $payment = $registry->get('payment.placement');
        self::assertSame(PaymentPlacementType::class, $payment->formType());
        self::assertSame(PaymentPlacementFormDTO::class, $payment->dataClass());
        self::assertInstanceOf(PaymentPlacementFormDTO::class, $payment->createData());
    }

    public function testContributionServiceBuildsActualOwnerForms(): void
    {
        $registry = new CaseFormContributionRegistry([
            new CaseShippingFormContributionProvider(),
            new CasePaymentFormContributionProvider(),
        ]);
        $service = new CaseFormContributionService(Forms::createFormFactory(), $registry);

        self::assertSame(['weightKg', 'priority', 'currency'], array_keys($service->create('shipping.placement')->all()));
        self::assertSame(['amount', 'currency', 'provider'], array_keys($service->create('payment.placement')->all()));
    }
}
