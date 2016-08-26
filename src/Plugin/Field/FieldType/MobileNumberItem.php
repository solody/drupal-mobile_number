<?php

namespace Drupal\mobile_number\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
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
 *   default_formatter = "mobile_number_international",
 *   constraints = {
 *     "MobileNumber" = {}
 *   }
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
    return parent::defaultFieldSettings() + array(
      'verify' => $util->isSmsEnabled() ? $util::MOBILE_NUMBER_VERIFY_OPTIONAL : MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE,
      'message' => $util::MOBILE_NUMBER_DEFAULT_SMS_MESSAGE,
      'countries' => array(),
    );
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
    $value = $this->getValue();
    return empty($value['value']) && empty($value['local_number']);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('E.165 Number'))
      ->addConstraint('Length', array('max' => 19));

    $properties['country'] = DataDefinition::create('string')
      ->setLabel(t('Country Code'))
      ->addConstraint('Length', array('max' => 3));

    $properties['local_number'] = DataDefinition::create('string')
      ->setLabel(t('National Number'))
      ->addConstraint('Length', array('max' => 15));

    $properties['verified'] = DataDefinition::create('boolean')
      ->setLabel(t('Verified Status'));

    $properties['tfa'] = DataDefinition::create('boolean')
      ->setLabel(t('TFA Option'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $values = $this->getValue();

    if (!empty($values['country'])) {
      if (!empty($values['local_number'])) {
        $number = $values['local_number'];
      }
      $country = $values['country'];
    }

    if (!$number) {
      $number = $values['value'];
    }

    if ($mobile_number = $util->getMobileNumber($number, $country)) {
      $this->value = $util->getCallableNumber($mobile_number);
      $this->country = $util->getCountry($mobile_number);
      $this->local_number = $util->getLocalNumber($mobile_number);
      $this->tfa = !empty($values['tfa']) ? 1 : 0;
      $this->verified = ($this->verify() === TRUE) ? 1 : 0;
    }
    else {
      $this->value = NULL;
      $this->local_number = NULL;
    }

    parent::preSave();
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
    $field = $this->getFieldDefinition();
    $settings = $this->getSettings() + $this->defaultFieldSettings();

    // @todo Remove FALSE after port of TFA for drupal 8 is available
    if ($form['#entity'] instanceof User && FALSE) {
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

    $element['countries'] = array(
      '#type' => 'select',
      '#title' => t('Allowed Countries'),
      '#options' => $util->getCountryOptions(array(), TRUE),
      '#default_value' => $this->getSetting('countries'),
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
      '#default_value' => $settings['verify'],
      '#description' => (string) t('Verification requirement. Will send sms to mobile number when user requests to verify the number as their own. Requires <a href="https://www.drupal.org/project/smsframework" target="_blank">SMS Framework</a> or any other sms sending module that integrates with with the Mobile Number module.'),
      '#required' => TRUE,
      '#disabled' => !$util->isSmsEnabled(),
    );

    $element['message'] = array(
      '#type' => 'textarea',
      '#title' => t('Verification Message'),
      '#default_value' => $settings['message'],
      '#description' => t('The SMS message to send during verification. Replacement parameters are available for verification code (!code) and site name (!site_name). Additionally, tokens are available if the token module is enabled, but be aware that entity values will not be available on entity creation forms as the entity was not created yet.'),
      '#required' => TRUE,
      '#token_types' => array($field->getTargetEntityTypeId()),
      '#disabled' => !$util->isSmsEnabled(),
      '#element_validate' => array(array(
        $this,
        'fieldSettingsFormValidate',
      ),
      ),
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
   * Validate callback for mobile number field item.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    $submit_handlers = $form_state->getSubmitHandlers();
    $submit_handlers[] = array(
      $this,
      'fieldSettingsFormSubmit',
    );
    $form_state->setSubmitHandlers($submit_handlers);
  }

  /**
   * Submit callback for mobile number field item.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function fieldSettingsFormSubmit(array $form, FormStateInterface $form_state) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $settings = $this->getSettings();
    if (!empty(['message'])) {
      t($settings['message']);
    }

    $tfa = !empty($this->getSetting('tfa'));
    $field_name = $this->getFieldDefinition()->getName();
    if (!empty($tfa)) {
      $util->setTfaField($field_name);
    }
    elseif ($field_name === $util->getTfaField()) {
      $util->setTfaField('');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    static $last_numbers = array();

    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    $settings = array(
      'verify' => $util->isSmsEnabled() ? $util::MOBILE_NUMBER_VERIFY_OPTIONAL : $util::MOBILE_NUMBER_VERIFY_NONE,
      'countries' => array(),
    );

    $country = array_rand($util->getCountryOptions($settings['countries']));
    $last = !empty($last_numbers[$country]) ? $last_numbers[$country] : array();
    $mobile_number = NULL;
    if (!$last) {
      $last['count'] = 0;
      $last['example'] = ($number = $util->libUtil()->getExampleNumberForType($country, 1)) ? $number->getNationalNumber() : NULL;
    }
    $example = $last['example'];
    $count = $last['count'];
    if ($example) {
      while ((strlen($count) <= strlen($example)) && !$mobile_number) {
        $number_length = strlen($example);
        $number = substr($example, 0, $number_length - strlen($count)) . $count;
        if (substr($count, 0, 1) != substr($example, strlen($count) - 1, 1)) {
          $mobile_number = $util->getMobileNumber($number, $country);
        }
        $count = ($count + 1) % pow(10, strlen($example));
      };
    }
    $value = array();
    if ($mobile_number) {
      $value = array(
        'value' => $util->getCallableNumber($mobile_number),
      );
      switch ($settings['verify']) {
        case $util::MOBILE_NUMBER_VERIFY_NONE:
          $value['verified'] = 0;
          break;

        case $util::MOBILE_NUMBER_VERIFY_OPTIONAL:
          $value['verified'] = rand(0, 1);
          break;

        case $util::MOBILE_NUMBER_VERIFY_REQUIRED:
          $value['verified'] = 1;
          break;
      }
    }

    return $value;
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

  /**
   * Get mobile number object of the current item.
   *
   * @param bool $throw_exception
   *   Whether to throw mobile number validity exceptions.
   *
   * @return \libphonenumber\PhoneNumber|NULL
   *   Mobile number object, or null if not valid.
   */
  public function getMobileNumber($throw_exception = FALSE) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $values = $this->getValue();
    $number = '';
    $country = '';
    if (!empty($values['country'])) {
      if (!empty($values['local_number'])) {
        $number = $values['local_number'];
      }
      $country = $values['country'];
    }

    if (!$number && !empty($values['value'])) {
      $number = $values['value'];
    }

    if ($throw_exception) {
      return $util->testMobileNumber($number, $country);
    }
    else {
      return $util->getMobileNumber($number, $country);
    }

  }

  /**
   * Is the item's mobile number verified in the field's saved values or current
   * session.
   *
   * @return bool
   *   TRUE if verified, else FALSE.
   */
  public function isVerified() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $input = $this->getValue();
    $field_name = $this->getFieldDefinition()->getName();
    $field_label = $this->getFieldDefinition()->getLabel();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');

    $mobile_number = $this->getMobileNumber();

    if (!$mobile_number) {
      return FALSE;
    }

    $verified = (bool) \Drupal::entityQuery($entity_type_id)
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $entity->id())
      ->condition($field_name, $util->getCallableNumber($mobile_number))
      ->range(0, 1)
      ->condition("$field_name.verified", "1")
      ->count()
      ->execute();

    $verified = $verified || $util->isVerified($mobile_number);

    return $verified;
  }

  /**
   * Performs verification, assuming verification token and code were set. Adds
   * to flood if failed. Will not attempt to verify if number is already verified.
   *
   * @return bool|int|null
   *   TRUE if verification is successful, FALSE if wrong code provided, NULL if
   *   code or token not provided, and -1 if does not pass flood check.
   */
  public function verify() {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $values = $this->getValue();
    $token = $values['verification_token'];
    $code = $values['verification_code'];

    if ($this->isVerified()) {
      return TRUE;
    }

    $mobile_number = $this->getMobileNumber();

    if (!empty($token) && !empty($code) && $mobile_number) {
      if ($util->checkFlood($mobile_number)) {
        return $util->verifyCode($mobile_number, $code, $token);
      }
      else {
        return -1;
      }
    }
    else {
      return NULL;
    }
  }

  /**
   * Is mobile number unique within the entity/field. Will check verified numbers,
   * only if specificed.
   *
   * @param int $unique_type
   *   Unique type [MOBILE_NUMBER_UNIQUE_YES|MOBILE_NUMBER_UNIQUE_YES_VERIEID].
   *
   * @return bool|null
   *   TRUE for is unique, false otherwise. Null if mobile number is not valid.
   */
  public function isUnique($unique_type = MobileNumberUtilInterface::MOBILE_NUMBER_UNIQUE_YES) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');

    $entity = $this->getEntity();
    $field_name = $this->getFieldDefinition()->getName();

    if (!$mobile_number = $this->getMobileNumber()) {
      return NULL;
    }
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');
    $query = \Drupal::entityQuery($entity_type_id)
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $entity->id(), '<>')
      ->condition($field_name, $util->getCallableNumber($mobile_number))
      ->range(0, 1)
      ->count();

    if ($unique_type == MobileNumberUtilInterface::MOBILE_NUMBER_UNIQUE_YES_VERIFIED) {
      $query->condition("$field_name.verified", "1");
    }

    return !(bool) $query->execute();
  }

  /**
   * Get all country options.
   *
   * @return array
   *   Array of countries, with country codes as keys and country names with
   *   prefix as labels.
   */
  public static function countryOptions() {
    /** @var \Drupal\mobile_number\MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    return $util->getCountryOptions(array(), TRUE);
  }

  /**
   * Boolean options for views. Because views' default boolean handler is
   * ridiculous.
   *
   * @return array
   *   Array of 0 => No, 1 => Yes. As it should be.
   */
  public static function booleanOptions() {
    return array(t('No'), t('Yes'));
  }

}
