<?php

declare(strict_types=1);

namespace Tests\Fabiang\Doctrine\Migrations\Liquibase\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMException;
use Fabiang\Doctrine\Migrations\Liquibase\LiquibaseSchemaTool;
use Fabiang\Doctrine\Migrations\Liquibase\Output\LiquibaseOutputOptions;
use PHPUnit\Framework\TestCase;

use function dirname;
use function extension_loaded;
use function implode;
use function sprintf;
use function sys_get_temp_dir;

abstract class AbstractDatabaseTestCase extends TestCase
{
    protected EntityManager $em;
    protected array $databaseState = [];

    abstract protected function getConnectionParameters(): array;

    abstract protected function getEntitiesPath(): string;

    /**
     * Setup database
     */
    protected function setUpDatabase(): void
    {
    }

    /**
     * Teardown database
     */
    protected function tearDownDatabase(): void
    {
    }

    private function driverIsAvailable(): bool
    {
        $params = $this->getConnectionParameters();
        if (! empty($params['driver'])) {
            $driverName = $params['driver'];

            if (! extension_loaded($driverName)) {
                $this->markTestSkipped(sprintf(
                    'Driver "%s" is not available',
                    $driverName
                ));

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @throws ORMException
     */
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $config = new Configuration();

        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Fabiang\Doctrine\Migrations\Liquibase\Proxies');

        $path       = implode('/', [dirname(__FILE__), '..', $this->getEntitiesPath()]);
        $driverImpl = new AttributeDriver([$path]);

        $config->setMetadataDriverImpl($driverImpl);

        $params = $this->getConnectionParameters();

        if (false === $this->driverIsAvailable()) {
            return;
        }

        $driver = null;
        switch ($params['driver']) {
            case 'pdo_mysql':
                $driver = new PDO\MySQL\Driver();
                break;

            case 'pdo_pgsql':
                $driver = new PDO\PgSQL\Driver();
                break;

            case 'pdo_sqlite':
                $driver = new PDO\SQLite\Driver();
                break;

            default:
                $this->markTestSkipped('Unsupported driver');
                break;
        }

        $conn     = new Connection($params, $driver);
        $this->em = new EntityManager($conn, $config);
    }

    /**
     * @throws ORMException
     */
    protected function changeLog(?LiquibaseOutputOptions $options = null): string
    {
        $schemaTool = new LiquibaseSchemaTool($this->em);
        return $schemaTool->changeLog($options)->saveXML();
    }

    /**
     * @throws ORMException
     */
    protected function diffChangeLog(?LiquibaseOutputOptions $options = null): string
    {
        $schemaTool = new LiquibaseSchemaTool($this->em);
        return $schemaTool->diffChangeLog($options)->saveXML();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        $this->em->close();
    }
}
