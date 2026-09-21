<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon002 interface tree mirrors implementation rule implementation used by the Gating runtime and rule execution flow.
 */
final class Canon002InterfaceTreeMirrorsImplementationRule extends AbstractCanonRule
{
    private const array PAIRS = ['ServiceInterface' => 'Service', 'RepositoryInterface' => 'Repository', 'BuilderInterface' => 'Builder', 'FormInterface' => 'Form', 'ProviderInterface' => 'Provider', 'FactoryInterface' => 'Factory', 'ResponderInterface' => 'Responder', 'RecorderInterface' => 'Recorder', 'VerifierInterface' => 'Verifier'];

    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.002.interface_tree_mirror';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $hits = [];
        $count = 0;
        foreach (self::PAIRS as $interfaceRoot => $implementationRoot) {
            $root = $context->targetPath.'/src/'.$interfaceRoot;
            if (!is_dir($root)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                    continue;
                }
                ++$count;
                $name = $file->getBasename('.php');
                if (!str_ends_with($name, 'Interface')) {
                    $hits[] = $this->relative($context, $file->getPathname()).' lacks Interface suffix.';
                    continue;
                }
                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                $dir = dirname($rel);
                $impl = substr($name, 0, -9).'.php';
                $implRel = ('.' === $dir ? '' : $dir.'/').$impl;
                $expectedPath = $context->targetPath.'/src/'.$implementationRoot.'/'.$implRel;
                if (is_file($expectedPath)) {
                    continue;
                }

                $implementationRootPath = $context->targetPath.'/src/'.$implementationRoot;
                if (!is_dir($implementationRootPath)) {
                    continue;
                }

                $implementation = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($implementationRootPath, \FilesystemIterator::SKIP_DOTS));
                foreach ($implementation as $candidate) {
                    if ($candidate instanceof \SplFileInfo && $candidate->isFile() && $candidate->getBasename() === $impl) {
                        $hits[] = $this->relative($context, $file->getPathname()).' mirrors '.$implementationRoot.'/'.$impl.' but the implementation is not in '.$implementationRoot.'/'.$implRel;
                        break;
                    }
                }
            }
        }
        if ([] !== $hits) {
            return $this->result('failed', 'Typed interface trees must mirror implementation trees.', $hits);
        }

        return 0 === $count ? $this->result('skipped', 'No typed interface trees found.') : $this->result('passed', 'Typed interface trees mirror implementations.');
    }
}
