<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Enforces Canon036 documentation producer ownership for ordinary component repositories.
 */
final class Canon036DocumentationProducerOwnershipRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.036.documentation_producer_ownership';
    }

    /**
     * Evaluates deterministic Antora ownership topology and leaves narrative duplication to semantic review.
     */
    public function check(RuleContext $context): RuleResult
    {
        if ($this->isDocumentating($context)) {
            return $this->result('passed', 'Documentating is the explicit Antora site-owner exception.');
        }

        $violations = [];
        foreach (['antora-playbook.yml', 'antora-playbook.yaml'] as $playbook) {
            if (is_file($context->targetPath.'/'.$playbook)) {
                $violations[] = $playbook.' is a full Antora site playbook owned by Documentating, not by an ordinary component.';
            }
        }

        if (is_file($context->targetPath.'/antora.yml')) {
            $violations[] = 'Root antora.yml is non-canonical for a component producer; use docs/antora.yml.';
        }

        if ([] !== $violations) {
            return $this->result('failed', 'Component repository owns Antora site-level documentation topology.', $violations);
        }

        if (is_file($context->targetPath.'/docs/antora.yml')) {
            return $this->result('passed', 'Component uses the canonical docs/ Antora content-producer surface.');
        }

        return $this->result('passed', 'No component-owned Antora site-level surface was detected.');
    }

    /**
     * Resolves the explicit Documentating exception from repository or Composer component identity.
     */
    private function isDocumentating(RuleContext $context): bool
    {
        if ('documentating' === strtolower(basename(str_replace('\\', '/', $context->targetPath)))) {
            return true;
        }

        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return false;
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        $name = is_array($composer) && is_string($composer['name'] ?? null) ? strtolower($composer['name']) : '';
        $component = explode('/', $name, 2)[0];

        return 'documentating' === $component;
    }
}
