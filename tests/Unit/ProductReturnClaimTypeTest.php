<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\DTO\Claim\ProductReturnClaimDTO;
use App\Casing\Form\Claim\ProductReturnClaimType;
use App\Casing\Value\PurchasedProductSubject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class ProductReturnClaimTypeTest extends TestCase
{
    public function testActorScopedSubjectTokenMapsBackToVerifiedProjection(): void
    {
        $subject = $this->subject();
        $data = new ProductReturnClaimDTO();
        $form = Forms::createFormFactory()->create(ProductReturnClaimType::class, $data, [
            'subjects' => [$subject],
            'types' => [['code' => 'damaged', 'label' => 'Damaged']],
        ]);

        $form->submit([
            'subject' => $this->token($subject),
            'typeCode' => 'damaged',
            'reason' => 'Package arrived damaged.',
            'quantity' => '1',
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());
        self::assertSame($subject, $data->subject);
        self::assertSame('damaged', $data->typeCode);
        self::assertSame('Package arrived damaged.', $data->reason);
        self::assertSame(1, $data->quantity);
    }

    public function testUnknownSubjectTokenIsRejectedByTheChoiceField(): void
    {
        $subject = $this->subject();
        $data = new ProductReturnClaimDTO();
        $form = Forms::createFormFactory()->create(ProductReturnClaimType::class, $data, [
            'subjects' => [$subject],
            'types' => [['code' => 'damaged', 'label' => 'Damaged']],
        ]);

        $form->submit([
            'subject' => hash('sha256', 'not-an-actor-owned-subject'),
            'typeCode' => 'damaged',
            'reason' => 'Return requested.',
            'quantity' => '1',
        ]);

        self::assertFalse($form->isValid());
        self::assertNull($data->subject);
    }

    private function subject(): PurchasedProductSubject
    {
        return new PurchasedProductSubject(
            orderReference: 'order-slug-1',
            orderNumber: 'ORD-TEST-1',
            itemReference: 'SKU-RETURN-1',
            quantity: 2,
            currency: 'USD',
            unitPrice: '19.99',
            orderStatus: 'delivered',
        );
    }

    private function token(PurchasedProductSubject $subject): string
    {
        return hash('sha256', $subject->orderReference."\0".$subject->itemReference);
    }
}
