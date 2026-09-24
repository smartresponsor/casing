<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    App\Objecting\ObjectBundle::class => ['all' => true],
    App\Ordering\OrderingBundle::class => ['all' => true],
    App\Paying\PayingBundle::class => ['all' => true],
    App\Relating\RelatingBundle::class => ['all' => true],
    App\Casing\CasingBundle::class => ['all' => true],
];
