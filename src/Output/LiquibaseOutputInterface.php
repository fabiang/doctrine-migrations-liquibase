<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Output;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\ORM\EntityManagerInterface;

interface LiquibaseOutputInterface
{
    public function createSchema(string $newNamespace): void;

    public function dropForeignKey(ForeignKeyConstraint $orphanedForeignKey, Table $localTable): void;

    public function alterSequence(Sequence $sequence): void;

    public function dropSequence(Sequence $sequence): void;

    public function createSequence(Sequence $sequence): void;

    public function createTable(Table $table): void;

    public function createForeignKey(ForeignKeyConstraint $foreignKey, Table $table): void;

    public function dropTable(Table $table): void;

    public function alterTable(TableDiff $tableDiff): void;

    public function started(EntityManagerInterface $em): void;

    public function terminated(): void;

    public function getResult(): mixed;
}
