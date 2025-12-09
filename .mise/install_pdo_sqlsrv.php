<?php

$binPath = dirname(PHP_BINARY);

$pecl = $binPath . '/pecl';
if (PHP_OS_FAMILY === 'Windows') {
    $pecl .= '.exe';
}

if (!file_exists($pecl) || !is_executable($pecl)) {
    return;
}

$loaded       = php_ini_scanned_files();
$extraINIPath = $loaded ? dirname(explode(PHP_EOL, trim($loaded))[0]) : '';

if (!extension_loaded('pdo_sqlsrv')) {
    echo "Install pdo_sqlsrv…";

    passthru($pecl . ' install pdo_sqlsrv');

    $xdebugINI = $extraINIPath . '/pdo_sqlsrv.ini';

    file_put_contents($xdebugINI, 'extension=' . PHP_EXTENSION_DIR . '/pdo_sqlsrv.so');

    echo " done\n";
}
