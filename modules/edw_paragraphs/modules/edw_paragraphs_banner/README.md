EDW Paragraphs Banner
=============================================

Provides Banner paragraph that display a prominent message and related action.

#### Banner
| Field label           | Field name             | Description                                 | Field type             | Cardinality | Required | Translatable | Widget         |
|-----------------------|------------------------|---------------------------------------------|------------------------|-------------|----------|--------------|----------------|
| Title                 | field_title            | -                                           | Text                   | Single      | No       | Yes          | Text field     |
| Description           | field_description      | -                                           | Text (formatted, long) | Single      | Yes      | Yes          | Text area      |
| Display as full width | field_full_width       | Display the banner full width (default: No) | Boolean                | Single      | No       | No           | Checkbox       |
| Image                 | field_media            | -                                           | Media entity reference | Single      | Yes      | No           | Media library  |
| Call to action        | field_link             | -                                           | Link                   | Single      | No       | No           | Link           |
| Banner alignment      | field_banner_alignment | Default: Centered                           | List (text)            | Single      | No       | Yes          | Chosen/Similar |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_banner`
3. (TODO: this part should be in edwt) Check banner component: `edw_paragraphs/templates/components/edw-banner.twig` and the template example: `edw_paragraphs/templates/paragraph--edw-banner.html.twig`