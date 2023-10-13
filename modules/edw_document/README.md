# edw_document

Provides a Drupal Document content type to store organisational multilingual 
structured PDF/Word etc. documents

# Installation

Before enabling this module, make sure that the following modules are present in your codebase by adding them to your composer.json and by running composer update:
In `composer.json`:

```php
"require": {
  "drupal/core": "^9.4 || ^10",
  "drupal/better_exposed_filters": "^6.0",
  "drupal/entity_browser": "^2.9",
  "drupal/file_delete": "^2.0",
  "drupal/file_replace": "^1.3",
  "drupal/file_to_media": "^1.0",
  "drupal/search_api_solr":"^4.3",
  "drupal/views_bulk_operations": "^4.2"
}
```
The `entity_reference_revisions` module requires the following patch to be applied:

```php
"patches": {
    "drupal/entity_reference_revisions": {
      "#2799479 - Views doesn't recognize relationship to host": "https://www.drupal.org/files/issues/2022-06-01/entity_reference_revisions-relationship_host_id-2799479-176.patch"
    }
}
```

and for core:^10:

```php
"patches": {
    "drupal/core": {
      "#2429699 - Add Views EntityReference filter to be available for all entity reference fields":"https://git.drupalcode.org/project/drupal/-/merge_requests/3086.patch"
    }
}
```
for core:^9.4
```php
"patches": {
    "drupal/core": {
      "#2457999 - Cannot use relationship for rendered entity on Views": "https://www.drupal.org/files/issues/2023-01-04/2457999-9.5.x-309.patch",
      "#2429699 - Add Views EntityReference filter to be available for all entity reference fields":"https://git.drupalcode.org/project/drupal/-/merge_requests/3086.patch"
    }
}
```

## Basic Configuration

- Field type
  - **File with Language** - File with description and language
- Field widget
  - File with language - default widget for the `File with Language` field type.
  - Multi Language file - display all files grouped by language.
- Field Formatter:
  - File with Language - File formatter for `file_language` field type. Extends 
generic File formatter with the 
possibility to display the language selected from a dropdown with languages. If 
`use_description_as_link_text` setting is true, then show description if 
language is not selected. If both are empty then display the filename. Use
`suppress_language` to suppress the language with description.
  - Dropdown File with Language - overrides the default File with Language 
formatter and display only files with language as a dropdown (using 
`dropdown_file_language` theme).
  - Files group by Language - Group files in tabs using available languages.
- Facet Processor **List item Language** - Display the language name instead 
of langcode.
