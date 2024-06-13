<?php

namespace Drupal\edw_utilities\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Plugin implementation of the 'link_with_title_as_language' widget.
 *
 * Extends File Widget with the possibility to display the Language
 * selected from a dropdown with languages instead of title.
 *
 * @FieldWidget(
 *   id = "link_with_title_as_language",
 *   label = @Translation("Link with title as language"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkWithTitleAsLanguageWidget extends LinkWidget {

  /**
   * {@inheritDoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    if ($this->getFieldSetting('title') == DRUPAL_DISABLED) {
      return $element;
    }

    $languages = \Drupal::languageManager()->getStandardLanguageList();
    \Drupal::moduleHandler()->alter('file_languages', $languages);

    $languageOptions = ['' => t('- None -')];
    foreach ($languages as $iso => $language) {
      $languageOptions[$iso] = $language[0];
    }

    $element['title']['#type'] = 'select';
    $element['title']['#title'] = t('Language');
    $element['title']['#options'] = $languageOptions;

    return $element;
  }

}
