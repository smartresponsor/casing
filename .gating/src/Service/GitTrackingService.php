<?php

declare(strict_types=1);

namespace Gating\Gate\Service;

/**
 * Resolves whether repository paths are tracked by Git without shell command composition.
 */
final class GitTrackingService
{
    /**
     * Returns true when Git tracks the path, false when it does not, and null when Git state cannot be resolved.
     */
    public function isTracked(string $workingDirectory, string $relativePath): ?bool
    {
        $repositoryProbe = $this->runGit(['-C', $workingDirectory, 'rev-parse', '--is-inside-work-tree']);
        if (null === $repositoryProbe || 0 !== $repositoryProbe['exitCode'] || 'true' !== trim($repositoryProbe['stdout'])) {
            return null;
        }

        $trackingProbe = $this->runGit(['-C', $workingDirectory, 'ls-files', '--error-unmatch', '--', $relativePath]);
        if (null === $trackingProbe) {
            return null;
        }

        if (0 === $trackingProbe['exitCode']) {
            return true;
        }

        if (1 === $trackingProbe['exitCode']) {
            return false;
        }

        return null;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{exitCode:int,stdout:string}|null
     */
    private function runGit(array $arguments): ?array
    {
        $process = @proc_open(
            ['git', ...$arguments],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            return null;
        }

        $stdout = '';
        foreach ($pipes as $index => $pipe) {
            if (!is_resource($pipe)) {
                continue;
            }

            $contents = stream_get_contents($pipe);
            if (1 === $index && false !== $contents) {
                $stdout = $contents;
            }
            fclose($pipe);
        }

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout,
        ];
    }
}
