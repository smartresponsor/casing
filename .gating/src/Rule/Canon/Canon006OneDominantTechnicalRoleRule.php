<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon006 one dominant technical role rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon006OneDominantTechnicalRoleRule extends AbstractCanonRule
{
    private const array ROLE_SUFFIXES = ['Builder' => 'Builder', 'Responder' => 'Responder', 'Policy' => 'Policy', 'Repository' => 'Repository', 'Service' => 'Service', 'Controller' => 'Controller', 'Provider' => 'Provider', 'Factory' => 'Factory', 'Recorder' => 'Recorder', 'Verifier' => 'Verifier', 'Authenticator' => 'Authenticator', 'Codec' => 'Codec', 'Resolver' => 'Resolver', 'Command' => 'Command'];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.006.dominant_technical_role';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $root = explode('/', $relative)[1] ?? '';
            $name = $file->getBasename('.php');
            foreach (self::ROLE_SUFFIXES as $suffix => $expectedRoot) {
                if (str_ends_with($name, $suffix) && $root !== $expectedRoot) {
                    $hits[] = $relative.' ends with '.$suffix.' but is under '.$root.'/';
                }
            }
            foreach (['BuilderService', 'ResponderService', 'PolicyService', 'ProviderService', 'FactoryService', 'RecorderService', 'VerifierService', 'AuthenticatorService', 'CodecService', 'ResolverService'] as $mixed) {
                if (str_ends_with($name, $mixed)) {
                    $hits[] = $relative.' uses mixed role suffix '.$mixed;
                }
            }
        }

        return [] === $hits ? $this->result('passed', 'Dominant technical roles match names and paths.') : $this->result('failed', 'Dominant technical role violations found.', array_values(array_unique($hits)));
    }
}
