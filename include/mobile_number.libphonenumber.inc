<?php

/**
 * @file
 * mobile_number.libphonenumber.inc.
 */

require __DIR__ . '/LibphonenumberClassLoader.php';

$loader = new LibphonenumberClassLoader();
$loader->set('libphonenumber', array(libraries_get_path('libphonenumber') . '/src'));
$loader->register(TRUE);

return $loader;
