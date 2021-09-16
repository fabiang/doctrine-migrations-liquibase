# doctrine-migrations-liquibase
Generate Liquibase ChangeLog from Doctrine Entities.

[![Unit Tests](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/unit.yml/badge.svg)](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/unit.yml)
[![Integration Tests](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/integration.yml/badge.svg)](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/integration.yml)
[![PHP](https://img.shields.io/packagist/php-v/fabiang/doctrine-migrations-liquibase.svg?style=flat-square)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase)
[![Version](https://img.shields.io/packagist/v/fabiang/doctrine-migrations-liquibase.svg?style=flat-square)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase)
[![Downloads](https://img.shields.io/packagist/dt/fabiang/doctrine-migrations-liquibase.svg?style=flat-square)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase)
[![License](https://img.shields.io/packagist/l/fabiang/doctrine-migrations-liquibase.svg?style=flat-square)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase)

## API Usage

```php
/** @var Doctrine\ORM\EntityManager $em */
$em = ...; // Retrieve Doctrine EntityManager as usual in your environment.

// Create a Liquibase SchemaTool with EntityManager
$schemaTool = new LiquibaseSchemaTool($this->em);

// Create a changelog that can be used on an empty database to build from scratch.
/** @var \DOMDocument $changeLog */
$changeLog = $schemaTool->changeLog()->getResult();
echo $changeLog->saveXML();

// Or create a diff changelog that can be used on current database to upgrade it.
/** @var \DOMDocument $diffChangeLog */
$diffChangeLog = $schemaTool->diffChangeLog()->getResult();
echo $diffChangeLog->saveXML();
```
