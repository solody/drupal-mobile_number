<?php

namespace Drupal\mobile_number\Tests;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * A test form used for the prepareCallback() tests.
 */
class PrepareCallbackTestForm implements FormInterface {

  /**
   *
   */
  public function getFormId() {
    return 'test_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {}

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
