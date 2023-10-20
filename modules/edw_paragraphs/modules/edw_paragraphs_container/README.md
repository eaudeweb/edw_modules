EDW Paragraphs Container
=============================================

This module provides a paragraph Container useful when you want to store other components.

#### Block Item
| Field label      | Field name             | Description | Field type                 | Cardinality | Required | Translatable | Widget      |
|------------------|------------------------|-------------|----------------------------|-------------|----------|--------------|-------------|
| Background color | field_background_color | -           | List (text)                | Single      | No       | No           | Select list |
| Background image | field_background_media | -           | Entity reference           | Single      | No       | No           | Select list |
| Container size   | field_container_size   | -           | List (text)                | Single      | Yes      | No           | Select list |
| Content          | field_paragraphs       | -           | Entity reference revisions | Multiple    | Yes      | No           | Paragraph   |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_container`