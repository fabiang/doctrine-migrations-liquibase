# doctrine-migrations-liquibase
Generate Liquibase ChangeLog from Doctrine Entities.

[![Unit Tests](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/unit.yml/badge.svg)](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/unit.yml)
[![Integration Tests](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/integration.yml/badge.svg)](https://github.com/fabiang/doctrine-migrations-liquibase/actions/workflows/integration.yml)
[![Latest Stable Version](http://poser.pugx.org/fabiang/doctrine-migrations-liquibase/v)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase) [![Total Downloads](http://poser.pugx.org/fabiang/doctrine-migrations-liquibase/downloads)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase) [![Latest Unstable Version](http://poser.pugx.org/fabiang/doctrine-migrations-liquibase/v/unstable)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase) [![License](http://poser.pugx.org/fabiang/doctrine-migrations-liquibase/license)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase) [![PHP Version Require](http://poser.pugx.org/fabiang/doctrine-migrations-liquibase/require/php)](https://packagist.org/packages/fabiang/doctrine-migrations-liquibase)

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
