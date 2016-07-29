<?php

namespace Drupal\mobile_number\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mobile_number\MobileNumberUtilInterface;

/**
 * Plugin implementation of the 'mobile_number' widget.
 *
 * @FieldWidget(
 *   id = "mobile_number_default",
 *   label = @Translation("Default"),
 *   description = @Translation("Mobile number field default widget."),
 *   field_types = {
 *     "mobile_number"
 *   }
 * )
 */
class MobileNumberWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $settings = $items->getFieldDefinition()->getSettings();

    $tfa_field = $util->getTfaField();

    $element += array(
      '#type' => 'mobile_number',
      '#description' => $element['#description'],
      '#default_value' => array(
        'value' => $items[$delta]->value,
        'country' => !empty($items[$delta]->country) ? $items[$delta]->country : $settings['default_country'],
        'local_number' => $items[$delta]->local_number,
        'verified' => $items[$delta]->verified,
        'tfa' => $items[$delta]->tfa,
      ),
      '#required' => $element['#required'],
      '#allowed_countries' => $settings['countries'],
      '#verify' => $util->isSmsEnabled() ? $settings['verify'] : MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE,
      '#message' => $settings['message'],
      '#tfa' => (
        $entity->getEntityTypeId() == 'user' &&
        $tfa_field == $items->getFieldDefinition()->getName() &&
        $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() == 1
      ) ? TRUE : NULL,
      '#token_data' => !empty($entity) ? array($entity->getEntityTypeId() => $entity) : array(),
    );

    return $element;
  }

}
