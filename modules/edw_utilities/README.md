# edw_utilities

Custom Drupal module with utilities for repetitive operations

## Search API processors
* ### Index depending on entity's field value
Location: `modules/edw_utilities/src/Plugin/search_api/processor/FieldValueIndex.php`

## Terms suggestions autocomplete

Location: `modules/edw_utilities/src/Controller/TermsAutocompleteController.php`

You can alter autocomplete results with `hook_terms_autocomplete_query_alter`

Attach the autocomplete:
```php
function hook_form_views_exposed_form_alter(array &$form, FormStateInterface &$form_state) {
  switch ($form['#id']) {
    case 'views-exposed-form-ID':
      $form['text']['#autocomplete_route_name'] = 'edw_utilities.terms_autocomplete';
      $form['text']['#autocomplete_route_parameters'] = [
        'vid' => 'YOUR_TAXONOMY_TERM_BUNDLE',
      ];
      break;
  }
}
```

## View Contextual Argument

* ### The entity browser widget context argument plugin to extract a node.

Location: `modules/edw_utilities/src/Plugin/views/argument_default/EntityBrowserWidgetContextNode.php`