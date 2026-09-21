<?php

declare(strict_types=1);

namespace Gating\Gate\Tests\Unit\Registry;

use Gating\Gate\Registry\RuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Protects the canonical test-tooling and coverage rule registrations.
 */
final class RuleRegistryTest extends TestCase
{
    public function testTestingCanonRulesAreRegistered(): void
    {
        $ids = (new RuleRegistry())->ids();

        self::assertContains('canon.039.php_test_tooling', $ids);
        self::assertContains('canon.040.php_test_coverage', $ids);
    }
}
