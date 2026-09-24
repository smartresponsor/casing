<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Service\CaseActorAccessService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

final class CaseActorAccessServiceTest extends TestCase
{
    public function testHostActorAttributeHasPriorityOverSecurityUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');

        $request = new Request();
        $request->attributes->set('casing_actor_id', ' host-actor ');

        self::assertSame('host-actor', (new CaseActorAccessService($security))->requireActorId($request));
    }

    public function testAuthenticatedUserIdentifierIsUsedWhenHostActorIsMissing(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn(' user-42 ');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        self::assertSame('user-42', (new CaseActorAccessService($security))->requireActorId(new Request()));
    }

    public function testMissingActorIsDenied(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedHttpException::class);
        (new CaseActorAccessService($security))->requireActorId(new Request());
    }

    public function testBlankAuthenticatedIdentifierIsDenied(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('   ');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $this->expectException(AccessDeniedHttpException::class);
        (new CaseActorAccessService($security))->requireActorId(new Request());
    }
}
