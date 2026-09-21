<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Rule\Mirror;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleInterface;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the service interface mirror rule implementation used by the Gating runtime and rule execution flow.
 */
final class ServiceInterfaceMirrorRule implements RuleInterface
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'mirror.service_interface';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $configuration = $context->profile['component']['service_interface_mirror'] ?? $context->profile['service_interface_mirror'] ?? true;
        if (in_array($configuration, [false, 'false', 'disabled'], true)) {
            return new RuleResult($this->id(), 'skipped', 'Service interface mirror validation is disabled by profile.');
        }

        $serviceRoot = $context->targetPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Service';
        $interfaceRoot = $context->targetPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'ServiceInterface';
        if (!is_dir($interfaceRoot)) {
            return new RuleResult($this->id(), 'skipped', 'No mirrored ServiceInterface contracts were discovered.');
        }

        $baseNamespace = trim((string) ($context->profile['component']['namespace'] ?? $context->profile['namespace'] ?? 'App'), '\\');
        $hits = [];
        $candidateCount = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($interfaceRoot, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $relativeFromInterface = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($interfaceRoot))), '/');
            $interfaceClass = basename($relativeFromInterface, '.php');
            if (!str_ends_with($interfaceClass, 'Interface')) {
                $hits[] = 'src/ServiceInterface/'.$relativeFromInterface.' must use the Interface suffix.';
                continue;
            }

            ++$candidateCount;
            $serviceClass = substr($interfaceClass, 0, -strlen('Interface'));
            if (!str_ends_with($serviceClass, 'Service')) {
                $hits[] = sprintf(
                    'src/ServiceInterface/%s is not a mirrored service interface; expected a name ending in ServiceInterface.',
                    $relativeFromInterface,
                );
                continue;
            }

            $relativeDirectory = dirname($relativeFromInterface);
            $serviceRelative = ('.' === $relativeDirectory ? '' : $relativeDirectory.'/').$serviceClass.'.php';
            $servicePath = $serviceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $serviceRelative);
            if (!is_file($servicePath)) {
                $hits[] = sprintf(
                    'src/ServiceInterface/%s has no mirrored service src/Service/%s.',
                    $relativeFromInterface,
                    $serviceRelative,
                );
                continue;
            }

            $interfaceContents = file_get_contents($file->getPathname());
            $serviceContents = file_get_contents($servicePath);
            if (false === $interfaceContents || false === $serviceContents) {
                $hits[] = 'Mirror files could not be read for src/ServiceInterface/'.$relativeFromInterface;
                continue;
            }

            if (1 !== preg_match('/\binterface\s+'.preg_quote($interfaceClass, '/').'\b/', $interfaceContents)) {
                $hits[] = 'src/ServiceInterface/'.$relativeFromInterface.' does not declare interface '.$interfaceClass;
                continue;
            }

            $namespaceSuffix = $this->namespaceSuffix($relativeDirectory);
            $expectedInterfaceNamespace = $baseNamespace.'\\ServiceInterface'.$namespaceSuffix;
            if (!$this->declaresNamespace($interfaceContents, $expectedInterfaceNamespace)) {
                $hits[] = 'src/ServiceInterface/'.$relativeFromInterface.' does not declare expected namespace '.$expectedInterfaceNamespace;
            }

            $expectedServiceNamespace = $baseNamespace.'\\Service'.$namespaceSuffix;
            if (!$this->declaresNamespace($serviceContents, $expectedServiceNamespace)) {
                $hits[] = 'src/Service/'.$serviceRelative.' does not declare expected namespace '.$expectedServiceNamespace;
            }

            if (1 !== preg_match('/\bclass\s+'.preg_quote($serviceClass, '/').'\b/', $serviceContents)) {
                $hits[] = 'src/Service/'.$serviceRelative.' does not declare class '.$serviceClass;
                continue;
            }

            if (!$this->serviceImplementsInterface($serviceContents, $interfaceClass)) {
                $hits[] = 'src/Service/'.$serviceRelative.' does not implement '.$interfaceClass;
                continue;
            }

            $serviceMethods = $this->publicMethodNames($serviceContents);
            foreach ($this->publicMethodNames($interfaceContents) as $method) {
                if (!in_array($method, $serviceMethods, true)) {
                    $hits[] = sprintf(
                        'src/Service/%s is missing public method %s required by %s.',
                        $serviceRelative,
                        $method,
                        $interfaceClass,
                    );
                }
            }
        }

        if ([] !== $hits) {
            return new RuleResult(
                $this->id(),
                'failed',
                'Discovered ServiceInterface contracts must mirror service names, paths, namespaces, and bindings.',
                $hits,
            );
        }

        if (0 === $candidateCount) {
            return new RuleResult($this->id(), 'skipped', 'No mirrored ServiceInterface contracts were discovered.');
        }

        return new RuleResult(
            $this->id(),
            'passed',
            sprintf('%d mirrored ServiceInterface contract(s) have valid names, paths, namespaces, and bindings.', $candidateCount),
        );
    }

    /**
     * Executes the service implements interface responsibility defined by this Gating component.
     */
    private function serviceImplementsInterface(string $contents, string $interfaceClass): bool
    {
        return 1 === preg_match('/\bclass\s+\w+[^\{]*\bimplements\b[^\{]*\b'.preg_quote($interfaceClass, '/').'\b/s', $contents);
    }

    /**
     * Executes the declares namespace responsibility defined by this Gating component.
     */
    private function declaresNamespace(string $contents, string $namespace): bool
    {
        return 1 === preg_match('/\bnamespace\s+'.preg_quote($namespace, '/').'\s*;/', $contents);
    }

    /** @return list<string> */
    private function publicMethodNames(string $contents): array
    {
        preg_match_all('/\bpublic\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $contents, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Executes the namespace suffix responsibility defined by this Gating component.
     */
    private function namespaceSuffix(string $relativeDirectory): string
    {
        $relativeDirectory = trim(str_replace(['/', '.'], ['\\', ''], $relativeDirectory), '\\');

        return '' === $relativeDirectory ? '' : '\\'.$relativeDirectory;
    }
}
