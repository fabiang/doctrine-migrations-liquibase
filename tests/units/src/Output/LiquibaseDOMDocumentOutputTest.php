<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase\Output;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types as DoctrineType;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use Fabiang\Doctrine\Migrations\Liquibase\ColumnDiffTrait;
use Fabiang\Doctrine\Migrations\Liquibase\Helper\VersionHelper;
use Fabiang\Doctrine\Migrations\Liquibase\TableDiffTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Xpath\AssertTrait as XPathAssert;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

#[CoversClass(LiquibaseDOMDocumentOutput::class)]
final class LiquibaseDOMDocumentOutputTest extends TestCase
{
    use ColumnDiffTrait;
    use ProphecyTrait;
    use TableDiffTrait;
    use XPathAssert;

    private LiquibaseDOMDocumentOutput $output;
    private LiquibaseOutputOptions $options;
    private DOMDocument $document;
    private ObjectProphecy $em;
    private ObjectProphecy $connection;
    private ObjectProphecy $platform;

    protected function setUp(): void
    {
        $this->options = new LiquibaseOutputOptions();
        $this->options->setChangeSetUniqueId(false);
        $this->options->setChangeSetAuthor('phpunit');

        $this->document = new DOMDocument();

        $this->output = new LiquibaseDOMDocumentOutput($this->options, $this->document);

        $this->platform = $this->prophesize(AbstractPlatform::class);
        $this->platform->getStringTypeDeclarationSQL(Argument::any())->willReturn('test');

        $this->connection = $this->prophesize(Connection::class);
        $this->connection->getDatabasePlatform()
            ->willReturn($this->platform->reveal());

        $this->em = $this->prophesize(EntityManagerInterface::class);
        $this->em->getConnection()->willReturn($this->connection->reveal());

        $this->output->started($this->em->reveal());
    }

    public function testDefaultConstructorOptions(): void
    {
        $output = new LiquibaseDOMDocumentOutput();
        $this->assertInstanceOf(LiquibaseOutputOptions::class, $output->getOptions());
        $this->assertInstanceOf(DOMDocument::class, $output->getDocument());
        $this->assertInstanceOf(DOMDocument::class, $output->getResult());
    }

