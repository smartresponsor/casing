<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

final class Canon044ObjectingSystemFieldNamingRule extends AbstractCanonRule
{
    public function id(): string
    {
        return 'canon.044.objecting_system_field_naming';
    }

    public function check(RuleContext $context): RuleResult
    {
        $composerPath = $context->targetPath.'/composer.json';
        if (!is_file($composerPath)) {
            return $this->result('skipped', 'Target has no composer.json.');
        }
        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer)) {
            return $this->result('failed', 'composer.json is invalid.');
        }
        $requirements = array_merge(
            is_array($composer['require'] ?? null) ? $composer['require'] : [],
            is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [],
        );
        if ('objecting/object' !== ($composer['name'] ?? null) && !array_key_exists('objecting/object', $requirements)) {
            return $this->result('skipped', 'Target is neither Objecting nor an objecting/object consumer.');
        }

        $hits = [];
        foreach ($this->phpFiles($context) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $relative = $this->relative($context, $file->getPathname());
            preg_match_all("/ORM\\\\Column\\([^)]*name:\\s*['\"]((?:objecting_|object_)[a-z0-9_]+)['\"]/i", $contents, $columns);
            foreach ($columns[1] as $name) {
                $hits[] = sprintf('%s: Doctrine column "%s" must be entity-native.', $relative, $name);
            }
            preg_match_all(
                '/#\\[ORM\\\\Column\\([^]]*\\)\\](?:\\s*#\\[[^]]+\\])*\\s*(?:public|protected|private)\\s+(?:readonly\\s+)?[^$;]+\\$((?:objecting|object)[A-Z][A-Za-z0-9_]*)/s',
                $contents,
                $properties,
            );
            foreach ($properties[1] as $name) {
                $hits[] = sprintf('%s: Doctrine-mapped property "$%s" must be entity-native.', $relative, $name);
            }
        }

        return [] === $hits
            ? $this->result('passed', 'Objecting system fields use entity-native Doctrine names.')
            : $this->result('failed', 'Objecting-prefixed persisted system fields found.', array_values(array_unique($hits)));
    }
}
