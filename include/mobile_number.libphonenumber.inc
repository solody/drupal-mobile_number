<?php

/**
 * @file
 * Mobile_number.libphonenumber.inc.
 */

use mobile_number\ClassLoader;

require __DIR__ . '/ClassLoader.php';
/**
 *
 */
class MobileNumberAutoload {

  /**
   *
   */
  static function load() {
    $ClassLoader = new ClassLoader();
    $ClassLoader->set('libphonenumber', array(libraries_get_path('libphonenumber') . '/src'));
    $ClassLoader->setPsr4('libphonenumber\\', array(libraries_get_path('libphonenumber') . '/src'));
    $ClassLoader->register(TRUE);

    return $ClassLoader;
  }

}

return MobileNumberAutoload::load();
