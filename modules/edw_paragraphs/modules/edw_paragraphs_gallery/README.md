EDW Paragraphs Gallery
============================

This module provides a paragraph type that displays a gallery of media entities.

#### Gallery
| Field label  | Field name                 | Description     | Field type             | Cardinality | Required | Translatable | Widget        |
|--------------|----------------------------|-----------------|------------------------|-------------|----------|--------------|---------------|
| Title        | field_title                | -               | Text                   | Single      | No       | Yes          | Text field    |
| Description  | field_body                 | -               | Text (formatted, long) | Single      | Yes      | Yes          | Text area     |
| Media        | field_gallery_items        | -               | Media entity reference | Multiple    | Yes      | No           | Media library |
| Gallery mode | field_gallery_display_mode | Default: Inline | List                   | Single      | No       | No           | List          |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_gallery`
3. Check gallery component: `edw_paragraphs_gallery/templates/components/edw-gallery.twig`
and the template example: `edw_paragraphs_gallery/templates/paragraph--edw-gallery.html.twig`

### Dependencies
* [lightgallery library](https://www.lightgalleryjs.com/) (>=2.7.x)

## Installation

Install this module like every other Drupal module. Also it's needed to get the
lightgallery library.

### Installation: composer

```json
{
  "type": "package",
  "package": {
      "name": "sachinchoolur/lightgallery",
      "version": "2.7.1",
      "type": "drupal-library",
      "source": {
          "url": "https://github.com/sachinchoolur/lightGallery",
          "type": "git",
          "reference": "2.7.1"
      }
  }
}
```

After this you can install the library using composer and the library will be
downloaded into the libraries folder.
```php
$ composer require 'sachinchoolur/lightgallery'
```
