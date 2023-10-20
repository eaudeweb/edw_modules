EDW Paragraphs Heading
=============================================

TODO

#### Heading
| Field label | Field name          | Description         | Field type             | Cardinality | Required | Translatable | Widget        |
|-------------|---------------------|---------------------|------------------------|-------------|----------|--------------|---------------|
| Heading     | field_heading_level | H2-H6 (default: H2) | List (text)            | Single      | No       | No           | List          |
| Title       | field_title         | -                   | Text                   | Single      | No       | Yes          | Text field    |
| Subtitle    | field_subtitle      | -                   | Text                   | Single      | No       | Yes          | Text field    |
| Icon        | field_icon          | -                   | Media entity reference | Single      | No       | No           | Media library |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_heading`