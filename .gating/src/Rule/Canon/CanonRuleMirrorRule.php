<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides the canon rule mirror rule implementation used by the Gating runtime and rule execution flow.
 */
final class CanonRuleMirrorRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.mirror_contract';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $canonRoot = dirname($context->targetPath).'/Canonization/.canonization/Governance/Architecture/Rule';
        $gatingRoot = $context->targetPath.'/src/Rule/Canon';
        if (!is_dir($canonRoot) || !is_dir($gatingRoot)) {
            return $this->result('skipped', 'Canonization/Gating sibling mirror roots are not both available.');
        }
        $hits = [];
        $count = 0;
        foreach (glob($canonRoot.'/Canon*Rule.md') ?: [] as $document) {
            ++$count;
            $base = basename($document, '.md');
            if (1 !== preg_match('/^Canon\d{3}[A-Z][A-Za-z0-9]*Rule$/', $base)) {
                $hits[] = basename($document).' violates CanonNNN<SemanticName>Rule naming.';
                continue;
            }
            if (!is_file($gatingRoot.'/'.$base.'.php')) {
                $hits[] = $base.'.md has no mirrored '.$base.'.php';
            }

            $contents = file_get_contents($document);
            if (false === $contents) {
                $hits[] = $base.'.md could not be read for its Evidence Contract.';
                continue;
            }

            if (1 !== preg_match_all('/^## Evidence Contract\s*\R```yaml\s*\R(.*?)\R```/ms', $contents, $matches)) {
                $hits[] = $base.'.md must contain exactly one inline YAML Evidence Contract.';
                continue;
            }

            try {
                $parsed = Yaml::parse($matches[1][0]);
            } catch (\Throwable $exception) {
                $hits[] = $base.'.md Evidence Contract YAML is invalid: '.$exception->getMessage();
                continue;
            }

            $contract = is_array($parsed) ? ($parsed['evidence_contract'] ?? null) : null;
            if (!is_array($contract)) {
                $hits[] = $base.'.md Evidence Contract must define evidence_contract metadata.';
                continue;
            }

            $requiredFields = ['coverage', 'extraction', 'body_read', 'reasoning', 'escalation', 'executable_evidence'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $contract)) {
                    $hits[] = $base.'.md Evidence Contract is missing '.$field.'.';
                }
            }

            if (isset($contract['coverage']) && (!is_string($contract['coverage']) || '' === trim($contract['coverage']))) {
                $hits[] = $base.'.md Evidence Contract coverage must be a non-empty string.';
            }
            foreach (['extraction', 'escalation', 'executable_evidence'] as $listField) {
                if (isset($contract[$listField]) && !is_array($contract[$listField])) {
                    $hits[] = $base.'.md Evidence Contract '.$listField.' must be a list.';
                }
            }
            foreach (['body_read', 'reasoning'] as $modeField) {
                if (isset($contract[$modeField]) && (!is_string($contract[$modeField]) || '' === trim($contract[$modeField]))) {
                    $hits[] = $base.'.md Evidence Contract '.$modeField.' must be a non-empty mode string.';
                }
            }
        }
        if ([] !== $hits) {
            return $this->result('failed', 'Canonization/Gating mirror contract is incomplete.', $hits);
        }

        return 0 === $count ? $this->result('skipped', 'No CanonNNN rule documents found.') : $this->result('passed', sprintf('%d Canonization rule(s) have mirrored Gating PHP rules and structurally valid Evidence Contracts.', $count));
    }
}
