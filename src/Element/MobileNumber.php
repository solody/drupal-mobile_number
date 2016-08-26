<?php

namespace Drupal\mobile_number\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mobile_number\MobileNumberUtilInterface;
use Drupal\mobile_number\Exception\MobileNumberException;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;

/**
 * Provides a form input element for entering an email address.
 *
 * Properties:
 * - #default_value
 * - #allowed_countries
 * - #verify
 * - #tfa
 * - #message.
 *
 * Example usage:
 * @code
 * $form['mobile_number'] = array(
 *   '#type' => 'mpbile_number',
 *   '#title' => $this->t('Mobile Number'),
 * );
 *
 * @end
 *
 * @FormElement("mobile_number")
 */
class MobileNumber extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return array(
      '#input' => TRUE,
      '#process' => array(
        array($this, 'mobileNumberProcess'),
      ),
      '#element_validate' => array(
        array($this, 'mobileNumberValidate'),
      ),
      '#allowed_countries' => array(),
      '#verify' => MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_OPTIONAL,
      '#message' => MobileNumberUtilInterface::MOBILE_NUMBER_DEFAULT_SMS_MESSAGE,
      '#tfa' => NULL,
      '#token_data' => array(),
      '#tree' => TRUE,
    );
  }

  /**
   * Mobile number element value callback.
   *
   * @param array $element
   *   Element.
   * @param bool $input
   *   Input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Value.
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $result = array();
    if ($input) {
      $country = !empty($input['country-code']) ? $input['country-code'] : (count($element['#allowed_countries']) == 1 ? key($element['#allowed_countries']) : NULL);
      $mobile_number = $util->getMobileNumber($input['mobile'], $country);
      $result = array(
        'value' => $mobile_number ? $util->getCallableNumber($mobile_number) : '',
        'country' => $country,
        'local_number' => $input['mobile'],
        'tfa' => !empty($input['tfa']) ? 1 : 0,
        'verified' => 0,
        'verification_token' => !empty($input['verification_token']) ? $input['verification_token'] : NULL,
        'verification_code' => !empty($input['verification_code']) ? $input['verification_code'] : NULL,
      );
    }
    else {
      $result = !empty($element['#default_value']) ? $element['#default_value'] : array();
    }

    return $result;
  }

  /**
   * Mobile number element process callback.
   *
   * @param array $element
   *   Element.
   *
   * @return array
   *   Processed element.
   */
  public function mobileNumberProcess($element) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    // $element['#tree'] = TRUE;.
    $field_name = $element['#name'];
    $field_path = implode('][', $element['#parents']);
    $id = $element['#id'];
    $element += array(
      '#allowed_countries' => array(),
      '#default_value' => array(),
      '#verify' => MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE,
      '#message' => MobileNumberUtilInterface::MOBILE_NUMBER_DEFAULT_SMS_MESSAGE,
      '#tfa' => FALSE,
      '#token_data' => array(),
    );

    $element['#default_value'] += array(
      'value' => '',
      'country' => (count($element['#allowed_countries']) == 1) ? key($element['#allowed_countries']) : 'US',
      'local_number' => '',
      'verified' => 0,
      'tfa' => 0,
    );

    if ($default_mobile_number = $util->getMobileNumber($element['#default_value']['value'])) {
      $element['#default_value']['country'] = $util->getCountry($default_mobile_number);
    }

    $value = $element['#value'];

    $element['#prefix'] = "<div class=\"mobile-number-field form-item $field_name\" id=\"$id\">";
    $element['#suffix'] = '</div>';

    $element['label'] = array(
      '#type' => 'label',
      '#title' => $element['#title'],
      '#required' => $element['#required'],
      '#title_display' => $element['#title_display'],
    );

    $mobile_number = NULL;
    $verified = FALSE;
    $countries = $util->getCountryOptions($element['#allowed_countries'], TRUE);
    $countries += $util->getCountryOptions(array($element['#default_value']['country'] => $element['#default_value']['country']), TRUE);
    $default_country = $element['#default_value']['country'];

    if (!empty($value['value']) && $mobile_number = $util->getMobileNumber($value['value'])) {
      $verified = ($element['#verify'] != MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE) && $util->isVerified($mobile_number);
      $default_country = $util->getCountry($mobile_number);
      $country = $util->getCountry($mobile_number);
      $countries += $util->getCountryOptions(array($country => $country));
    }

    $verified = $verified || (!empty($element['#default_value']['verified']) && !empty($value['value']) && $value['value'] == $element['#default_value']['value']);

    $element['country-code'] = array(
      '#type' => 'select',
      '#options' => $countries,
      '#default_value' => $default_country,
      '#access' => !(count($countries) == 1),
      '#attributes' => array('class' => array('country')),
      '#title' => t('Country Code'),
      '#title_display' => 'invisible',
    );

    $element['mobile'] = array(
      '#type' => 'textfield',
      '#default_value' => $mobile_number ? $util->libUtil()
        ->format($mobile_number, 2) : NULL,
      '#title' => t('Phone number'),
      '#title_display' => 'invisible',
      '#suffix' => '<div class="form-item verified ' . ($verified ? 'show' : '') . '" title="' . t('Verified') . '"><span>' . t('Verified') . '</span></div>',
      '#attributes' => array(
        'class' => array('local-number'),
        'placeholder' => t('Phone number'),
      ),
    );

    $element['mobile']['#attached']['library'][] = 'mobile_number/element';

    if ($element['#verify'] != MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE) {
      $element['send_verification'] = array(
        '#type' => 'button',
        '#value' => t('Send verification code'),
        '#ajax' => array(
          'callback' => 'Drupal\mobile_number\Element\MobileNumber::verifyAjax',
          'wrapper' => $id,
          'effect' => 'fade',
        ),
        '#name' => implode('__', $element['#parents']) . '__send_verification',
        '#mobile_number_op' => 'mobile_number_send_verification',
        '#attributes' => array(
          'class' => array(
            !$verified ? 'show' : '',
            'send-button',
          ),
        ),
        '#submit' => array(),
      );

      $element['verification_code'] = array(
        '#type' => 'textfield',
        '#title' => t('Verification Code'),
        '#prefix' => '<div class="verification"><div class="description">' . t('A verification code has been sent to your mobile. Enter it here.') . '</div>',
      );

      $element['verify'] = array(
        '#type' => 'button',
        '#value' => t('Verfiy'),
        '#ajax' => array(
          'callback' => 'Drupal\mobile_number\Element\MobileNumber::verifyAjax',
          'wrapper' => $id,
          'effect' => 'fade',
        ),
        '#suffix' => '</div>',
        '#name' => implode('__', $element['#parents']) . '__verify',
        '#mobile_number_op' => 'mobile_number_verify',
        '#attributes' => array(
          'class' => array(
            'verify-button',
          ),
        ),
        '#submit' => array(),
      );

      if (!empty($element['#tfa'])) {
        $element['tfa'] = array(
          '#type' => 'checkbox',
          '#title' => t('Enable two-factor authentication'),
          '#default_value' => !empty($value['tfa']) ? 1 : 0,
          '#prefix' => '<div class="mobile-number-tfa">',
          '#suffix' => '</div>',
        );
      }
    }

    if (!empty($element['#description'])) {
      $element['description']['#markup'] = '<div class="description">' . $element['#description'] . '</div>';
    }
    return $element;
  }

  /**
   * Mobile number element validate callback.
   *
   * @param array $element
   *   Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Complete form.
   *
   * @return array
   *   Element.
   */
  public function mobileNumberValidate($element, FormStateInterface $form_state, &$complete_form) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    $op = $this->getOp($element, $form_state);
    $field_label = !empty($element['#field_title']) ? $element['#field_title'] : $element['#title'];
    $tree_parents = $element['#parents'];
    $input = NestedArray::getValue($form_state->getUserInput(), $tree_parents);
    $input = $input ? $input : array();
    $mobile_number = NULL;
    $countries = $util->getCountryOptions(array(), TRUE);
    if ($input) {
      $input += count($element['#allowed_countries']) == 1 ? array('country-code' => key($element['#allowed_countries'])) : array();
      try {
        $mobile_number = $util->testMobileNumber($input['mobile'], $input['country-code']);
      }
      catch (MobileNumberException $e) {
        switch ($e->getCode()) {
          case MobileNumberException::ERROR_NO_NUMBER:
            if ($op) {
              $form_state->setError($element['mobile'], t('Phone number in %field is required.', array(
                '%field' => $field_label,
              )));
            }
            break;

          case MobileNumberException::ERROR_INVALID_NUMBER:
          case MobileNumberException::ERROR_WRONG_TYPE:
            $form_state->setError($element['mobile'], t('The phone number %value provided for %field is not a valid mobile number for country %country.', array(
              '%value' => $input['mobile'],
              '%field' => $field_label,
              '%country' => $countries[$input['country-code']],
            )));

            break;

          case MobileNumberException::ERROR_WRONG_COUNTRY:
            $form_state->setError($element['mobile'], t('The country %value provided for %field does not match the mobile number prefix.', array(
              '%value' => $countries[$input['country-code']],
              '%field' => $field_label,
            )));
            break;
        }
      }
    }

    if (!empty($input['mobile'])) {
      $input += count($element['#allowed_countries']) == 1 ? array('country-code' => key($element['#allowed_countries'])) : array();
      if ($mobile_number = $util->getMobileNumber($input['mobile'], $input['country-code'])) {
        $country = $util->getCountry($mobile_number);
        if ($element['#allowed_countries'] && empty($element['#allowed_countries'][$country])) {
          $form_state->setError($element['country-code'], t('The country %value provided for %field is not an allowed country.', array(
            '%value' => $util->getCountryName($country),
            '%field' => $field_label,
          )));
        }
      }
    }

    return $element;
  }

  /**
   * Mobile number element ajax callback.
   *
   * @param array $complete_form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public static function verifyAjax($complete_form, FormStateInterface $form_state) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    $element = static::getTriggeringElementParent($complete_form, $form_state);
    $op = static::getOp($element, $form_state);

    drupal_get_messages();
    $errors = $form_state->getErrors();

    foreach ($errors as $path => $message) {
      if (strpos($path, implode('][', $element['#parents'])) === 0) {
        drupal_set_message($message, 'error');
      }
      else {
        unset($errors[$path]);
      }
    }

    $mobile_number = static::getMobileNumber($element);
    $verified = FALSE;
    $verify_prompt = FALSE;
    $flood_ok = TRUE;
    $is_admin = \Drupal::currentUser()
      ->hasPermission('bypass mobile number verification requirement');
    $token = !empty($element['#value']['verification_token']) ? $element['#value']['verification_token'] : FALSE;
    if ($mobile_number) {
      $verified = $util->isVerified($mobile_number);
      $flood_ok = $verified || $util->checkFlood($mobile_number);

      if ($flood_ok) {
        if (!$verified && $op == 'mobile_number_send_verification' && !$errors) {
          $token = $util->sendVerification($mobile_number, $element['#message'], $util->generateVerificationCode(), $element['#token_data']);
          if (!$token) {
            drupal_set_message(t('An error occurred while sending sms.'), 'error');
            $verify_prompt = FALSE;
          }
          else {
            $verify_prompt = TRUE;
          }
        }
        elseif (!$verified && $op == 'mobile_number_verify') {
          $verify_prompt = TRUE;
        }
      }
    }

    if (!empty($token)) {
      $element['verification_token'] = array(
        '#type' => 'hidden',
        '#value' => $token,
        '#name' => $element['#name'] . '[verification_token]',
      );
    }

    $element['messages'] = array('#type' => 'status_messages');
    unset($element['_weight']);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(NULL, $element));

    if ($verify_prompt) {
      $response->addCommand(new SettingsCommand(array('mobileNumberVerificationPrompt' => $element['#id'])));
    }

    if ($verified) {
      $response->addCommand(new SettingsCommand(array('mobileNumberVerified' => $element['#id'])));
    }

    return $response;
  }

  /**
   * Get mobile number form operation name based on the button pressed in the form.
   *
   * @param array $element
   *   Mobile number element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return null|string
   *   Operation name, or null if button does not belong to element.
   */
  public static function getOp(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $op = !empty($triggering_element['#mobile_number_op']) ? $triggering_element['#mobile_number_op'] : NULL;
    $button = !empty($triggering_element['#name']) ? $triggering_element['#name'] : NULL;

    if (!in_array($button, array(
      implode('__', $element['#parents']) . '__send_verification',
      implode('__', $element['#parents']) . '__verify',
    ))
    ) {
      $op = NULL;
    }

    return $op;
  }

  /**
   * Get mobile number form element based on currently pressed form button.
   *
   * @param array $complete_form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed
   *   Mobile number form element.
   */
  public static function getTriggeringElementParent(array $complete_form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    $element = NestedArray::getValue($complete_form, $parents);
    return $element;
  }

  /**
   * Get currently entered mobile number, given the form element.
   *
   * @param array $element
   *   Mobile number form element.
   *
   * @return \libphonenumber\PhoneNumber|NULL
   *   Mobile number. Null if empty, or not valid, mobile number.
   */
  public static function getMobileNumber($element) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    $values = !empty($element['#value']['local_number']) ? $element['#value'] : array();
    if ($values) {
      return $util->getMobileNumber($values['local_number'], $values['country']);
    }

    return NULL;
  }

}
