<?php

declare(strict_types=1);

namespace Tests\Fabiang\Doctrine\Migrations\Liquibase\Database;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Fabiang\Doctrine\Migrations\Liquibase\LiquibaseOutputOptions;
use Fabiang\Doctrine\Migrations\Liquibase\LiquibaseSchemaTool;
use PHPUnit\Framework\TestCase;

use function dirname;
use function extension_loaded;
use function join;
use function sprintf;
use function sys_get_temp_dir;

abstract class AbstractDatabaseTest extends TestCase
{
    /** @var EntityManager */
    protected $em;

    /** @var array */
    protected $databaseState = [];

    abstract protected function getConnectionParameters(): array;

    abstract protected function getEntitiesPath(): string;

    /**
     * Setup database;
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

        //$config->setQueryCacheImpl(new ArrayCache());
        //$config->setMetadataCacheImpl(new ArrayCache());

        $driver = $config->newDefaultAnnotationDriver([join('/', [dirname(__FILE__), '..', $this->getEntitiesPath()])], false);
        $config->setMetadataDriverImpl($driver);

        $params = $this->getConnectionParameters();

        if (false === $this->driverIsAvailable()) {
            return;
        }

        $this->em = EntityManager::create($params, $config);
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
