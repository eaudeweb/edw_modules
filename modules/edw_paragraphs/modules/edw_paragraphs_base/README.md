EDW Paragraphs Base
=============================================

This module provides a number of Paragraph types that are based on a select
number of components.

## Paragraphs
The module provides the following paragraph types:

#### Button
The Button paragraph allows editors to insert a link in different variants.

| Field label | Field name           | Description | Field type  | Cardinality | Required | Translatable | Widget |
|-------------|----------------------|-------------|-------------|-------------|----------|--------------|--------|
| Variant     | field_button_variant | -           | List (text) | Single      | No       | No           | List   |
| Link        | field_link           | -           | Link        | Single      | Yes      | No           | Link   |

#### Card
The Card paragraph allows editors to display a bordered box around its content.
It includes optional fields like title, image, HTML text, metadata for 
headers/footer.

| Field label  | Field name  | Description | Field type             | Cardinality | Required | Translatable | Widget        |
|--------------|-------------|-------------|------------------------|-------------|----------|--------------|---------------|
| Title        | field_title | -           | Text                   | Single      | No       | Yes          | Text field    |
| Title link   | field_link  | -           | Link                   | Single      | No       | No           | Link only     |
| Text         | field_body  | -           | Text (formatted, long) | Single      | Yes      | Yes          | Text area     |
| Image        | field_media | -           | Media entity reference | Single      | Yes      | No           | Media library |
| Primary meta | field_meta  | -           | Text                   | Single      | No       | Yes          | Text field    |

#### Rich text
The Rich text paragraph adds an optional title field with body.

| Field label  | Field name  | Description | Field type             | Cardinality | Required | Translatable | Widget        |
|--------------|-------------|-------------|------------------------|-------------|----------|--------------|---------------|
| Title        | field_title | -           | Text                   | Single      | No       | Yes          | Text field    |
| Text         | field_body  | -           | Text (formatted, long) | Single      | Yes      | Yes          | Text area     |

#### Links block
The Links block paragraph displays a list of links.

| Field label | Field name  | Description | Field type | Cardinality | Required | Translatable | Widget     |
|-------------|-------------|-------------|------------|-------------|----------|--------------|------------|
| Title       | field_title | -           | Text       | Single      | No       | Yes          | Text field |
| Links       | field_links | -           | Link       | Multiple    | Yes      | No           | Link       |

#### Columns
The Columns paragraph allows editors to group multiple components(e.g.: Rich text,
Card) in one, two or more columns.

| Field label       | Field name              | Description          | Field type                 | Cardinality | Required | Translatable | Widget         |
|-------------------|-------------------------|----------------------|----------------------------|-------------|----------|--------------|----------------|
| Layout            | field_columns_layout    | -                    | List (text)                | Single      | Yes      | No           | Chosen/Similar |
| Number of columns | field_number_of_columns | -                    | List (text)                | Single      | Yes      | No           | Chosen/Similar |
| Items             | field_paragraphs        | List with components | Entity reference revisions | Multiple    | Yes      | No           | Paragraph      |

#### Content reference
The Content reference paragraph allows editors to group multiple and display
content reference in one, two or more columns.

| Field label       | Field name              | Description | Field type                 | Cardinality | Required | Translatable | Widget         |
|-------------------|-------------------------|-------------|----------------------------|-------------|----------|--------------|----------------|
| Title             | field_title             | -           | Text                       | Single      | No       | Yes          | Text field     |
| Number of columns | field_number_of_columns | -           | List (text)                | Single      | Yes      | No           | Chosen/Similar |
| Related content   | field_entity            | -           | Entity reference revisions | Multiple    | Yes      | No           | Paragraph      |

#### Quote
The Quote paragraph allows editors to add a quotation.

| Field label | Field name        | Description | Field type             | Cardinality | Required | Translatable | Widget     |
|-------------|-------------------|-------------|------------------------|-------------|----------|--------------|------------|
| Quote text  | field_description | -           | Text (formatted, long) | Single      | Yes      | Yes          | Text field |
| Attribution | field_title       | -           | Text                   | Single      | No       | Yes          | Text area  |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_base`