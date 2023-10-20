EDW Paragraphs Carousel
=============================================

Provides Carousel paragraph that display items similar to a Banner with
multiple slides.

#### Gallery
| Field label           | Field name       | Description                                 | Field type                 | Cardinality | Required | Translatable | Widget    |
|-----------------------|------------------|---------------------------------------------|----------------------------|-------------|----------|--------------|-----------|
| Display as full width | field_full_width | Display the banner full width (default: No) | Boolean                    | Single      | No       | No           | Checkbox  |
| Items                 | field_paragraphs | List with accordion items                   | Entity reference revisions | Multiple    | Yes      | No           | Paragraph |

#### Carousel item
| Field label      | Field name             | Description                                 | Field type             | Cardinality | Required | Translatable | Widget         |
|------------------|------------------------|---------------------------------------------|------------------------|-------------|----------|--------------|----------------|
| Title            | field_title            | -                                           | Text                   | Single      | No       | Yes          | Text field     |
| Description      | field_description      | -                                           | Text (formatted, long) | Single      | Yes      | Yes          | Text area      |
| Image            | field_media            | -                                           | Media entity reference | Single      | Yes      | No           | Media library  |
| Link             | field_link             | -                                           | Link                   | Single      | No       | No           | Link           |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_carousel`