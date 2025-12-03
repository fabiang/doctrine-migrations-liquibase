<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Fabiang\Doctrine\Migrations\Liquibase\Helper\VersionHelper;

trait ColumnDiffTrait
{
    private function columnDiff(Column $oldColumn, Column $newColumn, array $changedProperties = []): ColumnDiff
    {
        if (VersionHelper::isDBALVersion4()) {
            return new ColumnDiff($oldColumn, $newColumn);
        }

        return new ColumnDiff(
            $oldColumn->getName(),
            $newColumn,
            $changedProperties,
            $oldColumn
        );
    }
}
