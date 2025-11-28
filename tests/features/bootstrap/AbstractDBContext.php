<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Fabiang\Doctrine\Migrations\Liquibase\Output\LiquibaseOutputOptions;
use Webmozart\Assert\Assert;

use function dirname;
use function extension_loaded;
use function implode;
use function ltrim;
use function sprintf;
use function sys_get_temp_dir;

abstract class AbstractDBContext implements Context
{
    protected ?EntityManager $em = null;
    protected string $output     = '';
    protected string $driver;

    protected function databaseDriverIsAvailable(): void
    {
        Assert::notEmpty($this->driver, 'Not database driver defined');
        Assert::true(extension_loaded($this->driver), sprintf('Driver "%s" is not available', $this->driver));
    }

    protected function connectionToDatabaseIsEstablished(): void
    {
        $config = new Configuration();

        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Fabiang\Doctrine\Migrations\Liquibase\Entity');

        $path       = implode('/', [dirname(__FILE__), '..', 'Entity']);
        $driverImpl = new AttributeDriver([$path]);

        $config->setMetadataDriverImpl($driverImpl);

        $params = $this->getConnectionParameters();

        $conn     = new Connection($params, $this->createDriver());
        $this->em = new EntityManager($conn, $config);

        Assert::notEmpty($conn->getServerVersion(), 'Could not connect to database');
        Assert::true($conn->isConnected(), 'Database is not connected');
    }

    protected function changelogIsExecuted(): void
    {
        $schemaTool   = new LiquibaseSchemaTool($this->em);
        $this->output = $schemaTool->changeLog($this->options())->saveXML();
    }

    protected function diffChangelogIsExecuted(): void
    {
        $schemaTool   = new LiquibaseSchemaTool($this->em);
        $this->output = $schemaTool->diffChangeLog($this->options())->saveXML();
    }

    protected function theOutputXmlShouldBe(PyStringNode $expected): void
    {
        Assert::same($this->output, ltrim((string) $expected));
    }

    abstract protected function createDriver(): Driver;

    abstract protected function getConnectionParameters(): array;

    /**
     * @internal
     */
    #[BeforeScenario]
    public function clearOutput(): void
    {
        $this->output = '';
    }

    /**
     * @internal
     */
    #[AfterScenario]
    public function close(): void
    {
        if ($this->em) {
            $this->em->close();
        }

        $this->em = null;
    }

    protected function options(): LiquibaseOutputOptions
    {
        $options = new LiquibaseOutputOptions();
        $options->setChangeSetUniqueId(false);
        return $options;
    }
}
