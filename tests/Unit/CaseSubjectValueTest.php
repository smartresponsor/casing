<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Value\CaseLeadSubject;
use App\Casing\Value\CaseServicePaymentSubject;
use PHPUnit\Framework\TestCase;

final class CaseSubjectValueTest extends TestCase
{
    public function testLeadSubjectSerializesCanonicalShape(): void
    {
        $subject = new CaseLeadSubject('lead-1', 'qualified', 87);

        self::assertSame([
            'leadReference' => 'lead-1',
            'status' => 'qualified',
            'score' => 87,
        ], $subject->toArray());
    }

    public function testServicePaymentSubjectSerializesCanonicalShapeIncludingNullableProvider(): void
    {
        $subject = new CaseServicePaymentSubject(
            'payment-1',
            'order-1',
            'SO-100',
            'captured',
            '49.95',
            'USD',
            null,
        );

        self::assertSame([
            'paymentReference' => 'payment-1',
            'orderReference' => 'order-1',
            'orderNumber' => 'SO-100',
            'status' => 'captured',
            'amount' => '49.95',
            'currency' => 'USD',
            'providerReference' => null,
        ], $subject->toArray());
    }
}
