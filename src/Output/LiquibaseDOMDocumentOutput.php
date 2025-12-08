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
use Fabiang\Doctrine\Migrations\Liquibase\Helper\VersionHelper;
use Override;

use function array_key_exists;
use function array_map;
use function assert;
use function count;
use function get_class;
use function implode;
use function in_array;
use function is_bool;
use function is_numeric;
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

    protected function getColumnType(Column $column): string
    {
        $columDef = $column->getColumnDefinition();
        if ($columDef !== null) {
            return $columDef;
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
            // phpcs:disable
            };
            // phpcs:enable
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

        if (VersionHelper::isDBALVersion4()) {
            /**
             * @psalm-suppress UndefinedMethod
             */
            $addForeignKeyConstraintElt->setAttribute(
                'baseColumnNames',
                implode(',', $this->unqualifiedNameIdentifiersToArray($foreignKey->getReferencingColumnNames()))
            );
        } else {
            $addForeignKeyConstraintElt->setAttribute('baseColumnNames', implode(',', $foreignKey->getLocalColumns()));
        }

        $referencedTableName = QualifiedName::fromQualifiedName($foreignKey->getForeignTableName());

        $namespaceName = $referencedTableName->getNamespaceName();
        if (null !== $namespaceName) {
            $addForeignKeyConstraintElt->setAttribute('referencedTableSchemaName', $namespaceName);
        }
        $addForeignKeyConstraintElt->setAttribute('referencedTableName', $referencedTableName->getName());

        if (VersionHelper::isDBALVersion4()) {
            /**
             * @psalm-suppress UndefinedMethod
             */
            $addForeignKeyConstraintElt->setAttribute(
                'referencedColumnNames',
                implode(',', $this->unqualifiedNameIdentifiersToArray($foreignKey->getReferencedColumnNames()))
            );
        } else {
            $addForeignKeyConstraintElt->setAttribute(
                'referencedColumnNames',
                implode(',', $foreignKey->getForeignColumns())
            );
        }
    }

    private function unqualifiedNameIdentifiersToArray(array $identifiers): array
    {
        return array_map(
            /**
             * @psalm-suppress UndefinedClass
             */
            function (UnqualifiedName $name) {
                return $name->toString();
            },
            $identifiers
        );
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
        $oldTable = $tableDiff->getOldTable();
        /**
         * @psalm-suppress TypeDoesNotContainNull
         */
        if ($oldTable === null) {
            return;
        }

        if ($tableDiff->isEmpty()) {
            return;
        }

        /**
         * @psalm-suppress InternalMethod
         */
        $changeSetElt = $this->createChangeSet('alter-table-' . $oldTable->getName());

        $fromTableName = QualifiedName::fromAsset($oldTable);

        $indexColumns = new IndexColumns($oldTable);

        $this->alterTableAddedColumns($tableDiff, $fromTableName, $indexColumns, $changeSetElt);
        $this->alterTableAddedIndexes($tableDiff, $fromTableName, $indexColumns, $changeSetElt);
        $this->alterTableAddedForeignKeys($tableDiff, $changeSetElt);

        $this->alterTableRenamedColumns($tableDiff, $fromTableName, $changeSetElt);
        $this->alterTableRenamedIndexes($tableDiff, $fromTableName, $changeSetElt);

        if (VersionHelper::isDBALVersion4()) {
            /**
             * @psalm-suppress UndefinedMethod
             */
            $changedColumns = $tableDiff->getChangedColumns();
        } else {
            $changedColumns = $tableDiff->getModifiedColumns();
        }

        foreach ($changedColumns as $column) {
            $this->alterTableChangedColumn($column, $fromTableName, $changeSetElt);
        }

        foreach ($tableDiff->getModifiedForeignKeys() as $foreignKey) {
            $this->alterTableChangedForeignKey($foreignKey, $fromTableName, $changeSetElt);
        }

        $this->alterTableRemovedColumns($tableDiff, $fromTableName, $changeSetElt);
        $this->alterTableRemovedIndexes($tableDiff, $fromTableName, $changeSetElt);
        $this->alterTableRemovedForeignKeys($tableDiff, $fromTableName, $changeSetElt);

        if ($changeSetElt->firstChild !== null) {
            $this->root->appendChild($changeSetElt);
        }
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
        $oldTable = $tableDiff->getOldTable();
        /**
         * @psalm-suppress TypeDoesNotContainNull
         */
        if ($oldTable === null) {
            return;
        }

        foreach ($tableDiff->getAddedIndexes() as $index) {
            $this->createIndex($tableDiff, $fromTableName, $oldTable, $index, $indexColumns, $changeSetElt);
        }
    }

    protected function createIndex(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        Table $oldTable,
        Index $index,
        IndexColumns $indexColumns,
        DOMElement $changeSetElt
    ): void {
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

            if ($oldTable->hasColumn($columnName)) {
                $column = $oldTable->getColumn($columnName);
            } else {
                if (VersionHelper::isDBALVersion4()) {
                    /**
                     * @psalm-suppress InvalidArrayOffset
                     */
                    $column = $tableDiff->getAddedColumns()[$columnName];
                } else {
                    /**
                     * @psalm-suppress InternalProperty
                     * @psalm-suppress InaccessibleProperty
                     */
                    $column = $tableDiff->addedColumns[$columnName];
                }
            }

            assert($column instanceof Column);

            $this->fillColumnAttributes($columnElt, $column, $indexColumns);

            $createIndexElt->appendChild($columnElt);
        }

        $changeSetElt->appendChild($createIndexElt);
    }

    protected function alterTableAddedForeignKeys(TableDiff $tableDiff, DOMElement $changeSetElt): void
    {
        $oldTable = $tableDiff->getOldTable();
        /**
         * @psalm-suppress TypeDoesNotContainNull
         */
        if ($oldTable === null) {
            return;
        }

        foreach ($tableDiff->getAddedForeignKeys() as $foreignKey) {
            $addForeignKeyConstraintElt = $this->document->createElement('addForeignKeyConstraint');

            $this->fillForeignKeyAttributes($addForeignKeyConstraintElt, $foreignKey, $oldTable);

            $changeSetElt->appendChild($addForeignKeyConstraintElt);
        }
    }

    protected function alterTableRenamedColumns(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        if (VersionHelper::isDBALVersion4()) {
            /**
             * @psalm-suppress UndefinedMethod
             */
            $changedColumns = $tableDiff->getChangedColumns();
        } else {
            /**
             * @psalm-suppress InternalProperty
             * @psalm-suppress UndefinedPropertyFetch
             */
            $changedColumns = $tableDiff->renamedColumns;
        }

        foreach ($changedColumns as $oldName => $columnDiffOrColumn) {
            $renameColumnElt = $this->document->createElement('renameColumn');

            // DBAL >= 4
            if ($columnDiffOrColumn instanceof ColumnDiff) {
                $columnName = QualifiedName::fromAsset($columnDiffOrColumn->getNewColumn());
            } else {
                $columnName = QualifiedName::fromAsset($columnDiffOrColumn);
            }

            $schemaName = $fromTableName->getNamespaceName();
            if ($schemaName) {
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
        $oldTable = $tableDiff->getOldTable();
        /**
         * @psalm-suppress TypeDoesNotContainNull
         */
        if ($oldTable === null) {
            return;
        }

        foreach ($tableDiff->getRenamedIndexes() as $oldName => $index) {
            $this->dropIndex($fromTableName, $oldName, $changeSetElt);

            $this->createIndex(
                $tableDiff,
                $fromTableName,
                $oldTable,
                $index,
                new IndexColumns($oldTable),
                $changeSetElt
            );
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
            /**
             * @psalm-suppress InternalMethod
             */
            $this->dropIndex($fromTableName, $index->getName(), $changeSetElt);
        }
    }

    protected function dropIndex(
        QualifiedName $fromTableName,
        string $indexName,
        DOMElement $changeSetElt
    ): void {
        $dropIndexElt = $this->document->createElement('dropIndex');

        if ($schemaName = $fromTableName->getNamespaceName()) {
            $dropIndexElt->setAttribute('schemaName', $schemaName);
        }

        $dropIndexElt->setAttribute('tableName', $fromTableName->getName());
        $dropIndexElt->setAttribute('indexName', $indexName);

        $changeSetElt->appendChild($dropIndexElt);
    }

    protected function alterTableRemovedForeignKeys(
        TableDiff $tableDiff,
        QualifiedName $fromTableName,
        DOMElement $changeSetElt
    ): void {
        foreach ($tableDiff->getDroppedForeignKeys() as $foreignKey) {
            if (! $foreignKey instanceof ForeignKeyConstraint) {
                continue;
            }

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
        $oldColumn = $columnDiff->getOldColumn();

        /**
         * @psalm-suppress TypeDoesNotContainNull
         */
        if ($oldColumn === null) {
            return;
        }

        $column        = $columnDiff->getNewColumn();
        $oldColunmName = QualifiedName::fromAsset($oldColumn);
        $columnName    = QualifiedName::fromAsset($column);

        /**
         * @psalm-suppress InternalMethod
         */
        if ($oldColumn->getName() !== $column->getName()) {
            $renameColumnElt = $this->document->createElement('renameColumn');

            if ($schemaName = $fromTableName->getNamespaceName()) {
                $renameColumnElt->setAttribute('schemaName', $schemaName);
            }
            $renameColumnElt->setAttribute('tableName', $fromTableName->getName());
            $renameColumnElt->setAttribute('oldColumnName', $oldColunmName->getName());
            $renameColumnElt->setAttribute('newColumnName', $columnName->getName());

            $changeSetElt->appendChild($renameColumnElt);
        }

        if (VersionHelper::isDBALVersion4()) {
            /**
             * @psalm-suppress UndefinedMethod
             */
            $properties = $columnDiff->countChangedProperties();

            $changedProperties = [];
            /**
             * @psalm-suppress UndefinedMethod
             */
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
        } else {
            /**
             * @psalm-suppress UndefinedPropertyFetch
             */
            $changedProperties = $columnDiff->changedProperties;
            $properties        = count($changedProperties);
        }

        if ($properties > 0) {
            $typeIndex = $columnDiff->hasTypeChanged();

            if ($typeIndex !== false) {
                $this->modifyDataType($columnDiff, $fromTableName, $column, $changeSetElt);
                $properties -= 1;
            }

            $defaultChanged = $columnDiff->hasDefaultChanged();
            if ($defaultChanged !== false) {
                $this->modifyDefaultValue($columnDiff, $fromTableName, $column, $changeSetElt);
                $properties -= 1;
            }

            $changedNotNull = $columnDiff->hasNotNullChanged();
            if ($changedNotNull !== false) {
                $this->modifyNotNullConstraint($columnDiff, $fromTableName, $column, $changeSetElt);
                $properties -= 1;
            }
        }

        if ($properties > 0) {
            /**
             * @psalm-suppress InternalMethod
             */
            $commentElt = $this->document->createComment(
                sprintf(
                    ' Some column property changes are not supported (column: %s for properties [%s]) ',
                    $oldColumn->getName(),
                    implode(', ', $changedProperties)
                )
            );
            $changeSetElt->appendChild($commentElt);
        }
    }

    protected function modifyDataType(
        ColumnDiff $columnDiff,
        QualifiedName $fromTableName,
        Column $column,
        DOMElement $changeSetElt
    ): void {
        $modifyDataTypeElt = $this->document->createElement('modifyDataType');

        if ($schemaName = $fromTableName->getNamespaceName()) {
            $modifyDataTypeElt->setAttribute('schemaName', $schemaName);
        }

        $modifyDataTypeElt->setAttribute('tableName', $fromTableName->getName());
        /**
          * @psalm-suppress InternalMethod
          */
        $modifyDataTypeElt->setAttribute('columnName', $column->getName());
        $modifyDataTypeElt->setAttribute(
            'newDataType',
            $this->getColumnType($columnDiff->getNewColumn())
        );

        $changeSetElt->appendChild($modifyDataTypeElt);
    }

    protected function modifyDefaultValue(
        ColumnDiff $columnDiff,
        QualifiedName $fromTableName,
        Column $column,
        DOMElement $changeSetElt
    ): void {
        $dropDefaultValueElt = $this->document->createElement('dropDefaultValue');

        if ($schemaName = $fromTableName->getNamespaceName()) {
            $dropDefaultValueElt->setAttribute('schemaName', $schemaName);
        }

        $dropDefaultValueElt->setAttribute('tableName', $fromTableName->getName());
        /**
         * @psalm-suppress InternalMethod
         */
        $dropDefaultValueElt->setAttribute('columnName', $column->getName());
        $dropDefaultValueElt->setAttribute(
            'columnDataType',
            $this->getColumnType($columnDiff->getNewColumn())
        );

        $changeSetElt->appendChild($dropDefaultValueElt);

        $addDefaultValueElt = $this->document->createElement('addDefaultValue');

        if ($schemaName = $fromTableName->getNamespaceName()) {
            $addDefaultValueElt->setAttribute('schemaName', $schemaName);
        }

        $addDefaultValueElt->setAttribute('tableName', $fromTableName->getName());
        /**
         * @psalm-suppress InternalMethod
         */
        $addDefaultValueElt->setAttribute('columnName', $column->getName());
        $addDefaultValueElt->setAttribute(
            'columnDataType',
            $this->getColumnType($columnDiff->getNewColumn())
        );

        $default   = $column->getDefault();
        $attribute = match (true) {
            is_bool($default) => 'defaultValueBoolean',
            is_numeric($default) => 'defaultValueNumeric',
            default => 'defaultValue',
        // phpcs:disable
        };
        // phpcs:enable

        $defaultValue = match (true) {
            is_bool($default) => $default ? 'true' : 'false',
            is_numeric($default) => (string) $default,
            default => (string) $default,
        // phpcs:disable
        };
        // phpcs:enable

        $addDefaultValueElt->setAttribute($attribute, $defaultValue);

        $changeSetElt->appendChild($addDefaultValueElt);
    }

    protected function modifyNotNullConstraint(
        ColumnDiff $columnDiff,
        QualifiedName $fromTableName,
        Column $column,
        DOMElement $changeSetElt
    ): void {
        $addNotNullConstraint = $this->document->createElement('addNotNullConstraint');

        if ($schemaName = $fromTableName->getNamespaceName()) {
            $addNotNullConstraint->setAttribute('schemaName', $schemaName);
        }

        $addNotNullConstraint->setAttribute('tableName', $fromTableName->getName());
        /**
         * @psalm-suppress InternalMethod
         */
        $addNotNullConstraint->setAttribute('columnName', $column->getName());
        $addNotNullConstraint->setAttribute(
            'columnDataType',
            $this->getColumnType($columnDiff->getNewColumn())
        );

        $changeSetElt->appendChild($addNotNullConstraint);
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
