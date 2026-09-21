<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon020 typed symfony role root rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon020TypedSymfonyRoleRootRule extends AbstractCanonRule
{
    private const array GENERIC_ROOTS = ['Common', 'Core', 'Support', 'Runtime', 'Infrastructure', 'Utility', 'Helper'];
    private const array SUFFIX_ROOTS = [
        'Controller' => 'Controller',
        'Command' => 'Command',
        'Factory' => 'Factory',
        'Handler' => 'Handler',
        'Listener' => 'Listener',
        'Normalizer' => 'Normalizer',
        'Provider' => 'Provider',
        'Resolver' => 'Resolver',
        'Subscriber' => 'EventSubscriber',
    ];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.020.typed_symfony_role_root';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        foreach (self::GENERIC_ROOTS as $root) {
            if (is_dir($context->targetPath.'/src/'.$root)) {
                $hits[] = 'src/'.$root.'/ hides stable responsibilities behind a generic root.';
            }
        }

        foreach ($this->phpFiles($context) as $file) {
            $relative = $this->relative($context, $file->getPathname());
            $root = explode('/', $relative)[1] ?? '';
            $name = $file->getBasename('.php');
            foreach (self::SUFFIX_ROOTS as $suffix => $expectedRoot) {
                if (str_ends_with($name, $suffix) && $root !== $expectedRoot) {
                    $hits[] = $relative.' ends with '.$suffix.' but is under '.$root.'/; expected '.$expectedRoot.'/.';
                }
            }
        }

        return [] === $hits
            ? $this->result('passed', 'No generic roots or typed-role placement violations were found.')
            : $this->result('failed', 'Symfony/application types must live in explicit technical-role roots.', array_values(array_unique($hits)));
    }
}
