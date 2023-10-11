# Events module

Enable the event module to provide content managers with the ability to manage events within Drupal.

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_event`

## Architecture

Node types:
- `event` - Main node type which represents a single calendar event.
- `event_section` - The main event has a single default section which is the node page. Using this children entity, the
content manager can create additional sections to a meeting (sometimes are called tabs or pages).

### Fields

#### event

| Field label  | Field name        | Description                                                                                                            | Field type | Cardinality | Required    | Translatable | Widget         |
|--------------|-------------------|------------------------------------------------------------------------------------------------------------------------|------------|-------------|-------------|--------------|----------------|
| Title        | title             | -                                                                                                                      | Text       | Single      | Yes         | Yes          | Text field     |
| Number       | field_number      | Meeting number (use it to order events)                                                                                | Integer    | Single      | Yes         | Yes          | Text field     |
| Abbreviation | field_event_abbr  | Meeting short title (e.g. MOP 24)                                                                                      | Short text | Single      | No          | No           | Text field     |
| Date         | field_date_range  |                                                                                                                        | Date range | Single      | Yes (start) | No           | HTML5 calendar |
| Date notes   | field_date_notes  |                                                                                                                        | Text       | Single      | Yes         | No           | Text input     |
| Hide date    | field_hide_date   | Every meeting must have a date, but if you don't know the dates, set one and check this box to hide it from the public | Boolean    | Single      | No          | No           | Checkbox       |

TODO

#### event_section

| Field label | Field name       | Description                                                           | Field type | Cardinality | Required | Translatable      | Widget      |
|-------------|------------------|-----------------------------------------------------------------------|------------|-------------|----------|-------------------|-------------|
| Title       | title            | -                                                                     | Text       | Single      | Yes      | Yes               | Text field  |
| Hide        | field_hide       | Hide from public view (only authenticated users can see this section) | Boolean    | Single      | No       | No                | Checkbox    |

### Taxonomies

None

### Paragraphs

TODO (add links etc.): Use the `edw_paragraphs` module to enable different visual components that can be added to the meeting sections.

## Functionalities

The following functionalities are provided out of the box:

1. Multilingual content
2. **custom block** to show automatically the children tabs. When user is clicking on a tab element the appropriate
tab page is selected, highlighted and its content is presented to the user. There is a button to create new tab page
which appears when the user has the appropriate permission.
3. TODO
4. TODO: (not implemented) Import a calendar entry from ICS file 

## Sub-modules

The following submodules can be used to extend the event functionality. They can also be used independently to attach to
other entities.

### edw_event_agenda

Event agenda can be used to structure a general list of topics to be discussed during an event. For further information
check the README located inside the module.

### edw_event_daily_schedule

Daily schedule can be used to break down multi-day events in activities taking place daily on a certain time. Content
managers can assign room numbers where activities take place.