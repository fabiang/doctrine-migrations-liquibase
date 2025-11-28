<?php

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

use Fabiang\Doctrine\Migrations\Liquibase\MySQLContext;
use Fabiang\Doctrine\Migrations\Liquibase\PostgreSQLContext;
use Fabiang\Doctrine\Migrations\Liquibase\SQLiteContext;

$defaultProfile = new Profile('default', []);
$defaultProfile->withSuite(
    (new Suite('default'))
        ->withPaths('%paths.base%/tests/features/')
        ->addContext(MySQLContext::class, [
            'driver'        => getenv('MYSQL_DRIVER'),
            'host'          => getenv('MYSQL_HOST'),
            'port'          => intval(getenv('MYSQL_PORT')),
            'dbname'        => getenv('MYSQL_DATABASE'),
            'user'          => getenv('MYSQL_USER'),
            'password'      => getenv('MYSQL_PASSWORD'),
            'charset'       => getenv('MYSQL_CHARSET'),
            'serverVersion' => getenv('MYSQL_SERVER_VERSION'), 
        ])
        ->addContext(PostgreSQLContext::class, [
            'driver'   => getenv('POSTGRES_DRIVER'),
            'host'     => getenv('POSTGRES_HOST'),
            'port'     => getenv('POSTGRES_PORT'),
            'dbname'   => getenv('POSTGRES_DB'),
            'user'     => getenv('POSTGRES_USER'),
            'password' => getenv('POSTGRES_PASSWORD'),
        ])
        ->addContext(SQLiteContext::class, [
            'driver' => getenv('POSTGRES_DRIVER'),
            'memory' => true,
        ])
    )
;

return (new Config())
    ->withProfile($defaultProfile)
;
