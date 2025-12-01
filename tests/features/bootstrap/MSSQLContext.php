<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver as SqlSrvDriver;
use Override;

final class MSSQLContext extends AbstractDBContext implements Context
{
    protected array $ignoreTables = [
        'MSreplication_options',
        'spt_fallback_db',
        'spt_fallback_dev',
        'spt_fallback_usg',
        'spt_monitor',
    ];

    public function __construct(
        protected string $driver,
        private string $host,
        private int $port,
        private string $dbname,
        private string $user,
        private string $password
    ) {
    }

    #[Given('Database driver pdo_sqlsrv is available')]
    public function databaseDriverPdoMysqlIsAvailable(): void
    {
        $this->databaseDriverIsAvailable();
    }

    #[Given('Connection to MSSQL database is established')]
    public function connectionToMysqlDatabaseIsEstablished(): void
    {
        $this->connectionToDatabaseIsEstablished();
    }

    #[When('Changelog for MSSQL is executed')]
    public function changelogForMysqlIsExecuted(): void
    {
        $this->changelogIsExecuted();
    }

    #[Then('The Output XML for MSSQL should be:')]
    public function theOutputForMysqlXmlShouldBe(PyStringNode $expected): void
    {
        $this->theOutputXmlShouldBe($expected);
    }

    #[When('DiffChangelog for MSSQL is executed')]
    public function diffchangelogForMysqlIsExecuted(): void
    {
        $this->diffChangelogIsExecuted();
    }

    #[Override]
    protected function createDriver(): Driver
    {
        return new SqlSrvDriver();
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
            'driverOptions' => [
                'TrustServerCertificate' => 1,
            ],
        ];
    }
}
