EDW Paragraphs Banner
=============================================

Provides Banner paragraph that display a prominent message and related action.

#### Banner
| Field label    | Field name        | Description                                 | Field type             | Cardinality | Required | Translatable | Widget         |
|----------------|-------------------|---------------------------------------------|------------------------|-------------|----------|--------------|----------------|
| Title          | field_title       | -                                           | Text                   | Single      | No       | Yes          | Text field     |
| Description    | field_description | -                                           | Text (formatted, long) | Single      | Yes      | Yes          | Text area      |
| Image          | field_media       | -                                           | Media entity reference | Single      | Yes      | No           | Media library  |
| Call to action | field_link        | -                                           | Link                   | Single      | No       | No           | Link           |
| Variations     | field_banner_variations  |                                             | List (text)            | Single      | No       | Yes          | Chosen/Similar |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_banner`
