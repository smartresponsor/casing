<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        dirname(__DIR__, 4).'/src',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withTypeCoverageDocblockLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withCodingStyleLevel(0);
