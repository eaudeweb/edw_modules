EDW Paragraphs Announcement
=============================================

desc

#### Block Item
| Field label | Field name  | Description | Field type                 | Cardinality | Required | Translatable | Widget        |
|-------------|-------------|-------------|----------------------------|-------------|----------|--------------|---------------|
| Title       | field_title | -           | Text                       | Single      | No       | Yes          | Text field    |
| Content     | field_body  | -           | Text (formatted, long)     | Single      | No       | Yes          | Text field    |
| Documents   | field_media | -           | Entity reference: Document | Multiple    | No       | Yes          | Media library |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_announcement`
