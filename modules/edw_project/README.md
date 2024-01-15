# Project module

Enable the project module to provide content managers with the ability to manage projects within Drupal.

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_project`

### Fields

#### event

| Field label      | Field name                     | Description                                | Field type             | Cardinality | Required | Translatable | Widget         |
|------------------|--------------------------------|--------------------------------------------|------------------------|-------------|----------|--------------|----------------|
| Title            | title                          |                                            | Text                   | Single      | Yes      | Yes          | Text field     |
| Project type     | field_project_types            |                                            | TODO                   | Multiple    | No       | No           | TODO           |
| Project Date     | field_date                     |                                            | Date                   | Single      | Yes      | No           | HTML5 calendar |
| Countries        | field_countries                | Taxonomy term entity reference (Countries) | Entity reference       | Single      | No       | No           | Select         |
| Image            | field_image                    |                                            | Media entity reference | Single      | Yes      | No           | Media library  |
| Organization     | field_organisations            |                                            | TODO                   | Multiple    | No       | No           | TODO           |
| Related projects | field_entities                 |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Project URL      | field_project_url              |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Project Status   | field_project_status           |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Short Title      | field_short_title              |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Expected outcome | field_project_expected_outcome |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Budget           | field_project_budget           |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Date of approval | field_project_approved_date    |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |
| Date end         | field_date_end                 |                                            | TODO                   | TODO        | TODO     | TODO         | TODO           |

TODO

### Taxonomies

None

### Paragraphs

TODO (add links etc.): Use the `edw_paragraphs` module to enable different visual components that can be added to the meeting sections.

## Functionalities

The following functionalities are provided out of the box:

1. Multilingual content.
2. View block for Featured project.