    public function testCreateSchema(): void
    {
        $this->platform->getCreateSchemaSQL('myns')
            ->willReturn('CREATE MYSCHEMA myns');

        $this->output->createSchema('myns');
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog/changeSet[@id="create-schema-myns"][@author="phpunit"]/sql',
            $this->output->getDocument()
        );
        $this->assertXpathEquals(
            'CREATE MYSCHEMA myns',
            '/databaseChangeLog/changeSet[@id="create-schema-myns"][@author="phpunit"]/sql/text()',
            $this->output->getDocument()
        );
    }

    public function testCreateSchemaWithExceptionThrown(): void
    {
        $this->platform->getCreateSchemaSQL('myns')
            ->willThrow($this->prophesize(DBALException::class)->reveal());

        $this->output->createSchema('myns');
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog/changeSet[@id="create-schema-myns"][@author="phpunit"]/sql',
            $this->output->getDocument()
        );
        $this->assertXpathEquals(
            'CREATE SCHEMA `myns`',
            '/databaseChangeLog/changeSet[@id="create-schema-myns"][@author="phpunit"]/sql/text()',
            $this->output->getDocument()
        );
    }

    public function testDropForeignKeyConstraint(): void
    {
        $orphanedForeignKey = $this->prophesize(ForeignKeyConstraint::class);
        $orphanedForeignKey->getName()->willReturn('namespace.test');
        $orphanedForeignKey->getNamespaceName()->willReturn('namespace');
        $orphanedForeignKey->getShortestName('namespace')->willReturn('test');

        $localTable = $this->prophesize(Table::class);
        $localTable->getName()->willReturn('namespace.test2');
        $localTable->getNamespaceName()->willReturn('namespace');
        $localTable->getShortestName('namespace')->willReturn('test2');

        $this->output->dropForeignKey($orphanedForeignKey->reveal(), $localTable->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="drop-foreign-key-namespace-test"][@author="phpunit"]'
                . '/dropForeignKeyConstraint'
                . '[@baseTableSchemaName="namespace"][@baseTableName="test2"][@constraintName="test"]',
            $this->output->getDocument()
        );
    }

    public function testAlterSequence(): void
    {
        $sequence = $this->prophesize(Sequence::class);
        $sequence->getName()->willReturn('myseq');

        $this->output->alterSequence($sequence->reveal());
        $this->output->terminated();

        $this->assertStringContainsString(
            '<!-- alterSequence is not supported (sequence: myseq)-->',
            $this->output->getDocument()->saveXML()
        );
    }

    public function testDropSequence(): void
    {
        $sequence = $this->prophesize(Sequence::class);
        $sequence->getName()->willReturn('myseq');
        $sequence->getShortestName('namespace')->willReturn('myseq');
        $sequence->getNamespaceName()->willReturn('namespace');

        $this->output->dropSequence($sequence->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="drop-sequence-myseq"][@author="phpunit"]'
                . '/dropSequence[@schemaName="namespace"][@sequenceName="myseq"]',
            $this->output->getDocument()
        );
    }

    public function testCreateSequence(): void
    {
        $sequence = $this->prophesize(Sequence::class);
        $sequence->getName()->willReturn('myseq');
        $sequence->getShortestName('namespace')->willReturn('myseq');
        $sequence->getNamespaceName()->willReturn('namespace');
        $sequence->getInitialValue()->willReturn(1);

        $this->output->createSequence($sequence->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="create-sequence-myseq"][@author="phpunit"]'
                . '/createSequence[@schemaName="namespace"][@sequenceName="myseq"][@startValue="1"]',
            $this->output->getDocument()
        );
    }

    public function testCreateTable(): void
    {
        $table = $this->getTestTable();

        $column1 = new Column('column1', new DoctrineType\StringType());
        $column1->setComment('mycomment');
        $column1->setDefault('somedefault');
        $column1->setNotnull(true);
        $column1->setLength(10);

        $column2 = new Column('column2', new DoctrineType\IntegerType());
        $column3 = new Column('column3', new DoctrineType\FloatType());

        $column4 = new Column('column4', new DoctrineType\IntegerType());
        $column4->setColumnDefinition('bigint');

        $table->getColumns()->willReturn([$column1, $column2, $column3, $column4]);
        $table->getIndexes()
            ->shouldBeCalled()
            ->willReturn([]);

        $primaryIndex1 = new Index('primary1', ['column1', 'column3'], false, true);

        $uniqueIndex1 = new Index('unique1', ['column2'], true, false);

        $table->getIndexes()
            ->shouldBeCalled()
            ->willReturn([
                $primaryIndex1,
                $uniqueIndex1,
            ]);

        $this->output->createTable($table->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="create-table-mytable"][@author="phpunit"]'
                . '/createTable[@tableName="mytable"][@schemaName="namespace"]',
            $this->output->getDocument()
        );

        $this->assertXpathCount(
            4,
            '/databaseChangeLog/changeSet/createTable/column',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createTable/column[1]'
                . '[@name="column1"]'
                . '[@type="varchar(10)"]'
                . '[@remarks="mycomment"]'
                . '[@defaultValue="somedefault"]',
            $this->output->getDocument()
        );
        $this->assertXpathMatch(
            '//createTable/column[1]/constraints[@nullable="false"][@primaryKey="true"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createTable/column[2]'
                . '[@name="column2"]'
                . '[@type="int"]',
            $this->output->getDocument()
        );
        $this->assertXpathMatch(
            '//createTable/column[2]/constraints[@unique="true"][@uniqueConstraintName="unique1"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createTable/column[3]'
                . '[@name="column3"]'
                . '[@type="float"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createTable/column[4]'
                . '[@name="column4"]'
                . '[@type="bigint"]',
            $this->output->getDocument()
        );
    }

    public function testCreateTableWithPlatFormTypes(): void
    {
        $table = $this->getTestTable();

        $columnType1 = new DoctrineType\StringType();

        $column1 = new Column('column1', $columnType1);
        $column1->setLength(10);

        $table->getColumns()->willReturn([$column1]);
        $table->getIndexes()
            ->shouldBeCalled()
            ->willReturn([]);

        $this->options->setUsePlatformTypes(true);
        $this->output->createTable($table->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '//createTable/column[1]'
                . '[@name="column1"]'
                . '[@type="test(10)"]',
            $this->output->getDocument()
        );
    }

    public function testCreateTableWithIndexes(): void
    {
        $table = $this->getTestTable();

        $otherIndex1 = new Index('other1', ['test1'], false, false);
        $otherIndex2 = new Index('other2', ['test2', 'test3'], true, false);

        $table->getColumns()->willReturn([]);
        $table->getIndexes()
            ->willReturn([$otherIndex1, $otherIndex2]);

        $this->output->createTable($table->reveal());
        $this->output->terminated();

        $this->assertXpathCount(
            2,
            '/databaseChangeLog/changeSet/createIndex',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createIndex[1][@schemaName="namespace"][@tableName="mytable"][@indexName="other1"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createIndex[1]/column[@name="test1"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createIndex[2][@schemaName="namespace"][@tableName="mytable"][@indexName="other2"][@unique="true"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '//createIndex[2]/column[@name="test2"]',
            $this->output->getDocument()
        );
        $this->assertXpathMatch(
            '//createIndex[2]/column[@name="test3"]',
            $this->output->getDocument()
        );
    }

    public function testCreateForeignKey(): void
    {
        $foreignKey = $this->prophesize(ForeignKeyConstraint::class);
        $foreignKey->getName()->willReturn('namespace.test');
        $foreignKey->getNamespaceName()->willReturn('namespace');
        $foreignKey->getShortestName('namespace')->willReturn('test');

        if (VersionHelper::isDBALVersion4()) {
            $foreignKey->getLocalColumns()->willReturn([
                UnqualifiedName::unquoted('test1'),
                UnqualifiedName::unquoted('test2'),
            ]);
            $foreignKey->getReferencingColumnNames()->willReturn([
                UnqualifiedName::unquoted('test1'),
                UnqualifiedName::unquoted('test2'),
            ]);
            $foreignKey->getForeignColumns()->willReturn([
                UnqualifiedName::unquoted('test3'),
                UnqualifiedName::unquoted('test4'),
            ]);
            $foreignKey->getReferencedColumnNames()->willReturn([
                UnqualifiedName::unquoted('test3'),
                UnqualifiedName::unquoted('test4'),
            ]);
        } else {
            $foreignKey->getLocalColumns()->willReturn(['test1', 'test2']);
            $foreignKey->getForeignColumns()->willReturn(['test3', 'test4']);
        }

        $foreignKey->getForeignTableName()->willReturn('namespace.othertable');

        $table = $this->getTestTable();

        $this->output->createForeignKey($foreignKey->reveal(), $table->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="create-foreign-keys-mytable"][@author="phpunit"]'
                . '/addForeignKeyConstraint[@constraintName="namespace.test"]'
                . '[@baseTableSchemaName="namespace"]'
                . '[@baseTableName="mytable"]'
                . '[@baseColumnNames="test1,test2"]'
                . '[@referencedTableSchemaName="namespace"]'
                . '[@referencedTableName="othertable"]'
                . '[@referencedColumnNames="test3,test4"]',
            $this->output->getDocument()
        );
    }

    public function testDropTable(): void
    {
        $table = $this->getTestTable();

        $this->output->dropTable($table->reveal());
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="drop-table-mytable"][@author="phpunit"]'
                . '/dropTable[@schemaName="namespace"][@tableName="mytable"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableAddedColumns(): void
    {
        $table = $this->getTestTable();

        $columnType1 = new DoctrineType\StringType();
        $column1     = new Column('column1', $columnType1);
        $column1->setLength(10);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            addedColumns: [$column1]
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/addColumn[@schemaName="namespace"][@tableName="mytable"]'
                . '/column[@name="column1"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableAddedIndex(): void
    {
        $index1 = new Index('myindex1', ['column1', 'column2'], true);
        $index2 = new Index('myindex2', ['column3', 'column4'], true);

        $columnType1 = new DoctrineType\StringType();
        $column1     = new Column('column1', $columnType1);
        $column1->setLength(10);

        $columnType2 = new DoctrineType\IntegerType();
        $column2     = new Column('column2', $columnType2);

        $columnType3 = new DoctrineType\StringType();
        $column3     = new Column('column3', $columnType3);

        $columnType4 = new DoctrineType\StringType();
        $column4     = new Column('column4', $columnType4);

        $table = $this->getTestTable();
        $table->hasColumn('column1')->willReturn(false);
        $table->hasColumn('column2')->willReturn(false);
        $table->hasColumn('column3')->willReturn(true);
        $table->hasColumn('column4')->willReturn(true);

        $table->getColumn('column3')->willReturn($column3);
        $table->getColumn('column4')->willReturn($column4);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            addedColumns: ['column1' => $column1, 'column2' => $column2],
            addedIndexes: ['myindex1' => $index1, 'index2' => $index2]
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/createIndex[@schemaName="namespace"][@tableName="mytable"][@indexName="myindex1"]',
            $this->output->getDocument()
        );

        $this->assertXpathCount(2, '//createIndex[@indexName="myindex1"]/column', $this->output->getDocument());
        $this->assertXpathMatch(
            '//createIndex[@indexName="myindex1"]/column[1][@name="column1"]',
            $this->output->getDocument()
        );
        $this->assertXpathMatch(
            '//createIndex[@indexName="myindex1"]/column[2][@name="column2"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/createIndex[@schemaName="namespace"][@tableName="mytable"][@indexName="myindex2"]',
            $this->output->getDocument()
        );

        $this->assertXpathCount(
            2,
            '//createIndex[@indexName="myindex2"]/column',
            $this->output->getDocument()
        );
        $this->assertXpathMatch(
            '//createIndex[@indexName="myindex2"]/column[1][@name="column3"]',
            $this->output->getDocument()
        );
        $this->assertXpathMatch(
            '//createIndex[@indexName="myindex2"]/column[2][@name="column4"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableAddForeignKeys(): void
    {
        $table = $this->getTestTable();

        $foreignKey = $this->prophesize(ForeignKeyConstraint::class);
        $foreignKey->getName()->willReturn('namespace.test');
        $foreignKey->getNamespaceName()->willReturn('namespace');
        $foreignKey->getShortestName('namespace')->willReturn('test');

        if (VersionHelper::isDBALVersion4()) {
            $foreignKey->getLocalColumns()->willReturn([
                UnqualifiedName::unquoted('test1'),
                UnqualifiedName::unquoted('test2'),
            ]);
            $foreignKey->getReferencingColumnNames()->willReturn([
                UnqualifiedName::unquoted('test1'),
                UnqualifiedName::unquoted('test2'),
            ]);
            $foreignKey->getForeignColumns()->willReturn([
                UnqualifiedName::unquoted('test3'),
                UnqualifiedName::unquoted('test4'),
            ]);
            $foreignKey->getReferencedColumnNames()->willReturn([
                UnqualifiedName::unquoted('test3'),
                UnqualifiedName::unquoted('test4'),
            ]);
        } else {
            $foreignKey->getLocalColumns()->willReturn(['test1', 'test2']);
            $foreignKey->getForeignColumns()->willReturn(['test3', 'test4']);
        }

        $foreignKey->getForeignTableName()->willReturn('namespace.othertable');

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            addedForeignKeys: [$foreignKey->reveal()],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/addForeignKeyConstraint'
                . '[@baseTableSchemaName="namespace"]'
                . '[@baseTableName="mytable"]'
                . '[@baseColumnNames="test1,test2"]'
                . '[@referencedTableSchemaName="namespace"]'
                . '[@referencedTableName="othertable"]'
                . '[@referencedColumnNames="test3,test4"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableRenameColumns(): void
    {
        $table = $this->getTestTable();

        $columnType1 = new DoctrineType\StringType();
        $oldColumn   = new Column('oldcolumn', $columnType1);
        $newColumn   = new Column('newcolumn', $columnType1);
        $newColumn->setLength(10);

        $columnDiff = $this->columnDiff($oldColumn, $newColumn);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            changedColumns: ['oldcolumn' => $columnDiff],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/renameColumn[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@oldColumnName="oldcolumn"]'
                . '[@newColumnName="newcolumn"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableRenameIndexes(): void
    {
        $column1 = new Column(name: 'test1', type: new DoctrineType\StringType());
        $column2 = new Column(name: 'test2', type: new DoctrineType\StringType());

        $table = $this->getTestTable();
        $table->hasColumn('test1')->willReturn(true);
        $table->getColumn('test1')->shouldBeCalled()->willReturn($column1);

        $table->hasColumn('test2')->willReturn(true);
        $table->getColumn('test2')->shouldBeCalled()->willReturn($column2);

        $index1 = new Index(
            name: 'index1',
            columns: ['test1', 'test2'],
        );

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            renamedIndexes: ['oldindex' => $index1],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/dropIndex[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@indexName="oldindex"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/createIndex[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@indexName="index1"]'
                . '[@unique="false"]'
                . '/column[@name="test1"]'
                . '[@type="varchar"]'
                . '/constraints[@nullable="false"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/createIndex[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@indexName="index1"]'
                . '[@unique="false"]'
                . '/column[@name="test2"]'
                . '[@type="varchar"]'
                . '/constraints[@nullable="false"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableRemovedColumns(): void
    {
        $table = $this->getTestTable();

        $columnType1 = new DoctrineType\StringType();
        $column1     = new Column('removedcolumn', $columnType1);
        $column1->setLength(10);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            droppedColumns: ['removedcolumn' => $column1],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/dropColumn[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@columnName="removedcolumn"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableRemovedIndexes(): void
    {
        $table = $this->getTestTable();

        $index1 = new Index('removeindex', ['test1', 'test2']);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            droppedIndexes: ['removeindex' => $index1]
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/dropIndex[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@indexName="removeindex"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableRemovedForeignKeys(): void
    {
        $table = $this->getTestTable();

        $foreignKey = $this->prophesize(ForeignKeyConstraint::class);
        $foreignKey->getName()->willReturn('namespace.test');
        $foreignKey->getNamespaceName()->willReturn('namespace');
        $foreignKey->getShortestName('namespace')->willReturn('test');
        $foreignKey->getLocalColumns()->willReturn(['test1', 'test2']);
        $foreignKey->getForeignColumns()->willReturn(['test3', 'test4']);
        $foreignKey->getForeignTableName()->willReturn('namespace.othertable');

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            droppedForeignKeys: [$foreignKey->reveal()],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/dropForeignKeyConstraint'
                . '[@baseTableSchemaName="namespace"]'
                . '[@baseTableName="mytable"]'
                . '[@constraintName="test"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableRemovedForeignKeysWhereForeignKeyIsString(): void
    {
        $table = $this->getTestTable();

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            droppedForeignKeys: [new ForeignKeyConstraint([], '', [], 'test')],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/dropForeignKeyConstraint'
                . '[@baseTableSchemaName="namespace"]'
                . '[@baseTableName="mytable"]'
                . '[@constraintName="test"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableChangedColumnsRenamed(): void
    {
        $table = $this->getTestTable();

        $columnType1 = new DoctrineType\StringType();

        $oldColumn = new Column('oldname', $columnType1);
        $newColumn = new Column('changed', $columnType1);
        $newColumn->setLength(10);

        $columnDiff1 = $this->columnDiff($oldColumn, $newColumn);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            changedColumns: ['changed' => $columnDiff1],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/renameColumn[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@oldColumnName="oldname"]'
                . '[@newColumnName="changed"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableChangedColumnsChangedType(): void
    {
        $table = $this->getTestTable();

        $columnTypeText   = new DoctrineType\TextType();
        $columnTypeString = new DoctrineType\StringType();

        $oldColumn = new Column('notchangedname', $columnTypeText);
        $newColumn = new Column('notchangedname', $columnTypeString);
        $newColumn->setLength(10);

        $columnDiff1 = $this->columnDiff($oldColumn, $newColumn, ['type']);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            changedColumns: ['notchangedname' => $columnDiff1],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/modifyDataType[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@columnName="notchangedname"]'
                . '[@newDataType="varchar(10)"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableChangedColumnsOtherProperties(): void
    {
        $table = $this->getTestTable();

        $columnType1 = new DoctrineType\StringType();

        $oldColumn = new Column('notchangedname', $columnType1);
        $oldColumn->setPlatformOption('someotherproperty', 'foo');

        $newColumn = new Column('notchangedname', $columnType1);
        $newColumn->setPlatformOption('someotherproperty', 'bar');

        $columnDiff1 = $this->columnDiff($oldColumn, $newColumn, ['platformOption']);

        $tableDiff = $this->tableDiff(
            oldTable:  $table->reveal(),
            changedColumns: ['notchangedname' => $columnDiff1],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertStringContainsString(
            '<!-- Some column property changes are not supported '
                . '(column: notchangedname for properties [platformOption]) -->',
            $this->output->getDocument()->saveXML()
        );
    }

    public function testAlterTableChangedIndexes(): void
    {
        $column1 = new Column(name: 'test1', type: new DoctrineType\StringType());
        $column2 = new Column(name: 'test2', type: new DoctrineType\StringType());

        $table = $this->getTestTable();
        $table->hasColumn('test1')->willReturn(true);
        $table->getColumn('test1')->shouldBeCalled()->willReturn($column1);

        $table->hasColumn('test2')->willReturn(true);
        $table->getColumn('test2')->shouldBeCalled()->willReturn($column2);

        $index1 = new Index('changeindex', ['test1', 'test2']);

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            renamedIndexes: ['oldindex' => $index1],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/dropIndex[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@indexName="oldindex"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/createIndex[@schemaName="namespace"]'
                . '[@unique="false"]'
                . '[@indexName="changeindex"]'
                . '/column[@name="test1"]'
                . '[@type="varchar"]'
                . '/constraints[@nullable="false"]',
            $this->output->getDocument()
        );

        $this->assertXpathMatch(
            '/databaseChangeLog'
                . '/changeSet[@id="alter-table-mytable"][@author="phpunit"]'
                . '/createIndex[@schemaName="namespace"]'
                . '[@tableName="mytable"]'
                . '[@unique="false"]'
                . '[@indexName="changeindex"]'
                . '/column[@name="test2"]'
                . '[@type="varchar"]'
                . '/constraints[@nullable="false"]',
            $this->output->getDocument()
        );
    }

    public function testAlterTableChangedForeignKey(): void
    {
        $table = $this->getTestTable();

        $foreignKey = $this->prophesize(ForeignKeyConstraint::class);
        $foreignKey->getName()->willReturn('namespace.test');
        $foreignKey->getNamespaceName()->willReturn('namespace');
        $foreignKey->getShortestName('namespace')->willReturn('test');
        $foreignKey->getLocalColumns()->willReturn(['test1', 'test2']);
        $foreignKey->getForeignColumns()->willReturn(['test3', 'test4']);
        $foreignKey->getForeignTableName()->willReturn('namespace.othertable');

        $tableDiff = $this->tableDiff(
            oldTable: $table->reveal(),
            modifiedForeignKeys: [$foreignKey->reveal()],
        );

        $this->output->alterTable($tableDiff);
        $this->output->terminated();

        $this->assertStringContainsString(
            '<!-- foreign key changes are not supported (foreignKey: namespace.test)-->',
            $this->output->getDocument()->saveXML()
        );
    }

    public function testTerminated(): void
    {
        $this->output->terminated();
        $document = $this->output->getDocument();
        $this->assertXpathMatch('/databaseChangeLog', $document);
    }

    private function getTestTable(): ObjectProphecy
    {
        $table = $this->prophesize(Table::class);
        $table->getName()->willReturn('mytable');
        $table->getShortestName('namespace')->willReturn('mytable');
        $table->getNamespaceName()->willReturn('namespace');
        $table->getIndexes()->willReturn([]);
        return $table;
    }
}
