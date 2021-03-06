<?php

/**
 * @file
 * Enables demo content.
 */

/**
 * Implements hook_install_tasks().
 */
function opendevportal_install_tasks(&$install_state) {
  $tasks = [];

  if (empty($install_state['config_install_path'])) {
    $tasks['opendevportal_module_configure_form'] = [
      'display_name' => t('Select demo content'),
      'type' => 'form',
      'function' => 'Drupal\opendevportal\Installer\Form\ModuleConfigureForm',
    ];
    $tasks['opendevportal_module_install'] = [
      'display_name' => t('Install demo content'),
    ];
  }
  return $tasks;
}

/**
 * Installs the demo modules in a batch.
 *
 * @param array $install_state
 *   The install state.
 *
 * @return array
 *   A batch array to execute.
 */
function opendevportal_module_install(array &$install_state) {
  set_time_limit(0);
  $modules = $install_state['opendevportal_additional_modules'];
  try {
    \Drupal::service('module_installer')->install($modules);
  }
  catch (\Exception $e) {
    \Drupal::logger('opendevportal')->error($e->getMessage());
  }
}
