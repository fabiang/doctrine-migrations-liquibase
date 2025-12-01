<?php

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

use Fabiang\Doctrine\Migrations\Liquibase\MySQLContext;
use Fabiang\Doctrine\Migrations\Liquibase\PostgreSQLContext;
use Fabiang\Doctrine\Migrations\Liquibase\SQLiteContext;
use Fabiang\Doctrine\Migrations\Liquibase\MariaDBContext;
use Fabiang\Doctrine\Migrations\Liquibase\MSSQLContext;

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
        ->addContext(MariaDBContext::class, [
            'driver'        => getenv('MARIADB_DRIVER'),
            'host'          => getenv('MARIADB_HOST'),
            'port'          => intval(getenv('MARIADB_PORT')),
            'dbname'        => getenv('MARIADB_DATABASE'),
            'user'          => getenv('MARIADB_USER'),
            'password'      => getenv('MARIADB_PASSWORD'),
            'charset'       => getenv('MARIADB_CHARSET'),
            'serverVersion' => getenv('MARIADB_SERVER_VERSION'), 
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
        ->addContext(MSSQLContext::class, [
            'driver'   => getenv('MSSQL_DRIVER'),
            'host'     => getenv('MSSQL_HOST'),
            'port'     => intval(getenv('MSSQL_PORT')),
            'dbname'   => getenv('MSSQL_DATABASE'),
            'user'     => getenv('MSSQL_USER'),
            'password' => getenv('MSSQL_PASSWORD'),
        ])
    )
;

return (new Config())
    ->withProfile($defaultProfile)
;
