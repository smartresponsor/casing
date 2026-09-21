<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon021 cruding owns generic crud rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon021CrudingOwnsGenericCrudRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.021.cruding_owns_generic_crud';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.'/composer.json';
        $composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : null;
        if (is_array($composer) && 'cruding/crud' === ($composer['name'] ?? null)) {
            return $this->result('passed', 'Target is the Cruding owner component.');
        }

        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $name = $file->getBasename('.php');
            if (!str_contains(strtolower($name), 'crud')) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (str_contains($contents, 'EasyCorp\\Bundle\\EasyAdminBundle') || str_contains($contents, 'AbstractCrudController')) {
                continue;
            }
            if (1 === preg_match('/(?:GenericCrud|CrudRoute|CrudRouter|CrudService|CrudController)/i', $name)) {
                $hits[] = $this->relative($context, $file->getPathname()).' looks like component-local generic CRUD machinery.';
            }
        }

        return [] === $hits
            ? $this->result('passed', 'No component-local generic CRUD machinery was detected outside Cruding.')
            : $this->result('warning', 'Potential duplicate generic CRUD machinery requires review; EasyAdmin CRUD is exempt.', $hits, 'warning');
    }
}
