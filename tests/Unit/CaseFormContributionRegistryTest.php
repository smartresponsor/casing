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

    public function testContributionServiceSubmitsAndNormalizesOwnerDto(): void
    {
        $registry = new CaseFormContributionRegistry([new CasePaymentFormContributionProvider()]);
        $service = new CaseFormContributionService(Forms::createFormFactory(), $registry);

        $form = $service->submit('payment.placement', [
            'amount' => '12.50',
            'currency' => 'USD',
            'provider' => 'stripe',
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());
        self::assertSame(
            ['amount' => '0.00', 'currency' => 'USD', 'provider' => 'stripe'],
            $service->normalized('payment.placement', $form),
        );
    }

    public function testContributionServiceRejectsNormalizationBeforeSubmission(): void
    {
        $registry = new CaseFormContributionRegistry([new CasePaymentFormContributionProvider()]);
        $service = new CaseFormContributionService(Forms::createFormFactory(), $registry);

        $this->expectException(\DomainException::class);
        $service->normalized('payment.placement', $service->create('payment.placement'));
    }

    public function testRegistryTrimsKeysAndReportsUnknownContribution(): void
    {
        $provider = new class implements \App\Casing\FormInterface\Contribution\CaseFormContributionInterface {
            public function key(): string
            {
                return ' custom ';
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
                return [];
            }
        };

        $registry = new CaseFormContributionRegistry([$provider]);

        self::assertTrue($registry->has(' custom '));
        self::assertSame($provider, $registry->get('custom'));
        self::assertSame(['custom' => $provider], $registry->all());

        $this->expectException(\RuntimeException::class);
        $registry->get('missing');
    }

    public function testRegistryRejectsBlankAndDuplicateKeys(): void
    {
        $blank = $this->providerWithKey('   ');
        $this->expectException(\LogicException::class);
        (new CaseFormContributionRegistry([$blank]))->all();
    }

    public function testRegistryRejectsDuplicateTrimmedKeys(): void
    {
        $this->expectException(\LogicException::class);
        (new CaseFormContributionRegistry([
            $this->providerWithKey('duplicate'),
            $this->providerWithKey(' duplicate '),
        ]))->all();
    }

    public function testContributionProvidersRejectForeignDtoTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CaseShippingFormContributionProvider())->normalize(new PaymentPlacementFormDTO());
    }

    private function providerWithKey(string $key): \App\Casing\FormInterface\Contribution\CaseFormContributionInterface
    {
        return new class($key) implements \App\Casing\FormInterface\Contribution\CaseFormContributionInterface {
            public function __construct(private readonly string $key)
            {
            }

            public function key(): string
            {
                return $this->key;
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
                return [];
            }
        };
    }
}
