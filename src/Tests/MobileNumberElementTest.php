<?php

namespace Drupal\mobile_number\Tests;

use Drupal\Core\Form\FormState;
use Drupal\simpletest\WebTestBase;

/**
 * Mobile number form element functionality.
 *
 * @group mobile_number
 */
class MobileNumberElementTest extends WebTestBase {

  static $modules = array('mobile_number', 'sms');

  /**
   * Mobile number util.
   *
   * @var \Drupal\mobile_number\MobileNumberUtilInterface
   */
  public $util;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  public $flood;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->util = \Drupal::service('mobile_number.util');
    $this->flood = \Drupal::service('flood');
  }

  /**
   * Test number validation.
   */
  public function testNumberValidity() {

    $local_numbers = array(
      '0541234567' => 'Valid IL',
      '111' => 'Invalid IL',
      NULL => 'Empty',
    );

    $countries = array(
      'IL' => 'IL',
      'US' => 'US',
      NULL => 'Empty',
    );

    $allowed_countries = array(
      'IL' => array('IL' => 'IL'),
      'US' => array('US' => 'US'),
      'All' => array(),
    );

    $element = array(
      '#type' => 'mobile_number',
      '#title' => 'M',
      '#required' => TRUE,
      '#mobile_number' => array(),
    );

    $input = array(
      'country-code' => 'IL',
      'mobile' => '0541234567',
    );

    foreach ($allowed_countries as $type => $allowed) {
      $element['#mobile_number']['allowed_countries'] = $allowed;
      $errors = $this->submitFormElement($element, $input);
      $success = $type == 'US' ? 'failure' : 'success';
      $this->assertTrue($errors == ($type == 'US'), "IL mobile number with $type allowed: $success.", 'Number Validity');
    }

    unset($element['#mobile_number']['allowed_countries']);

    foreach ($local_numbers as $ln => $local_number) {
      foreach ($countries as $c => $country) {
        $input['country-code'] = $c;
        $input['mobile'] = $ln;

        $errors = $this->submitFormElement($element, $input);
        $valid = '0541234567' == $ln && 'IL' == $c;

        $success = $valid ? 'Success' : 'Failure';
        $this->assertTrue($valid == !$errors, "$country country, $local_number local number: $success.", 'Number Validity');
      }
    }
  }

  /**
   * Submit custom form.
   */
  public function submitFormElement($element, $input, $unset_verified_number = NULL) {
    if ($unset_verified_number) {
      $this->flood->clear('mobile_number_verification', $unset_verified_number);
      unset($_SESSION['mobile_number_verification'][$unset_verified_number]['verified']);
    }
    $form_id = $this->randomMachineName();
    $form_builder = \Drupal::formBuilder();

    $form = array();
    $form_state = new FormState();
    $form_state->clearErrors();
    $form['op'] = array('#type' => 'submit', '#value' => t('Submit'));
    $form['mobile_number'] = $element;
    $form_state->setUserInput([
      'mobile_number' => $input,
      'form_id' => $form_id,
    ]);
    $form_state->setFormObject(new PrepareCallbackTestForm());
    $form_state->setMethod('post');

    // The form token CSRF protection should not interfere with this test,
    // so we bypass it by marking this test form as programmed.
    $form_state->setProgrammed(TRUE);

    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    return $form_state->getErrors();
  }

}
