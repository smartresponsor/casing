<?php

declare(strict_types=1);

namespace App\Casing\Contract;

final class CaseFormContributionRegistry
{
    /** @var array<string, CaseFormContributionInterface>|null */
    private ?array $index = null;

    /** @param iterable<CaseFormContributionInterface> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function has(string $key): bool
    {
        return isset($this->all()[trim($key)]);
    }

    public function get(string $key): CaseFormContributionInterface
    {
        $key = trim($key);
        $provider = $this->all()[$key] ?? null;
        if (!$provider instanceof CaseFormContributionInterface) {
            throw new \RuntimeException(sprintf('No case form contribution is registered for "%s".', $key));
        }

        return $provider;
    }

    /** @return array<string, CaseFormContributionInterface> */
    public function all(): array
    {
        if (null !== $this->index) {
            return $this->index;
        }

        $index = [];
        foreach ($this->providers as $provider) {
            $key = trim($provider->key());
            if ('' === $key || isset($index[$key])) {
                throw new \LogicException(sprintf('Invalid or duplicate case form contribution key "%s".', $key));
            }
            $index[$key] = $provider;
        }

        return $this->index = $index;
    }
}
