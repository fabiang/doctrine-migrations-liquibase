<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Fabiang\Doctrine\Migrations\Liquibase\Helper\VersionHelper;

trait SchemaDiffTrait
{
    private function schemaDiff(
        Schema $fromSchema,
        array $createdSchemas,
        array $droppedSchemas,
        array $createdTables,
        array $alteredTables,
        array $droppedTables,
        array $createdSequences,
        array $alteredSequences,
        array $droppedSequences
    ): SchemaDiff {
        if (VersionHelper::isDBALVersion4()) {
            return new SchemaDiff(
                $createdSchemas,
                $droppedSchemas,
                $createdTables,
                $alteredTables,
                $droppedTables,
                $createdSequences,
                $alteredSequences,
                $droppedSequences
            );
        }

        return new SchemaDiff(
            $createdTables,
            $alteredTables,
            $droppedTables,
            $fromSchema,
            $createdSchemas,
            $droppedSchemas,
            $createdSequences,
            $alteredSequences,
            $droppedSequences
        );
    }
}
