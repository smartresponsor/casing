<?php

declare(strict_types=1);

namespace App\Casing;

use App\Casing\DependencyInjection\CasingExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CasingBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        $extension = parent::getContainerExtension();

        return $extension instanceof ExtensionInterface ? $extension : new CasingExtension();
    }
}
