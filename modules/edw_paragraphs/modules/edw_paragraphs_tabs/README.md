EDW Paragraphs Tabs
=============================================

Provides Tabs paragraph that displays items easy to scan labels of the relevant 
information, indicative of the additional content that is available through 
extra interaction.

#### Tabs
| Field label | Field name       | Description              | Field type                 | Cardinality | Required | Translatable | Widget    |
|-------------|------------------|--------------------------|----------------------------|-------------|----------|--------------|-----------|
| Items       | field_paragraphs | List with timeline items | Entity reference revisions | Multiple    | Yes      | No           | Paragraph |

#### Tab
| Field label | Field name       | Description | Field type                 | Cardinality | Required | Translatable | Widget     |
|-------------|------------------|-------------|----------------------------|-------------|----------|--------------|------------|
| Title       | field_title      |             | Text                       | Single      | Yes      | Yes          | Text field |
| Content     | field_paragraphs |             | Entity reference revisions | Multiple    | Yes      | No           | Paragraph  |
| ID          | field_id         |             | Text                       | Single      | No       | No           | Text field |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_tabs`