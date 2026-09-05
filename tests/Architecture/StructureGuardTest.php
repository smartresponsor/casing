<?php

declare(strict_types=1);

namespace App\Casing\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StructureGuardTest extends TestCase
{
    private const COMPONENT_TOKENS = [
        'Administering',
        'Attaching',
        'Cataloging',
        'Cruding',
        'Interfacing',
        'Navigating',
        'Objecting',
        'Ordering',
        'Paying',
        'Relating',
        'Shipping',
        'Viewing',
    ];

    /** @return iterable<string, array{string, string}> */
    public static function phpFiles(): iterable
    {
        $sourceRoot = dirname(__DIR__, 2).'/src';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== strtolower($file->getExtension())) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($sourceRoot) + 1));
            yield $relativePath => [$file->getPathname(), $relativePath];
        }
    }

    #[DataProvider('phpFiles')]
    public function testSourceFilesRespectCanonicalStructure(string $filePath, string $relativePath): void
    {
        foreach (['Contract/', 'Integration/', 'Event/Domain/', 'Dto/'] as $forbiddenPath) {
            self::assertFalse(
                str_starts_with($relativePath, $forbiddenPath),
                sprintf('Forbidden Casing source path "%s" found in "%s".', $forbiddenPath, $relativePath),
            );
        }

        $segments = explode('/', $relativePath);
        self::assertNotContains(
            $segments[0],
            self::COMPONENT_TOKENS,
            sprintf('Component token "%s" appears too early in "%s".', $segments[0], $relativePath),
        );

        $source = file_get_contents($filePath);
        self::assertIsString($source, sprintf('Unable to read "%s".', $relativePath));

        preg_match('/^namespace\s+([^;]+);/m', $source, $namespaceMatch);
        preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $source, $classMatch);

        self::assertArrayHasKey(1, $namespaceMatch, sprintf('No namespace found in "%s".', $relativePath));
        self::assertArrayHasKey(1, $classMatch, sprintf('No class-like declaration found in "%s".', $relativePath));

        $directory = dirname($relativePath);
        $expectedNamespace = 'App\\Casing'.('.' === $directory ? '' : '\\'.str_replace('/', '\\', $directory));
        $expectedClass = pathinfo($relativePath, PATHINFO_FILENAME);

        self::assertSame($expectedNamespace, $namespaceMatch[1], sprintf('PSR-4 namespace mismatch in "%s".', $relativePath));
        self::assertSame($expectedClass, $classMatch[1], sprintf('Class/file mismatch in "%s".', $relativePath));

        if (str_starts_with($relativePath, 'DTO/')) {
            self::assertStringEndsWith('DTO', $expectedClass, sprintf('DTO file "%s" must use the DTO suffix.', $relativePath));
            self::assertStringEndsWith('DTO', $classMatch[1], sprintf('DTO class "%s" must use the DTO suffix.', $classMatch[1]));
        }
    }
}
