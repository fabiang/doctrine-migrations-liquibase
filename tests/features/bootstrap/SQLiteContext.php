<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use Override;

final class SQLiteContext extends AbstractDBContext implements Context
{
    public function __construct(
        protected string $driver,
        private bool $memory
    ) {
    }

    #[Given('Database driver pdo_sqlite is available')]
    public function databaseDriverPdoPgsqlIsAvailable(): void
    {
        $this->databaseDriverIsAvailable();
    }

    #[Given('Connection to SQLite database is established')]
    public function connectionToPostgresqlDatabaseIsEstablished(): void
    {
        $this->connectionToDatabaseIsEstablished();
    }

    #[When('Changelog for SQLite is executed')]
    public function changelogForPostgressqlIsExecuted(): void
    {
        $this->changelogIsExecuted();
    }

    #[Then('The Output XML for SQLite should be:')]
    public function theOutputForPostgressqlXmlShouldBe(PyStringNode $expected): void
    {
        $this->theOutputXmlShouldBe($expected);
    }

    #[When('DiffChangelog for SQLite is executed')]
    public function diffchangelogForPostgressqlIsExecuted(): void
    {
        $this->diffChangelogIsExecuted();
    }

    #[Override]
    protected function createDriver(): Driver
    {
        return new SQLiteDriver();
    }

    #[Override]
    protected function getConnectionParameters(): array
    {
        return [
            'driver' => $this->driver,
            'memory' => $this->memory,
        ];
    }
}
