<?php

namespace Drupal\mobile_number\Plugin\Field\FieldType;

use Drupal\user\Entity\User;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\mobile_number\MobileNumberUtilInterface;

/**
 * Plugin implementation of the 'mobile_number' field type.
 *
 * @FieldType(
 *   id = "mobile_number",
 *   label = @Translation("Mobile Number"),
 *   description = @Translation("Stores international number, local number, country code, verified status, and tfa option for mobile numbers."),
 *   default_widget = "mobile_number_default",
 *   default_formatter = "mobile_number_international"
 * )
 */
class MobileNumberItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'unique' => MobileNumberUtilInterface::MOBILE_NUMBER_UNIQUE_NO,
    ) + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    return array(
      'default_country' => 'US',
      'countries' => array(),
      'verify' => $util->isSmsEnabled() ? MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_OPTIONAL : MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE,
      'message' => MobileNumberUtilInterface::MOBILE_NUMBER_DEFAULT_SMS_MESSAGE,
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'value' => array(
          'type' => 'varchar',
          'length' => 19,
          'not null' => TRUE,
          'default' => '',
        ),
        'country' => array(
          'type' => 'varchar',
          'length' => 3,
          'not null' => TRUE,
          'default' => '',
        ),
        'local_number' => array(
          'type' => 'varchar',
          'length' => 15,
          'not null' => TRUE,
          'default' => '',
        ),
        'verified' => array(
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ),
        'tfa' => array(
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ),
      ),
      'indexes' => array(
        'value' => array('value'),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('E.165 Number'))
      ->addConstraint('Length', array('max' => 19))
      ->setRequired(TRUE);

    $properties['country'] = DataDefinition::create('string')
      ->setLabel(t('Country Code'))
      ->addConstraint('Length', array('max' => 3))
      ->setRequired(TRUE);

    $properties['local_number'] = DataDefinition::create('string')
      ->setLabel(t('National Number'))
      ->addConstraint('Length', array('max' => 15))
      ->setRequired(TRUE);

    $properties['verified'] = DataDefinition::create('boolean')
      ->setLabel(t('Verified Status'))
      ->setRequired(TRUE);

    $properties['tfa'] = DataDefinition::create('boolean')
      ->setLabel(t('TFA Option'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    if ($mobile_number = $util->getMobileNumber($this->get('value')
      ->getValue())
    ) {
      $this->writePropertyValue('country', $util->getCountry($mobile_number));
      $this->writePropertyValue('local_number', $util->getLocalNumber($mobile_number));
      $this->writePropertyValue('tfa', !empty($this->get('tfa')
        ->getValue()) ? 1 : 0);
    }
    else {
      $this->writePropertyValue('value', NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    $field = $form_state->getFormObject()->getEntity();

    $element = array();

    $element['unique'] = array(
      '#type' => 'radios',
      '#title' => t('Unique'),
      '#options' => array(
        $util::MOBILE_NUMBER_UNIQUE_NO => t('No'),
        $util::MOBILE_NUMBER_UNIQUE_YES => t('Yes'),
        $util::MOBILE_NUMBER_UNIQUE_YES_VERIFIED => t('Yes, only verified numbers'),
      ),
      '#default_value' => $field->getSetting('unique'),
      '#description' => t('Should mobile numbers be unique within this field.'),
      '#required' => TRUE,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    $field = $form_state->getFormObject()->getEntity();

    $element['#type'] = 'container';
    $element['#element_validate'][] = array(
      get_class($this),
      'fieldSettingsFormValidate',
    );
    $form_state->set('field_item', $this);

    if ($form['#entity'] instanceof User) {
      $element['tfa'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use this field for two-factor authentication'),
        '#description' => t("If enabled, users will be able to choose if to use the number for two factor authentication. Only one field can be set true for this value, verification must be enabled, and the field must be of cardinality 1. Users are required to verify their number when enabling their two-factor authenticaion. <a href='https://www.drupal.org/project/tfa' target='_blank'>Two Factor Authentication</a> must be installed, as well as a supported sms provider such as <a href='https://www.drupal.org/project/smsframework' target='_blank'>SMS Framework</a>."),
        '#default_value' => $this->tfaAllowed() && $util->getTfaField() === $this->getFieldDefinition()
          ->getName(),
        '#disabled' => !$this->tfaAllowed(),
      );

      if ($this->tfaAllowed()) {
        $element['tfa']['#states'] = array(
          'disabled' => array('input[name="settings[verify]"]' => array('value' => $util::MOBILE_NUMBER_VERIFY_NONE)),
        );
      }
    }

    $element['default_country'] = array(
      '#type' => 'select',
      '#title' => t('Default Country'),
      '#options' => $util->getCountryOptions(),
      '#default_value' => $field->getSetting('default_country'),
      '#description' => t('Default country for mobile number input.'),
      '#required' => TRUE,
      '#element_validate' => array(
        array(
          static::class,
          'fieldSettingsFormValidate',
        ),
      ),
    );

    $element['countries'] = array(
      '#type' => 'select',
      '#title' => t('Allowed Countries'),
      '#options' => $util->getCountryOptions(array(), TRUE),
      '#default_value' => $field->getSetting('countries'),
      '#description' => t('Allowed counties for the mobile number. If none selected, then all are allowed.'),
      '#multiple' => TRUE,
      '#attached' => array('library' => array('mobile_number/element')),
    );

    $element['verify'] = array(
      '#type' => 'radios',
      '#title' => t('Verification'),
      '#options' => array(
        MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE => t('None'),
        MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_OPTIONAL => t('Optional'),
        MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_REQUIRED => t('Required'),
      ),
      '#default_value' => $field->getSetting('verify'),
      '#description' => (string) t('Verification requirement. Will send sms to mobile number when user requests to verify the number as their own. Requires <a href="https://www.drupal.org/project/smsframework" target="_blank">SMS Framework</a> or any other sms sending module that integrates with with the Mobile Number module.'),
      '#required' => TRUE,
      '#disabled' => !$util->isSmsEnabled(),
    );

    $element['message'] = array(
      '#type' => 'textarea',
      '#title' => t('Verification Message'),
      '#default_value' => $field->getSetting('message'),
      '#description' => t('The SMS message to send during verification. Replacement parameters are available for verification code (!code) and site name (!site_name). Additionally, tokens are available if the token module is enabled, but be aware that entity values will not be available on entity creation forms as the entity was not created yet.'),
      '#required' => TRUE,
      '#token_types' => array($field->getTargetEntityTypeId()),
      '#disabled' => !$util->isSmsEnabled(),
    );

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $element['message']['#element_validate'] = array('token_element_validate');
      $element['message_token_tree']['token_tree'] = array(
        '#theme' => 'token_tree',
        '#token_types' => array($field->getTargetEntityTypeId()),
        '#dialog' => TRUE,
      );
    }

    return $element;
  }

  /**
   * Form element validation handler; Invokes selection plugin's validation.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    $handlers = $form_state->getSubmitHandlers();
    $form_state->setSubmitHandlers(array_merge($handlers, [
      array(
        static::class,
        'fieldSettingsFormSubmit',
      ),
    ]));
    $settings = $form_state->getValue('settings');
    t($settings['message']);
    $default_country = $settings['default_country'];
    $allowed_countries = $settings['countries'];
    if (!empty($allowed_countries) && empty($allowed_countries[$default_country])) {
      $form_state->setErrorByName($form['default_country'], t('Default country is not in one of the allowed countries.'));
    }
  }

  /**
   * Submit callback for mobile number field item.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function fieldSettingsFormSubmit(array $form, FormStateInterface $form_state) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $tfa = !empty($form_state->getValue('settings')['tfa']);
    $field_name = $form_state->get('field_item')
      ->getFieldDefinition()
      ->getName();
    if (!empty($tfa)) {
      $util->setTfaField($field_name);
    }
    elseif ($field_name === $util->getTfaField()) {
      $util->setTfaField('');
    }
  }

  /**
   * Checks if tfa is allowed based on tfa module installation and field cardinality.
   *
   * @return bool
   *   True or false.
   */
  public function tfaAllowed() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    return $util->isTfaEnabled() && ($this->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality() == 1);
  }

  public static function countryOptions() {
    /** @var \Drupal\mobile_number\MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    return $util->getCountryOptions(array(), TRUE);
  }

  public static function booleanOptions() {
    return array(t('No'), t('Yes'));
  }

}
