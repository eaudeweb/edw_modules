EDW Paragraphs View
=============================================

This module provides a View paragraph, using Views Reference Field module and 
allows a content entity to embed a view block.

#### View
| Field label | Field name | Description | Field type      | Cardinality | Required | Translatable | Widget                      |
|-------------|------------|-------------|-----------------|-------------|----------|--------------|-----------------------------|
| Block       | field_view | -           | Views reference | Single      | No       | No           | View Reference Autocomplete |


## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_view`
3. Go to **Structure > Paragraphs types > View > Manage fields**, edit
`field_view` and check **Preselect View Options** and **Enable extra 
settings** sections.