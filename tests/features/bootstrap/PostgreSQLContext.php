<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgSQLDriver;
use Override;

final class PostgreSQLContext extends AbstractDBContext implements Context
{
    public function __construct(
        protected string $driver,
        private string $host,
        private int $port,
        private string $dbname,
        private string $user,
        private string $password
    ) {
    }

    #[Given('Database driver pdo_pgsql is available')]
    public function databaseDriverPdoPgsqlIsAvailable(): void
    {
        $this->databaseDriverIsAvailable();
    }

    #[Given('Connection to PostgreSQL database is established')]
    public function connectionToPostgresqlDatabaseIsEstablished(): void
    {
        $this->connectionToDatabaseIsEstablished();
    }

    #[When('Changelog for PostgresSQL is executed')]
    public function changelogForPostgressqlIsExecuted(): void
    {
        $this->changelogIsExecuted();
    }

    #[Then('The Output XML for PostgresSQL should be:')]
    public function theOutputForPostgressqlXmlShouldBe(PyStringNode $expected): void
    {
        $this->theOutputXmlShouldBe($expected);
    }

    #[When('DiffChangelog for PostgresSQL is executed')]
    public function diffchangelogForPostgressqlIsExecuted(): void
    {
        $this->diffChangelogIsExecuted();
    }

    #[Override]
    protected function createDriver(): Driver
    {
        return new PgSQLDriver();
    }

    #[Override]
    protected function getConnectionParameters(): array
    {
        return [
            'driver'   => $this->driver,
            'host'     => $this->host,
            'port'     => $this->port,
            'dbname'   => $this->dbname,
            'user'     => $this->user,
            'password' => $this->password,
        ];
    }
}
