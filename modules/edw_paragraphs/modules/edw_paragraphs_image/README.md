EDW Paragraphs Image
=============================================

A simple component, used to add an image.

#### Image
| Field label             | Field name             | Description | Field type             | Cardinality | Required | Translatable | Widget        |
|-------------------------|------------------------|-------------|------------------------|-------------|----------|--------------|---------------|
| Image                   | field_media            |             | Media entity reference | Single      | Yes      | No           | Media library |
| Sticky to screen margin | field_sticky_to_margin |             | Boolean                | Single      | No       | No           | Checkbox      |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_image`