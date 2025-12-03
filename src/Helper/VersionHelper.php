<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Helper;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

final class VersionHelper
{
    private static ?bool $dbalVersion4 = null;

    public static function isDBALVersion4(): bool
    {
        if (static::$dbalVersion4 === null) {
            static::$dbalVersion4 = InstalledVersions::satisfies(new VersionParser(), 'doctrine/dbal', '^4.0');
        }

        return static::$dbalVersion4;
    }
}
