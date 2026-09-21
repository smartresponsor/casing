<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;
use Gating\Gate\Service\GitTrackingService;

/**
 * Enforces Canon037 generated reference artifacts staying outside repository source history.
 */
final class Canon037GeneratedReferenceArtifactRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.037.generated_reference_artifact';
    }

    /**
     * Checks whether config/reference.php is tracked as repository source.
     */
    public function check(RuleContext $context): RuleResult
    {
        $relativePath = 'config/reference.php';
        $artifactPath = $context->targetPath.'/'.$relativePath;

        if (!is_file($artifactPath)) {
            return $this->result('passed', 'No generated config/reference.php source artifact is present.');
        }

        $trackingState = (new GitTrackingService())->isTracked($context->targetPath, $relativePath);

        if (true === $trackingState) {
            return $this->result(
                'failed',
                'Generated config/reference.php is tracked as repository source.',
                ['Remove the tracked generated artifact and keep authoritative configuration as the source of truth.'],
            );
        }

        if (false === $trackingState) {
            return $this->result('passed', 'Generated config/reference.php exists locally but is not tracked by Git.');
        }

        return $this->result(
            'warning',
            'Generated config/reference.php exists, but Git tracking state could not be determined.',
            ['Verify that config/reference.php is untracked/ignored and is not committed repository source.'],
            'warning',
        );
    }
}
