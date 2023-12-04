EDW Event Daily Schedule
=============================================

This module provides the Daily schedule paragraph.

Daily schedule can be used to break down multi-day events in activities taking 
place daily on a certain time. Content managers can assign room numbers where
activities take place.

#### Daily Schedule
| Field label | Field name       | Description              | Field type                 | Cardinality | Required | Translatable | Widget         |
|-------------|------------------|--------------------------|----------------------------|-------------|----------|--------------|----------------|
| Items       | field_paragraphs | List with schedule items | Entity reference revisions | Multiple    | Yes      | No           | Paragraph      |
| Date        | field_date       |                          | Date                       | Single      | Yes      | No           | HTML5 calendar |

#### Schedule item
| Field label | Field name  | Description | Field type | Cardinality | Required | Translatable | Widget            |
|-------------|-------------|-------------|------------|-------------|----------|--------------|-------------------|
| Title       | field_title |             | Text       | Single      | Yes      | No           | Text field        |
| Time        | field_time  |             | Time Range | Single      | Yes      | No           | Time Range Widget |
| Room        | field_room  |             | Text       | Single      | Yes      | No           | Text field        |

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main 
module documentation
2. Install `time_field` module using composer 
`composer require 'drupal/time_field:^2.1'`
3. Enable the module using drush: `drush en edw_event_daily_schedule`
4. If you already have an entity reference revisions field go to `Structure >
Content types > Meeting Section > Manage fields > YOUR FIELD` amd include 
`Daily schedule` in list with available paragraphs otherwise, create a new
`field_content` using the instruction from the `edw_event` module documentation.