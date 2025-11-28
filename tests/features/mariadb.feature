@mariadb
Feature: MariaDB migrations

  Background:
    Given Database driver pdo_mysql for MariaDB is available
    And Connection to MariaDB database is established

  Scenario: Create table with default options
    When Changelog for MariaDB is executed
    Then The Output XML for MariaDB should be:
    """
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
        <createIndex schemaName="testdb" tableName="indexcolumns" indexName="IDX_78B576EAAA9E377A">
          <column name="date"/>
        </createIndex>
        <createIndex schemaName="testdb" tableName="indexcolumns" indexName="IDX_78B576EAA4D60759">
          <column name="libelle"/>
        </createIndex>
      </changeSet>
      <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-ReservedKeywords">
        <createTable schemaName="testdb" tableName="reservedkeywords">
          <column name="id" type="int">
            <constraints primaryKey="true" nullable="false"/>
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

    """

  Scenario: Update empty database with default options
    When DiffChangelog for MariaDB is executed
    Then The Output XML for MariaDB should be:
    """
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
        <createIndex schemaName="testdb" tableName="indexcolumns" indexName="IDX_78B576EAAA9E377A">
          <column name="date"/>
        </createIndex>
        <createIndex schemaName="testdb" tableName="indexcolumns" indexName="IDX_78B576EAA4D60759">
          <column name="libelle"/>
        </createIndex>
      </changeSet>
      <changeSet author="doctrine-migrations-liquibase" id="create-table-testdb-ReservedKeywords">
        <createTable schemaName="testdb" tableName="reservedkeywords">
          <column name="id" type="int">
            <constraints primaryKey="true" nullable="false"/>
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

    """

