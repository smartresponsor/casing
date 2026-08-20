<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Contract\ServicePaymentSubjectResolverInterface;
use App\Casing\Controller\ContextualSupportController;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Value\PurchasedProductSubject;
use App\Casing\Value\ServicePaymentSubject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ContextualSupportControllerTest extends TestCase
{
    public function testOrderContextExposesOnlyActorOwnedHelpActions(): void
    {
        $products = new class implements PurchasedProductSubjectResolverInterface {
            public function listForActor(string $actorId): array
            {
                return 'actor-1' === $actorId ? [$this->subject()] : [];
            }

            public function listForActorOrder(string $actorId, string $orderReference): array
            {
                return 'actor-1' === $actorId && 'order-1' === $orderReference ? [$this->subject()] : [];
            }

            public function resolve(string $actorId, string $orderReference, string $itemReference): ?PurchasedProductSubject
            {
                foreach ($this->listForActorOrder($actorId, $orderReference) as $subject) {
                    if ($subject->itemReference === $itemReference) {
                        return $subject;
                    }
                }

                return null;
            }

            private function subject(): PurchasedProductSubject
            {
                return new PurchasedProductSubject('order-1', 'ORD-1', 'SKU-1', 1, 'USD', '25.00', 'completed');
            }
        };
        $payments = new class implements ServicePaymentSubjectResolverInterface {
            public function listForActor(string $actorId): array
            {
                return 'actor-1' === $actorId ? [new ServicePaymentSubject('payment-1', 'order-1', 'ORD-1', 'completed', '25.00', 'USD', null)] : [];
            }

            public function resolve(string $actorId, string $paymentReference): ?ServicePaymentSubject
            {
                foreach ($this->listForActor($actorId) as $subject) {
                    if ($subject->paymentReference === $paymentReference) {
                        return $subject;
                    }
                }

                return null;
            }
        };
        $security = $this->createStub(Security::class);
        $controller = new ContextualSupportController(new CaseActorAccessService($security), $products, $payments);
        $request = new Request(attributes: ['casing_actor_id' => 'actor-1']);

        $payload = $controller->order($request, 'order-1');

        self::assertCount(2, $payload['data']['actions']);
        self::assertSame('product-return', $payload['data']['actions'][0]['kind']);
        self::assertSame('service-dispute', $payload['data']['actions'][1]['kind']);
    }

    public function testOrderContextDoesNotRevealAnotherActorsOrder(): void
    {
        $products = $this->createStub(PurchasedProductSubjectResolverInterface::class);
        $products->method('listForActorOrder')->willReturn([]);
        $payments = $this->createStub(ServicePaymentSubjectResolverInterface::class);
        $payments->method('listForActor')->willReturn([]);
        $security = $this->createStub(Security::class);
        $controller = new ContextualSupportController(new CaseActorAccessService($security), $products, $payments);
        $request = new Request(attributes: ['casing_actor_id' => 'actor-2']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('We could not associate this order with your account.');
        $controller->order($request, 'order-1');
    }
}
