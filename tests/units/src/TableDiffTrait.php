<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Fabiang\Doctrine\Migrations\Liquibase\Helper\VersionHelper;

trait TableDiffTrait
{
    private function tableDiff(
        Table $oldTable,
        array $addedColumns = [],
        array $changedColumns = [],
        array $droppedColumns = [],
        array $renamedColumns = [],
        array $addedIndexes = [],
        array $modifiedIndexes = [],
        array $droppedIndexes = [],
        array $renamedIndexes = [],
        array $addedForeignKeys = [],
        array $modifiedForeignKeys = [],
        array $droppedForeignKeys = []
    ): TableDiff {
        if (VersionHelper::isDBALVersion4()) {
            return new TableDiff(
                $oldTable,
                $addedColumns,
                $changedColumns,
                $droppedColumns,
                $addedIndexes,
                $modifiedIndexes,
                $droppedIndexes,
                $renamedIndexes,
                $addedForeignKeys,
                $modifiedForeignKeys,
                $droppedForeignKeys
            );
        }

        return new TableDiff(
            $oldTable->getName(),
            $addedColumns,
            $changedColumns,
            $droppedColumns,
            $addedIndexes,
            $modifiedIndexes,
            $droppedIndexes,
            $oldTable,
            $addedForeignKeys,
            $modifiedForeignKeys,
            $droppedForeignKeys,
            $renamedColumns,
            $renamedIndexes,
        );
    }
}
