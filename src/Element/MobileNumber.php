<?php

namespace Drupal\mobile_number\Element;

use Drupal\Core\Entity\ContentEntityInterface;
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
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#process' => array(
        array($class, 'mobileNumberProcess'),
      ),
      '#element_validate' => array(
        array($class, 'mobileNumberValidate'),
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
      if ($mobile_number = $util->getMobileNumber($input['mobile'], $country)) {
        $result = array(
          'value' => $util->getCallableNumber($mobile_number),
          'country' => $util->getCountry($mobile_number),
          'local_number' => $util->getLocalNumber($mobile_number),
          'verified' => ($util->isVerified($mobile_number) || (!empty($element['#default_value']['verified']) && $util->getCallableNumber($mobile_number) == $element['#default_value']['value'])) ? 1 : 0,
          'tfa' => !empty($input['tfa']) ? 1 : 0,
        );
      }
      else {
        $result = array();
      }
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
  public static function mobileNumberProcess($element) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    // $element['#tree'] = TRUE;.
    $field_name = $element['#name'];
    $field_path = implode('][', $element['#parents']);
    $id = $element['#id'];
    $element += array(
      '#allowed_countries' => array(),
      '#default_value' => array(),
      '#verify' => $util->isSmsEnabled() ? MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_OPTIONAL : MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE,
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
    $countries += $util->getCountryOptions(array($element['#default_value']['country'] => $element['#default_value']['country']));
    $default_country = $element['#default_value']['country'];

    if (!empty($value['value']) && $mobile_number = $util->getMobileNumber($value['value'])) {
      $verified = $util->isVerified($mobile_number);
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

    if(!empty($element['#description'])) {
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
  public static function mobileNumberValidate($element, FormStateInterface $form_state, &$complete_form) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $errors = array();
    $triggering_element = $form_state->getTriggeringElement();
    $op = !empty($triggering_element['#mobile_number_op']) ? $triggering_element['#mobile_number_op'] : NULL;
    $button = !empty($triggering_element['#name']) ? $triggering_element['#name'] : NULL;
    $field_label = !empty($element['#field_title']) ? $element['#field_title'] : $element['#title'];
    if (!in_array($button, array(
      implode('__', $element['#parents']) . '__send_verification',
      implode('__', $element['#parents']) . '__verify',
    ))
    ) {
      $op = NULL;
    }
    $field_path = implode('][', $element['#parents']);
    $tree_parents = $element['#parents'];
    $value = $element['#value'];
    $input = NestedArray::getValue($form_state->getUserInput(), $tree_parents);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $form_object = $form_state->getFormObject();
    $settings = array();
    if($form_object instanceof \Drupal\Core\Entity\ContentEntityForm) {
      /** @var ContentEntityInterface $entity */
      $entity = $form_object->getEntity();
      $entity_type = $entity->getEntityTypeId();
      $field_name = $element['#parents'][0];
      $settings = $entity->getFieldDefinition($field_name)->getSettings();
    }
    $input = $input ? $input : array();
    $mobile_number = NULL;
    $countries = $util->getCountryOptions(array(), TRUE);
    $verified = FALSE;
    if ($input) {
      $input += count($element['#allowed_countries']) == 1 ? array('country-code' => key($element['#allowed_countries'])) : array();
      try {
        $mobile_number = $util->testMobileNumber($input['mobile'], $input['country-code']);
        $verified = $util->isVerified($mobile_number);
      }
      catch (MobileNumberException $e) {
        switch ($e->getCode()) {
          case MobileNumberException::ERROR_NO_NUMBER:
            if ($op) {
              $errors[$field_path . '][mobile'] = t('Phone number in %field is required.', array(
                '%field' => $field_label,
              ));
            }
            break;

          case MobileNumberException::ERROR_INVALID_NUMBER:
          case MobileNumberException::ERROR_WRONG_TYPE:
            $errors[$field_path . '][mobile'] = t('The phone number %value provided for %field is not a valid mobile number for country %country.', array(
              '%value' => $input['mobile'],
              '%field' => $field_label,
              '%country' => $countries[$input['country-code']],
            ));

            break;

          case MobileNumberException::ERROR_WRONG_COUNTRY:
            $errors[$field_path . '][mobile'] = t('The country %value provided for %field does not match the mobile number prefix.', array(
              '%value' => $countries[$input['country-code']],
              '%field' => $field_label,
            ));
            break;
        }
      }
    }

    $verified = $verified || (!empty($element['#default_value']['verified']) && !empty($value['value']) && $value['value'] == $element['#default_value']['value']);

    if ($mobile_number && !$errors) {
      $country = $util->getCountry($mobile_number);
      if ($element['#allowed_countries'] && empty($element['#allowed_countries'][$country])) {
        $errors[$field_path . '][country-code'] = t('The country %value provided for %field is not an allowed country.', array(
          '%value' => $countries[$country],
          '%field' => $field_label,
        ));
      }
      elseif ($op && !$util->checkFlood($mobile_number)) {
        $errors[$field_path . '][verification_code'] = t('Too many validation attempts for %field, please try again in a few hours.', array(
          '%field' => $field_label,
        ));
      }
      elseif ($op == 'mobile_number_send_verification' && !$verified && !$util->sendVerification($mobile_number, $element['#message'], $util->generateVerificationCode(), $element['#token_data'])
      ) {
        $errors[NULL] = t('An error occurred while sending sms.');
      }
      elseif ($op == 'mobile_number_verify' && !$verified = $util->verifyCode($mobile_number, $input['verification_code'])) {
        $errors[$field_path . '][verification_code'] = t('Invalid verification code for %field.', array(
          '%field' => $field_label,
        ));
      }
      elseif (!$op && !$verified && $element['#verify'] == $util::MOBILE_NUMBER_VERIFY_REQUIRED && !\Drupal::currentUser()->hasPermission('bypass mobile number verification requirement')) {
        $errors[$field_path . '][mobile'] = t('%field verification is required.', array(
          '%field' => $field_label,
        ));
      }
      elseif (!$op && !empty($input['tfa']) && !$verified) {
        $errors[$field_path . '][tfa'] = t('%field verification is required for enabling two-factor authentication.', array(
          '%field' => $field_label,
        ));
      }
      elseif (!$op && !empty($settings['unique']) && $util->getCallableNumber($mobile_number) !== $element['#default_value']['value']) {
        $entity_type = $form_object->getEntity()->getEntityTypeId();
        $field_name = $element['#parents'][0];

        $field_query = \Drupal::entityQuery($entity_type);
        $field_query->condition($field_name . '.value', $util->getCallableNumber($mobile_number));

        if ($settings['unique'] == $util::MOBILE_NUMBER_UNIQUE_YES_VERIFIED) {
          $field_query->condition($field_name . '.verified', 1);
        }

        $result = $field_query->execute();

        if ($result) {
          $errors[$field_path . '][mobile'] = t('The number for %field already exists. It must be unique.', array(
            '%field' => $field_label,
          ));
        }
      }
    }

    $verification_prompt = FALSE;

    if ($mobile_number) {
      switch ($op) {
        case 'mobile_number_send_verification':
          $verification_prompt = !$verified && !$errors;
          break;

        case 'mobile_number_verify':
          $verification_prompt = !$verified;
          break;
      }
    }

    $storage = $form_state->getStorage();

    if ($verification_prompt && $op) {
      $element['#attached']['drupalSettings']['mobileNumberVerificationPrompt'] = $element['#id'];
      $storage['mobileNumberVerificationPrompt'][$field_path] = $element['#id'];
    }
    else {
      unset($storage['mobileNumberVerificationPrompt'][$field_path]);
    }

    if ($verified && $op) {
      $element['#attached']['drupalSettings']['mobileNumberVerified'] = $element['#id'];
      $storage['mobileNumberVerified'][$field_path] = $element['#id'];
    }
    else {
      unset($storage['mobileNumberVerified'][$field_path]);
    }

    foreach ($errors as $field => $error) {
      $form_state->setErrorByName($field, $error);
    }

    $storage['mobile_number_fields'][$field_path]['errors'] = $errors;
    $form_state->setStorage($storage);

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
    drupal_get_messages();
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $tree_parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    array_pop($tree_parents);

    $element = NestedArray::getValue($complete_form, $parents);
    $field_path = implode('][', $tree_parents);
    $storage = $form_state->getStorage();
    $errors = !empty($storage['mobile_number_fields'][$field_path]['errors']) ? $storage['mobile_number_fields'][$field_path]['errors'] : array();

    foreach ($errors as $field => $error) {
      drupal_set_message($error, 'error');
      unset($errors[$field]);
    }

    $element['messages'] = array('#type' => 'status_messages');
    unset($element['_weight']);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(NULL, $element));

    if (!empty($storage['mobileNumberVerificationPrompt'][$field_path])) {
      $response->addCommand(new SettingsCommand(array('mobileNumberVerificationPrompt' => $element['#id'])));
    }

    if (!empty($storage['mobileNumberVerified'][$field_path])) {
      $response->addCommand(new SettingsCommand(array('mobileNumberVerified' => $element['#id'])));
    }

    return $response;
  }

}
