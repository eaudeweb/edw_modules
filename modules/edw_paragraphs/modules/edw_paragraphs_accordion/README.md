EDW Paragraphs Accordion
=============================================

This module provides the Accordion paragraph.

The paragraph shows a collection of collapsible items.

#### Accordion
| Field label | Field name       | Description               | Field type                 | Cardinality | Required | Translatable | Widget    |
|-------------|------------------|---------------------------|----------------------------|-------------|----------|--------------|-----------|
| Items       | field_paragraphs | List with accordion items | Entity reference revisions | Multiple    | Yes      | No           | Paragraph |

#### Accordion item
| Field label | Field name       | Description | Field type                 | Cardinality | Required | Translatable | Widget     |
|-------------|------------------|-------------|----------------------------|-------------|----------|--------------|------------|
| Title       | field_title      | -           | Text                       | Single      | Yes      | Yes          | Text field |
| Content     | field_paragraphs | -           | Entity reference revisions | Multiple    | Yes      | No           | Paragraph  |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_accordion`
