EDW Paragraphs Media
=============================================

This module provides the Text with Featured media paragraph.

The paragraph shows a rich text with a title and a featured media on the
left/right.

#### Text with image
| Field label    | Field name           | Description      | Field type             | Cardinality | Required | Translatable | Widget        |
|----------------|----------------------|------------------|------------------------|-------------|----------|--------------|---------------|
| Grid layout    | field_grid_layout    | Default: 50%/50% | List (text)            | Single      | No       | No           | List          |
| Media position | field_media_position | Default: Left    | List (text)            | Single      | No       | No           | List          |
| Heading        | field_title          | -                | Text                   | Single      | No       | Yes          | Text field    |
| Full text      | field_body           | -                | Text (formatted, long) | Single      | Yes      | Yes          | Text field    |
| Media          | field_media          | -                | Media entity reference | Single      | No       | No           | Media library |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_media`