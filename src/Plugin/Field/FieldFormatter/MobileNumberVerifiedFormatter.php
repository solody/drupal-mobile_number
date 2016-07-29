<?php


namespace Drupal\mobile_number\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'mobile_number_verified' formatter.
 *
 * @FieldFormatter(
 *   id = "mobile_number_verified",
 *   label = @Translation("Verified status"),
 *   field_types = {
 *     "mobile_number"
 *   }
 * )
 */
class MobileNumberVerifiedFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $element = array();

    foreach ($items as $delta => $item) {
      /** @var MobileNumberItemInterface $item */
      if ($mobile_number = $util->getMobileNumber($item->value)) {
        $element[$delta] = array(
          '#plain_text' => !empty($item->verified) ? (string) t('Verified') : (string) t('Not verified'),
        );
      }
    }

    return $element;
  }

}
