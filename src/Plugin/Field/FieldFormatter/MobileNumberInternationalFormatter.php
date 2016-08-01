<?php


namespace Drupal\mobile_number\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'mobile_number_international' formatter.
 *
 * @FieldFormatter(
 *   id = "mobile_number_international",
 *   label = @Translation("International Number"),
 *   field_types = {
 *     "mobile_number"
 *   }
 * )
 */
class MobileNumberInternationalFormatter extends FormatterBase {

  public $phoneDisplayFormat = 1;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();

    $form['as_link'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show as TEL link'),
      '#default_value' => $settings['as_link'],
    );

    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    $settings = $this->getSettings();

    if (!empty($settings['as_link'])) {
      $summary[] = t('Show as TEL link');
    }
    else {
      $summary[] = t('Show as plaintext');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\mobile_number\MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $element = array();
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      /** @var \Drupal\mobile_number\Plugin\Field\FieldType\MobileNumberItem $item */
      if ($mobile_number = $util->getMobileNumber($item->getValue())) {
        if (!empty($settings['as_link'])) {
          $element[$delta] = array(
            '#type' => 'link',
            '#title' => $util->libUtil()->format($mobile_number, $this->phoneDisplayFormat),
            '#href' => "tel:" . $util->getCallableNumber($mobile_number),
          );
        }
        else {
          $element[$delta] = array(
            '#plain_text' => $util->libUtil()->format($mobile_number, $this->phoneDisplayFormat),
          );
        }
      }
    }

    return $element;
  }

}
