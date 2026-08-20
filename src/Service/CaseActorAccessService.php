<?php

declare(strict_types=1);

namespace App\Casing\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class CaseActorAccessService
{
    public function __construct(private Security $security)
    {
    }

    public function requireActorId(Request $request): string
    {
        $hostActorId = trim((string) $request->attributes->get('casing_actor_id', ''));
        if ('' !== $hostActorId) {
            return $hostActorId;
        }

        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            $actorId = trim($user->getUserIdentifier());
            if ('' !== $actorId) {
                return $actorId;
            }
        }

        throw new AccessDeniedHttpException('Authenticated support actor is required.');
    }
}
