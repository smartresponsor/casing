<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Enforces Canon038 collision-safe subject prefixes for component-owned YAML configuration filenames.
 */
final class Canon038ConfigYamlSubjectPrefixRule extends AbstractCanonRule
{
    private const array CONVENTIONAL_BOOTSTRAP_FILES = [
        'annotations.yaml',
        'api_platform.yaml',
        'asset_mapper.yaml',
        'controllers.yaml',
        'cache.yaml',
        'csrf.yaml',
        'doctrine.yaml',
        'doctrine_migrations.yaml',
        'easyadmin.yaml',
        'framework.yaml',
        'lock.yaml',
        'mailer.yaml',
        'messenger.yaml',
        'monolog.yaml',
        'nelmio_api_doc.yaml',
        'notifier.yaml',
        'property_info.yaml',
        'rate_limiter.yaml',
        'reset_password.yaml',
        'routes.yaml',
        'routing.yaml',
        'scheb_2fa.yaml',
        'security.yaml',
        'services.yaml',
        'translation.yaml',
        'twig.yaml',
        'twig_component.yaml',
        'ux_turbo.yaml',
        'validator.yaml',
        'verify_email.yaml',
        'web_profiler.yaml',
    ];

    public function id(): string
    {
        return 'canon.038.config_yaml_subject_prefix';
    }

    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer) || !is_string($composer['name'] ?? null)) {
            return $this->result('skipped', 'Target has no usable Composer package identity.');
        }

        $tokens = explode('/', strtolower($composer['name']));
        if (2 !== count($tokens) || '' === $tokens[1]) {
            return $this->result('skipped', 'Target does not expose the Canon018 component/subject package identity.');
        }

        $psr4 = $composer['autoload']['psr-4'] ?? [];
        if (!is_array($psr4) || [] === array_filter(array_keys($psr4), static fn (mixed $key): bool => is_string($key) && str_starts_with($key, 'App\\'))) {
            return $this->result('skipped', 'Target does not use the SmartResponsor App\\<Component>\\ component model.');
        }

        $configPath = $context->targetPath.'/config';
        if (!is_dir($configPath)) {
            return $this->result('skipped', 'Target has no config/ tree.');
        }

        $subjectPrefix = str_replace('-', '_', $tokens[1]).'_';
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($configPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['yaml', 'yml'], true)) {
                continue;
            }

            $basename = strtolower($file->getBasename());
            if ($this->isConventionalBootstrap($basename) || str_starts_with($basename, $subjectPrefix)) {
                continue;
            }

            $violations[] = $this->relative($context, $file->getPathname()).' must start with '.$subjectPrefix.' or use an established framework/vendor bootstrap filename.';
        }

        return [] === $violations
            ? $this->result('passed', 'Component-owned YAML filenames use the Canon018 subject prefix or an established bootstrap convention.')
            : $this->result('failed', 'Component-owned YAML filename prefix violations found.', $violations);
    }

    private function isConventionalBootstrap(string $basename): bool
    {
        $normalized = str_ends_with($basename, '.yml') ? substr($basename, 0, -4).'.yaml' : $basename;
        if (in_array($normalized, self::CONVENTIONAL_BOOTSTRAP_FILES, true)) {
            return true;
        }

        return 1 === preg_match('/^(?:services|routes)_(?:dev|test|prod)\.ya?ml$/', $basename);
    }
}
