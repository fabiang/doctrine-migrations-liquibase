<?php

declare(strict_types=1);

namespace Tests\Fabiang\Doctrine\Migrations\Liquibase;

use Doctrine\ORM\ORMException;
use Fabiang\Doctrine\Migrations\Liquibase\Output\LiquibaseOutputOptions;

class SqliteTest extends Database\AbstractDatabaseTestCase
{
    protected function getConnectionParameters(): array
    {
        return [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
    }

    protected function getEntitiesPath(): string
    {
        return 'Entity';
    }

    /**
     * @throws ORMException
     */
    public function testCreateWithDefaultOptions(): void
    {
        $options = new LiquibaseOutputOptions();
        $options->setChangeSetUniqueId(false);
        $output = $this->changeLog($options);

        $expected = <<<'EOT'
<?xml version="1.0"?>
<databaseChangeLog>
  <changeSet author="doctrine-migrations-liquibase" id="create-schema-testdb">
    <sql>CREATE SCHEMA `testdb`</sql>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-Bar">
    <createTable schemaName="testdb" tableName="bar">
      <column name="id" type="varchar(255)">
        <constraints primaryKey="true" nullable="false"/>
      </column>
    </createTable>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-Foo">
    <createTable schemaName="testdb" tableName="foo">
      <column name="id" type="int">
        <constraints primaryKey="true" nullable="false"/>
      </column>
    </createTable>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-IndexColumns">
    <createTable schemaName="testdb" tableName="indexcolumns">
      <column name="id" type="int">
        <constraints primaryKey="true" nullable="false"/>
      </column>
      <column name="date" type="date"/>
      <column name="libelle" type="varchar(255)"/>
      <column name="commentaire" type="varchar(500)">
        <constraints unique="true" uniqueConstraintName="UNIQ_78B576EA67F068BC"/>
      </column>
    </createTable>
    <createIndex indexName="IDX_78B576EAAA9E377A" schemaName="testdb" tableName="indexcolumns">
      <column name="date"/>
    </createIndex>
    <createIndex indexName="IDX_78B576EAA4D60759" schemaName="testdb" tableName="indexcolumns">
      <column name="libelle"/>
    </createIndex>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-ReservedKeywords">
    <createTable schemaName="testdb" tableName="reservedkeywords">
      <column name="id" type="int">
        <constraints nullable="false" primaryKey="true"/>
      </column>
      <column name="from" type="date">
        <constraints nullable="false"/>
      </column>
      <column name="to" type="datetime">
        <constraints nullable="false"/>
      </column>
    </createTable>
  </changeSet>
</databaseChangeLog>

EOT;

        self::assertXmlStringEqualsXmlString($expected, $output);
    }

    /**
     * @throws ORMException
     */
    public function testUpdateFromEmptyDatabaseWithDefaultOptions(): void
    {
        $options = new LiquibaseOutputOptions();
        $options->setChangeSetUniqueId(false);
        $output = $this->diffChangeLog($options);

        $expected = <<<'EOT'
<?xml version="1.0"?>
<databaseChangeLog>
  <changeSet author="doctrine-migrations-liquibase" id="create-schema-testdb">
    <sql>CREATE SCHEMA `testdb`</sql>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-Bar">
    <createTable schemaName="testdb" tableName="bar">
      <column name="id" type="varchar(255)">
        <constraints primaryKey="true" nullable="false"/>
      </column>
    </createTable>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-Foo">
    <createTable schemaName="testdb" tableName="foo">
      <column name="id" type="int">
        <constraints primaryKey="true" nullable="false"/>
      </column>
    </createTable>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-IndexColumns">
    <createTable schemaName="testdb" tableName="indexcolumns">
      <column name="id" type="int">
        <constraints primaryKey="true" nullable="false"/>
      </column>
      <column name="date" type="date"/>
      <column name="libelle" type="varchar(255)"/>
      <column name="commentaire" type="varchar(500)">
        <constraints unique="true" uniqueConstraintName="UNIQ_78B576EA67F068BC"/>
      </column>
    </createTable>
    <createIndex tableName="indexcolumns" schemaName="testdb" indexName="IDX_78B576EAAA9E377A">
      <column name="date"/>
    </createIndex>
    <createIndex tableName="indexcolumns" schemaName="testdb" indexName="IDX_78B576EAA4D60759">
      <column name="libelle"/>
    </createIndex>
  </changeSet>
  <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-ReservedKeywords">
    <createTable schemaName="testdb" tableName="reservedkeywords">
      <column name="id" type="int">
        <constraints nullable="false" primaryKey="true"/>
      </column>
      <column name="from" type="date">
        <constraints nullable="false"/>
      </column>
      <column name="to" type="datetime">
        <constraints nullable="false"/>
      </column>
    </createTable>
  </changeSet>
</databaseChangeLog>

EOT;

        self::assertXmlStringEqualsXmlString($expected, $output);
    }
}
