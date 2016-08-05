<?php

/**
 * @file
 * mobile_number.libphonenumber.inc.
 */

require __DIR__ . '/ClassLoader.php';

class MobileNumberAutoload {
  static function load() {
    $ClassLoader = new mobile_number\ClassLoader();
    $ClassLoader->set('libphonenumber', array(libraries_get_path('libphonenumber') . '/src'));
    $ClassLoader->register(TRUE);

    return $ClassLoader;
  }
}

return MobileNumberAutoload::load();
