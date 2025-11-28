<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as MySQLDriver;
use Override;

final class MySQLContext extends AbstractDBContext implements Context
{
    public function __construct(
        protected string $driver,
        private string $host,
        private int $port,
        private string $dbname,
        private string $user,
        private string $password,
        private string $charset,
        private string $serverVersion
    ) {
    }

    #[Given('Database driver pdo_mysql is available')]
    public function databaseDriverPdoMysqlIsAvailable(): void
    {
        $this->databaseDriverIsAvailable();
    }

    #[Given('Connection to MySQL database is established')]
    public function connectionToMysqlDatabaseIsEstablished(): void
    {
        $this->connectionToDatabaseIsEstablished();
    }

    #[When('Changelog for MySQL is executed')]
    public function changelogForMysqlIsExecuted(): void
    {
        $this->changelogIsExecuted();
    }

    #[Then('The Output XML for MySQL should be:')]
    public function theOutputForMysqlXmlShouldBe(PyStringNode $expected): void
    {
        $this->theOutputXmlShouldBe($expected);
    }

    #[When('DiffChangelog for MySQL is executed')]
    public function diffchangelogForMysqlIsExecuted(): void
    {
        $this->diffChangelogIsExecuted();
    }

    #[Override]
    protected function createDriver(): Driver
    {
        return new MySQLDriver();
    }

    #[Override]
    protected function getConnectionParameters(): array
    {
        return [
            'driver'        => $this->driver,
            'host'          => $this->host,
            'port'          => $this->port,
            'dbname'        => $this->dbname,
            'user'          => $this->user,
            'password'      => $this->password,
            'charset'       => $this->charset,
            'serverVersion' => $this->serverVersion,
        ];
    }
}
