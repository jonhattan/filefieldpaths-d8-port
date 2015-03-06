<?php

namespace Drupal\filefield_paths;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;


class FileFieldPathsSettingsManager {

  public function alterSettingsForm(array &$form, FormStateInterface $form_state) {
    $field = $form_state->get('field');

    // Get our 3rd party settings to use as defaults on the form.
    $defaults = $field->getThirdPartySettings('filefield_paths');

    // This gets a list of all the field types from enabled modules that responded
    // to the hook_filefield_paths_field_type_info() invocation. This hook is
    // currently implemented on behalf of File, Image, and Video in the include
    // files under the "modules" directory.
    $field_types = _filefield_paths_get_field_types();

    if (isset($field->field_type) && in_array($field->field_type, array_keys($field_types))) {
      // @TODO: Hiding directory field doesn't work.
      // Hide standard File directory field.
      $form['field']['settings']['file_directory']['#states'] = array(
        'visible' => array(
          ':input[name="form[field][third_party_settings][filefield_paths][enabled]"]' => array('checked' => FALSE),
        ),
      );

      // FFP fieldset.
      $form['field']['third_party_settings']['filefield_paths'] = array(
        '#type' => 'details',
        '#title' => t('File (Field) Paths settings'),
        '#open' => TRUE,
      );

      // Enable / disable.
      $default = isset($defaults['enabled']) ? $defaults['enabled'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['enabled'] = array(
        '#type' => 'checkbox',
        '#title' => t('Enable File (Field) Paths?'),
        '#default_value' => $default,
        '#weight' => -10,
      );

      // Token browser.
      $form['field']['third_party_settings']['filefield_paths']['token_tree'] = array(
        '#type' => '#markup',
        '#theme' => 'token_tree_link',
        '#weight' => -5,
      );

      // File path.
      $default = isset($defaults['filepath']) ? $defaults['filepath'] : '';
      $form['field']['third_party_settings']['filefield_paths']['filepath'] = array(
        '#type' => 'textfield',
        '#title' => t('File path'),
        '#maxlength' => 512,
        '#size' => 128,
        '#element_validate' => array('_file_generic_settings_file_directory_validate'),
        '#default_value' => $default,
      );

      // File path options fieldset.
      $form['field']['third_party_settings']['filefield_paths']['path_options'] = array(
        '#type' => 'details',
        '#title' => t('File path options'),
        '#open' => FALSE,
      );

      // Clean up path with Pathauto.
      $default = isset($defaults['path_options']['pathauto_path']) ? $defaults['path_options']['pathauto_path'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['path_options']['pathauto_path'] = $this->getPathAutoElement('filepath', $default);

      // Clean up path with Transliteration
      $default = isset($defaults['path_options']['transliteration_path']) ? $defaults['path_options']['transliteration_path'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['path_options']['transliteration_path'] = $this->getTransliterationElement('filepath', $default);

      // File name.
      $default = (isset($defaults['filename']) && !empty($defaults['filename'])) ? $defaults['filename'] : '[file:ffp-name-only-original].[file:ffp-extension-original]';
      $form['field']['third_party_settings']['filefield_paths']['filename'] = array(
        '#type' => 'textfield',
        '#title' => t('File name'),
        '#maxlength' => 512,
        '#size' => 128,
        '#element_validate' => array('_file_generic_settings_file_directory_validate'),
        '#default_value' => $default,
      );

      // File name options fieldset.
      $form['field']['third_party_settings']['filefield_paths']['name_options'] = array(
        '#type' => 'details',
        '#title' => t('File name options'),
        '#open' => FALSE,
      );

      // Clean up filename with Pathauto.
      $default = isset($defaults['name_options']['pathauto_filename']) ? $defaults['name_options']['pathauto_filename'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['name_options']['pathauto_filename'] = $this->getPathAutoElement('filename', $default);

      // Clean up filename with Transliteration.
      $default = isset($defaults['name_options']['transliteration_filename']) ? $defaults['name_options']['transliteration_filename'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['name_options']['transliteration_filename'] = $this->getTransliterationElement('filename', $default);

      // Retroactive updates.
      $default = isset($defaults['retroactive_update']) ? $defaults['retroactive_update'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['retroactive_update'] = array(
        '#type' => 'checkbox',
        '#title' => t('Retroactive update'),
        '#description' => t('Move and rename previously uploaded files.') . '<div>' . t('<strong class="warning">Warning:</strong> This feature should only be used on developmental servers or with extreme caution.') . '</div>',
        '#weight' => 11,
        '#default_value' => $default,
      );

      // Active updating.
      $default = isset($defaults['active_updating']) ? $defaults['active_updating'] : FALSE;
      $form['field']['third_party_settings']['filefield_paths']['active_updating'] = array(
        '#type' => 'checkbox',
        '#title' => t('Active updating'),
        '#default_value' => $default,
        '#description' => t('Actively move and rename previously uploaded files as required.') . '<div>' . t('<strong class="warning">Warning:</strong> This feature should only be used on developmental servers or with extreme caution.') . '</div>',
        '#weight' => 12
      );

      // @TODO: Uncomment this when retroactive updates are working.
      // $form['#submit'][] = 'filefield_paths_form_submit';
    }

  }

  /**
   * Returns the form element for the PathAuto checkbox in FFP settings.
   *
   * @param $setting
   *   File path or File name.
   * @param $default
   *   Default or existing value for the form element.
   * @return array
   */
  protected function getPathAutoElement($setting, $default) {
    if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
      $pathauto_enabled = TRUE;
      $description = t('Cleanup %setting using <a href="@pathauto">Pathauto settings</a>.', array(
        '%setting' => $setting,
        '@pathauto' => Url::fromRoute('pathauto.settings.form')));
      $default_value = $default;
    }
    else {
      $pathauto_enabled = FALSE;
      $description = t('Pathauto is not installed');
      $default_value = FALSE;
    }

    return array(
      '#type' => 'checkbox',
      '#title' => t('Cleanup using Pathauto'),
      '#default_value' => $default_value,
      '#description' => $description,
      '#disabled' => !$pathauto_enabled,
    );
  }

  /**
   * Returns the form element for the Transliteration checkbox in FFP settings.
   *
   * @param $setting
   *   File path or File name.
   * @param $default
   *   Default or existing value for the form element.
   * @return array
   */
  protected function getTransliterationElement($setting, $default) {
    if (\Drupal::moduleHandler()->moduleExists('transliteration')) {
      $transliteration_enabled = TRUE;
      $description = t('Provides one-way string transliteration (romanization) and cleans the %setting during upload by replacing unwanted characters.', array('%setting' => $setting));
      $default_value = $default;
    }
    else {
      $transliteration_enabled = FALSE;
      $description = t('Transliteration is not installed');
      $default_value = FALSE;
    }

    return array(
      '#type' => 'checkbox',
      '#title' => t('Cleanup using Transliteration'),
      '#default_value' => $default_value,
      '#description' => $description,
      '#disabled' => !$transliteration_enabled,
    );
  }

}