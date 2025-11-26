<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Output;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMElement;
use Fabiang\Doctrine\Migrations\Liquibase\DBAL\IndexColumns;
use Fabiang\Doctrine\Migrations\Liquibase\DBAL\QualifiedName;
use Override;

use function array_key_exists;
use function array_map;
use function assert;
use function count;
use function get_class;
use function implode;
use function in_array;
use function preg_replace;
use function sprintf;
use function strval;
use function uniqid;

class LiquibaseDOMDocumentOutput implements LiquibaseOutputInterface
{
    private DOMDocument $document;
    private LiquibaseOutputOptions $options;
    private AbstractPlatform $platform;
    private DOMElement $root;

    public function __construct(?LiquibaseOutputOptions $options = null, ?DOMDocument $document = null)
    {
        if (null === $options) {
            $options = new LiquibaseOutputOptions();
        }

        $this->options = $options;

        if (null === $document) {
            $document                     = new DOMDocument();
            $document->preserveWhiteSpace = false;
            $document->formatOutput       = true;
            $this->document               = $document;
        } else {
            $this->document = $document;
        }

        $this->root     = $this->document->createElement('databaseChangeLog');
        $this->platform = new MySQLPlatform();
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getDocument(): DOMDocument
    {
        return $this->document;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getOptions(): LiquibaseOutputOptions
    {
        return $this->options;
    }

    #[Override]
    public function getResult(): DOMDocument
    {
        return $this->document;
    }

    protected function createChangeSet(string $id): DOMElement
    {
        $changeSet = $this->document->createElement('changeSet');
        $changeSet->setAttribute('author', $this->options->getChangeSetAuthor());
        $sanitizedId = preg_replace('/[_\.]/', '-', $id);
        assert($sanitizedId !== null);
        $changeSet->setAttribute(
            'id',
            $this->options->isChangeSetUniqueId() ? $sanitizedId . '-' . uniqid() : $sanitizedId
        );
        $this->root->appendChild($changeSet);
        return $changeSet;
    }

    #[Override]
    public function createSchema(string $newNamespace): void
    {
        $changeSetElt = $this->createChangeSet('create-schema-' . $newNamespace);

        try {
            $sql = $this->platform->getCreateSchemaSQL($newNamespace);
        } catch (DBALException $e) {
            $sql = "CREATE SCHEMA `$newNamespace`";
        }

        $sqlElement = $this->document->createElement('sql');

        $sqlTextNode = $this->document->createTextNode($sql);
        $sqlElement->appendChild($sqlTextNode);

        $changeSetElt->appendChild($sqlElement);
        $this->root->appendChild($changeSetElt);
    }

    #[Override]
    public function dropForeignKey(ForeignKeyConstraint $orphanedForeignKey, Table $localTable): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('drop-foreign-key-' . $orphanedForeignKey->getName());

        $tableName      = QualifiedName::fromAsset($localTable);
        $foreignKeyName = QualifiedName::fromAsset($orphanedForeignKey);

        $dropForeignKeyElement = $this->document->createElement('dropForeignKeyConstraint');

        $namespaceName = $tableName->getNamespaceName();
        if (null !== $namespaceName) {
            $dropForeignKeyElement->setAttribute('baseTableSchemaName', $namespaceName);
        }

        $dropForeignKeyElement->setAttribute('baseTableName', $tableName->getName());
        $dropForeignKeyElement->setAttribute('constraintName', $foreignKeyName->getName());

        $changeSetElt->appendChild($dropForeignKeyElement);
        $this->root->appendChild($changeSetElt);
    }

    #[Override]
    public function alterSequence(Sequence $sequence): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $commentElt = $this->document->createComment(
            ' alterSequence is not supported (sequence: ' . $sequence->getName() . ')'
        );
        $this->root->appendChild($commentElt);
    }

    #[Override]
    public function dropSequence(Sequence $sequence): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('drop-sequence-' . $sequence->getName());

        $sequenceName    = QualifiedName::fromAsset($sequence);
        $dropSequenceElt = $this->document->createElement('dropSequence');

        $namespaceName = $sequenceName->getNamespaceName();
        if (null !== $namespaceName) {
            $dropSequenceElt->setAttribute('schemaName', $namespaceName);
        }

        $dropSequenceElt->setAttribute('sequenceName', $sequenceName->getName());

