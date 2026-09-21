<?php

// Copyright (c) 2026 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace Gating\Gate\Service;

/**
 * Provides the policy export service implementation used by the Gating runtime and rule execution flow.
 */
final class PolicyExportService
{
    /**
     * @return array{artifact:array<string,mixed>,canonical:array<string,mixed>|list<mixed>,hash_base:string}
     */
    public function export(string $repoRoot, ?string $policyRoot = null): array
    {
        $repoRoot = $this->normalizePath($repoRoot);
        $policyRoot = $this->normalizePath($policyRoot ?? $repoRoot.'/.gating');

        $version = $this->readTrimmedFile($policyRoot.'/VERSION');
        $rulesCatalog = $this->readJsonFile($policyRoot.'/policy/rule/catalog.json');
        $reviewContract = $this->readJsonFile($policyRoot.'/contract/review-contract.v1.json');
        $validationSchema = $this->readJsonFile($policyRoot.'/schema/review-result.v1.schema.json');
        $sourceCommit = $this->gitCommit($repoRoot);

        $artifact = [
            'schema' => 'gating.policy.export.v1',
            'policy_version' => $version,
            'schema_version' => 'v1',
            'generated_at' => gmdate('c'),
            'source_commit' => $sourceCommit,
            'rules' => $rulesCatalog,
            'review_contract' => $reviewContract,
            'validation_schema' => $validationSchema,
        ];

        $hashSource = $artifact;
        unset($hashSource['generated_at']);
        $canonical = $this->canonicalize($artifact);
        $canonicalHashSource = $this->canonicalize($hashSource);
        $hashBase = json_encode($canonicalHashSource, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $artifact['content_hash'] = 'sha256:'.hash('sha256', $hashBase);

        return [
            'artifact' => $artifact,
            'canonical' => $canonical,
            'hash_base' => $hashBase,
        ];
    }

    /**
     * Executes the git commit responsibility defined by this Gating component.
     */
    private function gitCommit(string $repoRoot): ?string
    {
        $command = sprintf('git -C %s rev-parse HEAD 2>NUL', escapeshellarg($repoRoot));
        $output = [];
        $code = 1;
        @exec($command, $output, $code);
        if (0 !== $code || [] === $output) {
            return null;
        }

        $commit = trim((string) $output[0]);

        return '' !== $commit ? $commit : null;
    }

    /** @return array<string, mixed> */
    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Missing policy artifact file: %s', $path));
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Policy artifact file is not a JSON object: %s', $path));
        }

        return $decoded;
    }

    /**
     * Reads trimmed file from the repository-owned Gating input surface.
     */
    private function readTrimmedFile(string $path): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Missing policy version file: %s', $path));
        }

        $contents = trim((string) file_get_contents($path));
        if ('' === $contents) {
            throw new \RuntimeException(sprintf('Policy version file is empty: %s', $path));
        }

        return $contents;
    }

    /**
     * Normalizes path into the canonical representation used by Gating.
     */
    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function canonicalize(array $value): array
    {
        return $this->sortValue($value);
    }

    /**
     * Executes the sort value responsibility defined by this Gating component.
     */
    private function sortValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ([] === $value) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map($this->sortValue(...), $value);
        }

        $sorted = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $sorted[$key] = $this->sortValue($value[$key]);
        }

        return $sorted;
    }
}
