<?php

function _webform_defaults_mobile_number() {
  return array(
    'name' => '',
    'form_key' => NULL,
    'required' => 0,
    'pid' => 0,
    'weight' => 0,
    'extra' => array(
      'default_country' => 'US',
      'countries' => array(),
      'verify' => MOBILE_NUMBER_VERIFY_OPTIONAL,
      'message' => MOBILE_NUMBER_DEFAULT_SMS_MESSAGE,
      'private' => FALSE,
    ),
  );
}


function _webform_edit_mobile_number($component) {
  $instance['entity_type'] = 'node';
  $instance['settings'] = $component['extra'];
  $form['extra'] = mobile_number_field_instance_settings_form(NULL, $instance);
  unset($form['extra']['default_country']['#element_validate']);
  return $form;
}

function _webform_render_mobile_number($component, $value = NULL, $filter = TRUE) {
  $node = isset($component['nid']) ? node_load($component['nid']) : NULL;
  $settings = $component['extra'];

  $title_display_map = array(
    'before' => 'before',
    'inline' => 'after',
    'none' => 'invisible',
  );

  $element = array(
    '#title' => $component['name'],
    '#title_display' => $title_display_map[$settings['title_display']],
    '#required' => $component['required'],
    '#weight' => $component['weight'],
    '#type' => 'mobile_number',
    '#description' => $settings['description'],
    '#default_value' => $value ? $value : array(),
    '#allowed_countries' => $settings['countries'],
    '#verify' => mobile_number_sms_sending_is_enabled() ? $settings['verify'] : MOBILE_NUMBER_VERIFY_NONE,
    '#message' => $settings['message'],
    '#token_data' => !empty($node) ? array('node' => $node) : array(),
  );

  $element['#default_value'] += array('country' => $settings['default_country']);

  return $element;
}

function _webform_display_mobile_number($component, $value, $format = 'html') {
  $output = array();
  try {
    $mobile_number = new MobileNumber($value['value']);
    $output = array(
      '#type' => 'markup',
      '#markup' => check_plain($mobile_number->libUtil->format($mobile_number->libPhoneNumber, 1)),
    );
  } catch (Exception $e) {

  }
  return $output;
}

function _webform_table_mobile_number($component, $value) {
  $output = array();
  try {
    $mobile_number = new MobileNumber($value['value']);
    $output = array(
      '#type' => 'markup',
      '#markup' => check_plain($mobile_number->libUtil->format($mobile_number->libPhoneNumber, 1)),
    );
  } catch (Exception $e) {

  }
  return $output;
}

function _webform_csv_headers_mobile_number($component, $export_options) {
  $header = array();
  $header[0] = array('', '', '', '');
  $header[1] = array($export_options['header_keys'] ? $component['form_key'] : $component['name'], '');
  $header[2] = array(t('International Number'), t('Country'), t('Local Number'), t('Verified'));
  return $header;
}

function _webform_csv_data_mobile_number($component, $export_options, $value) {

  return array(
    $value['value'],
    $value['country'],
    $value['local_number'],
    $value['verified'],
  );
}