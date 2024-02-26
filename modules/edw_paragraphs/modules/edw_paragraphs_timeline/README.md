EDW Paragraphs Timeline
=============================================

Provides Timeline paragraph that displays items visually on a time axis.

#### Timeline entries
| Field label | Field name       | Description              | Field type                 | Cardinality | Required | Translatable | Widget    |
|-------------|------------------|--------------------------|----------------------------|-------------|----------|--------------|-----------|
| Items       | field_paragraphs | List with timeline items | Entity reference revisions | Multiple    | Yes      | No           | Paragraph |

#### Timeline entry
| Field label | Field name                     | Description | Field type                 | Cardinality | Required | Translatable | Widget         |
|-------------|--------------------------------|-------------|----------------------------|-------------|----------|--------------|----------------|
| Title       | field_title                    | -           | Text                       | Single      | Yes      | Yes          | Text field     |
| Variations  | field_timeline_item_variations |             | List (text)                | Single      | No       | Yes          | Chosen/Similar |
| Content     | field_paragraphs               | -           | Entity reference revisions | Multiple    | Yes      | No           | Paragraph      |
| Image       | field_media                    | -           | Media entity reference     | Single      | Yes      | No           | Media library  |
| Title       | field_text_plain               | -           | Text                       | Single      | Yes      | Yes          | Text field     |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_timeline`