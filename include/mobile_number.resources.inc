<?php

/**
 * @file
 * mobile_number.resources.inc.
 */

require_once drupal_get_path('module', 'mobile_number') . '/include/mobile_number.libphonenumber.inc';

function _mobile_number_request_code($number, $country = NULL) {

  if(!mobile_number_sms_sending_is_enabled()) {
    return services_error(t('SMS functionality not enabled.'), MobileNumber::VERIFY_SMS_FAILED);
  }

  try {
    $mobile_number = new MobileNumber($number, $country);
    if(!$mobile_number->checkFlood()) {
      return services_error(t('Too many attempts, try again later.'), MobileNumber::VERIFY_TOO_MANY_ATTEMPTS);
    }
    if($token = $mobile_number->sendVerification(MOBILE_NUMBER_DEFAULT_SMS_MESSAGE, $mobile_number->generateVerificationCode())) {
      return array('verification_token' => $token);
    }

    return services_error(t('Could not send verification code.'), MobileNumber::VERIFY_SMS_FAILED);
  } catch(Exception $e) {
    return services_error(t($e->getMessage()), $e->getCode());
  }
  
}