EDW Paragraphs Block Item
=============================================

This module provides a paragraph Block item with a field Block (plugin) that 
allows a content entity to reference and configure custom block instances.

#### Block Item
| Field label | Field name  | Description | Field type     | Cardinality | Required | Translatable | Widget      |
|-------------|-------------|-------------|----------------|-------------|----------|--------------|-------------|
| Block       | field_block | -           | Block (plugin) | Single      | No       | No           | Block field |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_block`
3. Go to **Structure > Paragraphs types > Block item > Manage fields**, edit
`field_block` and configure **Available blocks** section.