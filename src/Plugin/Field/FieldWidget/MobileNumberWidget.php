<?php

namespace Drupal\mobile_number\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mobile_number\Element\MobileNumber;
use Drupal\mobile_number\MobileNumberUtilInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'mobile_number' widget.
 *
 * @FieldWidget(
 *   id = "mobile_number_default",
 *   label = @Translation("Mobile Number"),
 *   description = @Translation("Mobile number field default widget."),
 *   field_types = {
 *     "mobile_number",
 *     "telephone"
 *   }
 * )
 */
class MobileNumberWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    return parent::defaultSettings() + array(
      'default_country' => 'US',
      'countries' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $field_settings = $this->getFieldSettings();
    $field_country_validation = isset($field_settings['countries']);

    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    /** @var ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();

    $form_state->set('field_item', $this);

    $verification_enabled = !empty($this->fieldDefinition) && ($this->fieldDefinition->getType() == 'mobile_number');

    $element['default_country'] = array(
      '#type' => 'select',
      '#title' => t('Default Country'),
      '#options' => $util->getCountryOptions(array(), TRUE),
      '#default_value' => $this->getSetting('default_country'),
      '#description' => t('Default country for mobile number input.'),
      '#required' => TRUE,
      '#element_validate' => array(array(
        $this,
        'settingsFormValidate',
      ),
      ),
    );

    if (!$field_country_validation) {
      $element['countries'] = array(
        '#type' => 'select',
        '#title' => t('Allowed Countries'),
        '#options' => $util->getCountryOptions(array(), TRUE),
        '#default_value' => $this->getSetting('countries'),
        '#description' => t('Allowed counties for the mobile number. If none selected, then all are allowed.'),
        '#multiple' => TRUE,
        '#attached' => array('library' => array('mobile_number/element')),
      );
    }

    if ($verification_enabled) {
    }

    return $element;
  }

  /**
   * Form element validation handler; Invokes selection plugin's validation.
   *
   * @param array $element
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public function settingsFormValidate(array $element, FormStateInterface $form_state) {
    $parents = $element['#parents'];
    array_pop($parents);
    $settings = $this->getFieldSettings();
    $settings += NestedArray::getValue($form_state->getValues(), $parents);

    $default_country = $settings['default_country'];
    $allowed_countries = $settings['countries'];
    if (!empty($allowed_countries) && !in_array($default_country, $allowed_countries)) {
      $form_state->setError($element, t('Default country is not in one of the allowed countries.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    /** @var ContentEntityInterface $entity */
    $entity = $items->getEntity();
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $settings = $this->getFieldSettings();
    $settings += $this->getSettings() + static::defaultSettings();

    $tfa_field = $util->getTfaField();

    $element += array(
      '#type' => 'mobile_number',
      '#description' => $element['#description'],
      '#default_value' => array(
        'value' => $item->value,
        'country' => !empty($item->country) ? $item->country : $settings['default_country'],
        'local_number' => $item->local_number,
        'verified' => $item->verified,
        'tfa' => $item->tfa,
      ),
      '#allowed_countries' => array_combine($settings['countries'], $settings['countries']),
      '#verify' => ($util->isSmsEnabled() && !empty($settings['verify'])) ? $settings['verify'] : MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE,
      '#message' => !empty($settings['message']) ? $settings['message'] : NULL,
      '#tfa' => (
        $entity->getEntityTypeId() == 'user' &&
        $tfa_field == $items->getFieldDefinition()->getName() &&
        $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() == 1
      ) ? TRUE : NULL,
      '#token_data' => !empty($entity) ? array($entity->getEntityTypeId() => $entity) : array(),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $op = MobileNumber::getOp($element, $form_state);
    $mobile_number = MobileNumber::getMobileNumber($element);

    if ($op == 'mobile_number_send_verification' && $util->checkFlood($mobile_number)) {
      return FALSE;
    }

    return parent::errorElement($element, $error, $form, $form_state);
  }

}
