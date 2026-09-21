<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon001 technical role first rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon001TechnicalRoleFirstRule extends AbstractCanonRule
{
    private const array ROOTS = ['Controller', 'Service', 'ServiceInterface', 'Repository', 'RepositoryInterface', 'Entity', 'DTO', 'Snapshot', 'Form', 'FormInterface', 'Policy', 'Builder', 'BuilderInterface', 'Responder', 'ResponderInterface', 'Command', 'Enum', 'Event', 'EventSubscriber', 'ValueObject', 'ValueObjectInterface', 'Exception', 'Message', 'Handler', 'Provider', 'ProviderInterface', 'Resolver', 'Factory', 'FactoryInterface', 'Normalizer', 'Recorder', 'RecorderInterface', 'Verifier', 'VerifierInterface', 'Authenticator', 'Clock', 'Codec', 'Context', 'Guard', 'Invoker', 'Parser', 'Runner', 'Contract', 'Kernel', 'Registry', 'Rule', 'Console', 'Inventory', 'Profile', 'Reporter', 'Target', 'DataFixtures', 'Validator', 'DependencyInjection'];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.001.technical_role_first';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $parts = explode('/', $this->relative($context, $file->getPathname()));
            $root = $parts[1] ?? null;
            if ('src' !== $parts[0]) {
                continue;
            }

            if (2 === count($parts) && ('Kernel.php' === $root || (is_string($root) && str_ends_with($root, 'Bundle.php')))) {
                continue;
            }

            if (null === $root || str_ends_with($root, '.php') || !in_array($root, self::ROOTS, true)) {
                $hits[] = implode('/', $parts);
            }
        }

        return [] === $hits ? $this->result('passed', 'Technical role is the first src/ namespace token.') : $this->result('failed', 'Technical role must be first under src/.', $hits);
    }
}
