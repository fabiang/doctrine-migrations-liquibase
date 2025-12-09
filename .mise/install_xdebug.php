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

if (!extension_loaded('xdebug')) {
    echo "Install Xdebug… ";

    passthru($pecl . ' install xdebug');

    $xdebugINI = $extraINIPath . '/xdebug.ini';

    file_put_contents($xdebugINI, 'zend_extension=' . PHP_EXTENSION_DIR . '/xdebug.so');

    echo " done\n";
}
