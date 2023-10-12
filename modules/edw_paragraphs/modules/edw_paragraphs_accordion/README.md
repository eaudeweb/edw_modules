EDW Paragraphs Accordion
=============================================

This module provides the Accordion paragraph.

The paragraph shows a collection of collapsible items.

#### Accordion
| Field label | Field name       | Description               | Field type                 | Cardinality | Required | Translatable | Widget    |
|-------------|------------------|---------------------------|----------------------------|-------------|----------|--------------|-----------|
| Items       | field_paragraphs | List with accordion items | Entity reference revisions | Multiple    | Yes      | No           | Paragraph |

#### Accordion item
| Field label | Field name  | Description | Field type             | Cardinality | Required | Translatable | Widget     |
|-------------|-------------|-------------|------------------------|-------------|----------|--------------|------------|
| Title       | field_title | -           | Text                   | Single      | Yes      | Yes          | Text field |
| Body        | field_body  | -           | Text (formatted, long) | Single      | Yes      | Yes          | Text area  |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_accordion`
3. (TODO: this part should be in edwt) Check accordion component: `edw_paragraphs/templates/components/edw-accordion.twig` and template example: `edw_paragraphs/templates/paragraph--edw-accordion.html.twig`