        $changeSetElt->appendChild($dropSequenceElt);
        $this->root->appendChild($changeSetElt);
    }

    #[Override]
    public function createSequence(Sequence $sequence): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('create-sequence-' . $sequence->getName());

        $sequenceName      = QualifiedName::fromAsset($sequence);
        $createSequenceElt = $this->document->createElement('createSequence');

        $namespaceName = $sequenceName->getNamespaceName();
        if (null !== $namespaceName) {
            $createSequenceElt->setAttribute('schemaName', $namespaceName);
        }

        $createSequenceElt->setAttribute('sequenceName', $sequenceName->getName());
        $createSequenceElt->setAttribute('startValue', strval($sequence->getInitialValue()));

        $changeSetElt->appendChild($createSequenceElt);
        $this->root->appendChild($changeSetElt);
    }

    /**
     * @return string
     */
    protected function getColumnType(Column $column)
    {
        if ($column->getColumnDefinition()) {
            return $column->getColumnDefinition();
        }

        if ($this->options->isUsePlatformTypes()) {
            $sqlType = $column->getType()->getSQLDeclaration($column->toArray(), $this->platform);
            $sqlType = preg_replace('/\(.*?\)/', '', $sqlType);
            assert($sqlType !== null);
        } else {
            $sqlType = match (get_class($column->getType())) {
                Types\DateImmutableType::class => 'date',
                Types\DateType::class => 'date',
                Types\DateTimeImmutableType::class => 'datetime',
                Types\DateTimeType::class => 'datetime',
                Types\IntegerType::class => 'int',
                Types\FloatType::class => 'float',
                default => 'varchar',
            };
        }

        $length = $column->getLength();
        if ($length !== null) {
            $sqlType .= '(' . $length . ')';
        }

        return $sqlType;
    }

    protected function fillColumnAttributes(
        DOMElement $columnElt,
        Column $column,
        IndexColumns $indexColumns
    ): void {
        $columnName = QualifiedName::fromAsset($column);
        $columnElt->setAttribute('name', $columnName->getName());
        $columnType = $this->getColumnType($column);
        $columnElt->setAttribute('type', $columnType);

        if ($remarks = $column->getComment()) {
            $columnElt->setAttribute('remarks', $remarks);
        }
        if ($defaultValue = $column->getDefault()) {
            $columnElt->setAttribute('defaultValue', $defaultValue);
        }

        /**
         * @psalm-suppress InternalMethod
         */
        $primaryKey           = in_array($column->getName(), $indexColumns->getPrimaryKeyColumns());
        $unique               = false;
        $uniqueConstraintName = null;

        /**
         * @psalm-suppress InternalMethod
         */
        if (array_key_exists($column->getName(), $indexColumns->getUniqueColumns())) {
            $unique = true;
            /**
             * @psalm-suppress InternalMethod
             */
            $uniqueConstraintName = $indexColumns->getUniqueColumns()[$column->getName()]->getName();
        }

        $nullable = ! $column->getNotnull();

        if ($primaryKey || ! $nullable || $unique) {
            $constraintsElt = $this->document->createElement('constraints');
            if ($primaryKey) {
                $constraintsElt->setAttribute('primaryKey', "true");
            }
            if (! $nullable) {
                $constraintsElt->setAttribute('nullable', "false");
            }
            if ($unique) {
                $constraintsElt->setAttribute('unique', "true");
            }
            if ($uniqueConstraintName) {
                $constraintsElt->setAttribute('uniqueConstraintName', $uniqueConstraintName);
            }

            $columnElt->appendChild($constraintsElt);
        }
    }

    #[Override]
    public function createTable(Table $table): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('create-table-' . $table->getName());

        $createTableElt = $this->document->createElement('createTable');

        $tableName = QualifiedName::fromAsset($table);

        $namespaceName = $tableName->getNamespaceName();
        if (null !== $namespaceName) {
            $createTableElt->setAttribute('schemaName', $namespaceName);
        }

        /**
         * @psalm-suppress InternalMethod
         */
        $createTableElt->setAttribute('tableName', $tableName->getName());

        $indexColumns = new IndexColumns($table);

        foreach ($table->getColumns() as $column) {
            $columnElt = $this->document->createElement('column');

            $this->fillColumnAttributes($columnElt, $column, $indexColumns);

            $createTableElt->appendChild($columnElt);
        }

        $changeSetElt->appendChild($createTableElt);

        foreach ($indexColumns->getOtherIndexes() as $index) {
            $createIndexElt = $this->document->createElement('createIndex');

            $namespaceName = $tableName->getNamespaceName();
            if (null !== $namespaceName) {
                $createIndexElt->setAttribute('schemaName', $namespaceName);
            }

            /**
             * @psalm-suppress InternalMethod
             */
            $createIndexElt->setAttribute('tableName', $tableName->getName());
            /**
             * @psalm-suppress InternalMethod
             */
            $createIndexElt->setAttribute('indexName', $index->getName());

            if ($index->isUnique()) {
                $createIndexElt->setAttribute('unique', 'true');
            }

            foreach ($index->getColumns() as $column) {
                $columnElt = $this->document->createElement('column');
                $columnElt->setAttribute('name', $column);
                $createIndexElt->appendChild($columnElt);
            }

            $changeSetElt->appendChild($createIndexElt);
        }

        $this->root->appendChild($changeSetElt);
    }

    protected function fillForeignKeyAttributes(
        DOMElement $addForeignKeyConstraintElt,
        ForeignKeyConstraint $foreignKey,
        Table $table
    ): void {
        /**
         * @psalm-suppress InternalMethod
         */
        $addForeignKeyConstraintElt->setAttribute('constraintName', $foreignKey->getName());

        $tableName = QualifiedName::fromAsset($table);

        $namespaceName = $tableName->getNamespaceName();
        if (null !== $namespaceName) {
            $addForeignKeyConstraintElt->setAttribute('baseTableSchemaName', $namespaceName);
        }
        $addForeignKeyConstraintElt->setAttribute('baseTableName', $tableName->getName());
        $addForeignKeyConstraintElt->setAttribute(
            'baseColumnNames',
            implode(',', $this->unqualifiedNameIdentifiersToArray($foreignKey->getReferencingColumnNames()))
        );

        $referencedTableName = QualifiedName::fromQualifiedName($foreignKey->getForeignTableName());

        $namespaceName = $referencedTableName->getNamespaceName();
        if (null !== $namespaceName) {
            $addForeignKeyConstraintElt->setAttribute('referencedTableSchemaName', $namespaceName);
        }
        $addForeignKeyConstraintElt->setAttribute('referencedTableName', $referencedTableName->getName());
        $addForeignKeyConstraintElt->setAttribute(
            'referencedColumnNames',
            implode(',', $this->unqualifiedNameIdentifiersToArray($foreignKey->getReferencedColumnNames()))
        );
    }

    private function unqualifiedNameIdentifiersToArray(array $identifiers): array
    {
        return array_map(function (UnqualifiedName $name) {
            return $name->toString();
        }, $identifiers);
    }

    #[Override]
    public function createForeignKey(ForeignKeyConstraint $foreignKey, Table $table): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('create-foreign-keys-' . $table->getName());

        $addForeignKeyConstraintElt = $this->document->createElement('addForeignKeyConstraint');

        $this->fillForeignKeyAttributes($addForeignKeyConstraintElt, $foreignKey, $table);

        $changeSetElt->appendChild($addForeignKeyConstraintElt);
        $this->root->appendChild($changeSetElt);
    }

    #[Override]
    public function dropTable(Table $table): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('drop-table-' . $table->getName());
        $dropTableElt = $this->document->createElement('dropTable');

        $tableName = QualifiedName::fromAsset($table);

        $namespaceName = $tableName->getNamespaceName();
        if (null !== $namespaceName) {
            $dropTableElt->setAttribute('schemaName', $namespaceName);
        }

        $dropTableElt->setAttribute('tableName', $tableName->getName());
        // Should we add cascadeConstraints attribute ?
        // $dropTableElt->setAttribute('cascadeConstraints', 'false');

        $changeSetElt->appendChild($dropTableElt);
        $this->root->appendChild($changeSetElt);
    }

    /**
     * @throws SchemaException
     */
    #[Override]
    public function alterTable(TableDiff $tableDiff): void
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('alter-table-' . $tableDiff->getOldTable()->getName());

        $fromTableName = QualifiedName::fromAsset($tableDiff->getOldTable());

        $indexColumns = new IndexColumns($tableDiff->getOldTable());

        $this->alterTableAddedColumns($tableDiff, $fromTableName, $indexColumns, $changeSetElt);
        $this->alterTableAddedIndexes($tableDiff, $fromTableName, $indexColumns, $changeSetElt);
        $this->alterTableAddedForeignKeys($tableDiff, $changeSetElt);

        $this->alterTableRenamedColumns($tableDiff, $fromTableName, $changeSetElt);
        $this->alterTableRenamedIndexes($tableDiff, $fromTableName, $changeSetElt);

        foreach ($tableDiff->getChangedColumns() as $column) {
            $this->alterTableChangedColumn($column, $fromTableName, $changeSetElt);
        }

        foreach ($tableDiff->getRenamedIndexes() as $index) {
            $this->alterTableChangedIndex($index, $fromTableName, $changeSetElt);
        }

        foreach ($tableDiff->getModifiedForeignKeys() as $foreignKey) {
            $this->alterTableChangedForeignKey($foreignKey, $fromTableName, $changeSetElt);
        }

        $this->alterTableRemovedColumns($tableDiff, $fromTableName, $changeSetElt);
        $this->alterTableRemovedIndexes($tableDiff, $fromTableName, $changeSetElt);
        $this->alterTableRemovedForeignKeys($tableDiff, $fromTableName, $changeSetElt);

        $this->root->appendChild($changeSetElt);
    }

    protected function alterTableAddedColumns(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        IndexColumns $indexColumns,
        DOMElement $changeSetElt
    ): void {
        if (count($tableDiff->getAddedColumns()) > 0) {
            $addColumnElt = $this->document->createElement('addColumn');

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $addColumnElt->setAttribute('schemaName', $schemaName);
            }

            $addColumnElt->setAttribute('tableName', $fromTableName->getName());

            foreach ($tableDiff->getAddedColumns() as $column) {
                $columnElt = $this->document->createElement('column');

                $this->fillColumnAttributes($columnElt, $column, $indexColumns);

                $addColumnElt->appendChild($columnElt);
            }

            $changeSetElt->appendChild($addColumnElt);
        }
    }

    /**
     * @throws SchemaException
     */
    protected function alterTableAddedIndexes(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        IndexColumns $indexColumns,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getAddedIndexes() as $index) {
            $createIndexElt = $this->document->createElement('createIndex');

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $createIndexElt->setAttribute('schemaName', $schemaName);
            }
            $createIndexElt->setAttribute('tableName', $fromTableName->getName());
            /**
             * @psalm-suppress InternalMethod
             */
            $createIndexElt->setAttribute('indexName', $index->getName());
            $createIndexElt->setAttribute('unique', $index->isUnique() ? 'true' : 'false');

            foreach ($index->getColumns() as $columnName) {
                $columnElt = $this->document->createElement('column');

                if ($tableDiff->getOldTable()->hasColumn($columnName)) {
                    $column = $tableDiff->getOldTable()->getColumn($columnName);
                } else {
                    $column = $tableDiff->getAddedColumns()[$columnName];
                }

                $this->fillColumnAttributes($columnElt, $column, $indexColumns);

                $createIndexElt->appendChild($columnElt);
            }

            $changeSetElt->appendChild($createIndexElt);
        }
    }

    protected function alterTableAddedForeignKeys(TableDiff $tableDiff, DOMElement $changeSetElt): void
    {
        foreach ($tableDiff->getAddedForeignKeys() as $foreignKey) {
            $addForeignKeyConstraintElt = $this->document->createElement('addForeignKeyConstraint');

            $this->fillForeignKeyAttributes($addForeignKeyConstraintElt, $foreignKey, $tableDiff->getOldTable());

            $changeSetElt->appendChild($addForeignKeyConstraintElt);
        }
    }

    protected function alterTableRenamedColumns(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getChangedColumns() as $oldName => $columnDiff) {
            $renameColumnElt = $this->document->createElement('renameColumn');

            $columnName = QualifiedName::fromAsset($columnDiff->getNewColumn());

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $renameColumnElt->setAttribute('schemaName', $schemaName);
            }

            $renameColumnElt->setAttribute('tableName', $fromTableName->getName());
            $renameColumnElt->setAttribute('oldColumnName', $oldName);
            $renameColumnElt->setAttribute('newColumnName', $columnName->getName());

            $changeSetElt->appendChild($renameColumnElt);
        }
    }

    /**
     * @psalm-suppress PossiblyUnusedParam
     */
    protected function alterTableRenamedIndexes(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getRenamedIndexes() as $oldName => $index) {
            /**
             * @psalm-suppress InternalMethod
             */
            $commentElt = $this->document->createComment(
                ' renameIndex is not supported (index: ' . $oldName . ' => ' . $index->getName() . ')'
            );
            $changeSetElt->appendChild($commentElt);
        }
    }

    protected function alterTableRemovedColumns(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getDroppedColumns() as $column) {
            $dropColumnElt = $this->document->createElement('dropColumn');

            $columnName = QualifiedName::fromAsset($column);

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $dropColumnElt->setAttribute('schemaName', $schemaName);
            }
            $dropColumnElt->setAttribute('tableName', $fromTableName->getName());
            $dropColumnElt->setAttribute('columnName', $columnName->getName());

            $changeSetElt->appendChild($dropColumnElt);
        }
    }

    protected function alterTableRemovedIndexes(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getDroppedIndexes() as $index) {
            $dropIndexElt = $this->document->createElement('dropIndex');

            $indexName = QualifiedName::fromAsset($index);

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $dropIndexElt->setAttribute('schemaName', $schemaName);
            }

            $dropIndexElt->setAttribute('tableName', $fromTableName->getName());
            $dropIndexElt->setAttribute('indexName', $indexName->getName());

            $changeSetElt->appendChild($dropIndexElt);
        }
    }

    protected function alterTableRemovedForeignKeys(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getDroppedForeignKeys() as $foreignKey) {
            $dropForeignKeyConstraintElt = $this->document->createElement('dropForeignKeyConstraint');

            $foreignKeyName = QualifiedName::fromAsset($foreignKey);

            if ($baseTableSchemaName = $fromTableName->getNamespaceName()) {
                $dropForeignKeyConstraintElt->setAttribute('baseTableSchemaName', $baseTableSchemaName);
            }

            $dropForeignKeyConstraintElt->setAttribute('baseTableName', $fromTableName->getName());
            $dropForeignKeyConstraintElt->setAttribute('constraintName', $foreignKeyName->getName());

            $changeSetElt->appendChild($dropForeignKeyConstraintElt);
        }
    }

    private function alterTableChangedColumn(
        ColumnDiff $columnDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        $oldColunmName = QualifiedName::fromAsset($columnDiff->getOldColumn());
        $columnName    = QualifiedName::fromAsset($columnDiff->getNewColumn());

        if ($oldColunmName->getName() !== $columnName->getName()) {
            $renameColumnElt = $this->document->createElement('renameColumn');

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $renameColumnElt->setAttribute('schemaName', $schemaName);
            }
            $renameColumnElt->setAttribute('tableName', $fromTableName->getName());
            $renameColumnElt->setAttribute('oldColumnName', $oldColunmName->getName());
            $renameColumnElt->setAttribute('newColumnName', $columnName->getName());

            $changeSetElt->appendChild($renameColumnElt);
        }

        $properties = $columnDiff->countChangedProperties();

        if ($properties > 0) {
            $typeIndex = $columnDiff->hasTypeChanged();

            if ($typeIndex !== false) {
                $modifyDataTypeElt = $this->document->createElement('modifyDataType');

                if ($schemaName = $fromTableName->getNamespaceName()) {
                    $modifyDataTypeElt->setAttribute('schemaName', $schemaName);
                }

                $modifyDataTypeElt->setAttribute('tableName', $fromTableName->getName());
                $modifyDataTypeElt->setAttribute('columnName', $columnName->getName());
                $modifyDataTypeElt->setAttribute(
                    'newDataType',
                    $this->getColumnType($columnDiff->getNewColumn())
                );

                $changeSetElt->appendChild($modifyDataTypeElt);

                $properties -= 1;
            }
        }

        if ($properties > 0) {
            $changedProperties = [];
            foreach (
                [
                    'platformOption' => $columnDiff->hasPlatformOptionsChanged(),
                    'comment'        => $columnDiff->hasCommentChanged(),
                ] as $property => $changed
            ) {
                if ($changed) {
                    $changedProperties[] = $property;
                }
            }

            /**
             * @psalm-suppress InternalMethod
             */
            $commentElt = $this->document->createComment(
                sprintf(
                    ' Some column property changes are not supported (column: %s for properties [%s]) ',
                    $columnDiff->getOldColumn()->getName(),
                    implode(', ', $changedProperties)
                )
            );
            $changeSetElt->appendChild($commentElt);
        }
    }

    /**
     * @psalm-suppress PossiblyUnusedParam
     */
    protected function alterTableChangedIndex(
        Index $index,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        /**
         * @psalm-suppress InternalMethod
         */
        $commentElt = $this->document->createComment(
            ' index changes are not supported (index: ' . $index->getName() . ')'
        );
        $changeSetElt->appendChild($commentElt);
    }

    /**
     * @psalm-suppress PossiblyUnusedParam
     */
    protected function alterTableChangedForeignKey(
        ForeignKeyConstraint $foreignKey,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        /**
         * @psalm-suppress InternalMethod
         */
        $commentElt = $this->document->createComment(
            ' foreign key changes are not supported (foreignKey: ' . $foreignKey->getName() . ')'
        );
        $changeSetElt->appendChild($commentElt);
    }

    #[Override]
    public function started(EntityManagerInterface $em): void
    {
        $this->platform = $em->getConnection()->getDatabasePlatform();
        $this->root     = $this->document->createElement('databaseChangeLog');
    }

    #[Override]
    public function terminated(): void
    {
        $this->document->appendChild($this->root);
    }
}
