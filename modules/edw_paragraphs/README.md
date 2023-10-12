EDW Paragraphs
=============================================

This module provides a number of Paragraph types.

## Architecture

The module provides the following paragraph types:

```php
├── edw_paragraphs
    ├── edw_paragraphs_accordion
    ├── edw_paragraphs_banner
    ├── edw_paragraphs_base
    ├── edw_paragraphs_carousel
    ├── edw_paragraphs_document
    ├── edw_paragraphs_facts_figures
    ├── edw_paragraphs_gallery
    ├── edw_paragraphs_heading
    ├── edw_paragraphs_media
    └── edw_paragraphs_timeline
```

## Installation

Go to Manage form display and change widget for field `field_content` to **Paragraphs with widgets** (extends FieldWidget _Paragraphs (stable)_).

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs`

## Sub-modules

The following submodules can be used to extend the event functionality. They can also be used independently to attach to
other entities.

### Base
- **Button**: The Button paragraph allows editors to insert a link in different
variants.
- **Card**: The Card paragraph allows editors to display a bordered box around
its content. It includes optional fields like title, image, HTML text, metadata
for headers/footer.
- **HTML**: The Rich text paragraph adds an optional title field with body.
- **Links block**: The Links block paragraph displays a list of links.
- **Listing item blocks**: The Listing item block paragraph allows editors to group
multiple components (e.g.: HTML, Card, Document) in one, two or more columns.
- **Quote**: The Quote paragraph allows editors to add a quotation.

### Accordion
The paragraph shows a collection of collapsible items. To get this paragraph
type enable the EDW Paragraphs Accordion submodule.

### Banner
Provides Banner paragraph that display a prominent message and related action.
To get this paragraph type enable the EDW Paragraphs Banner submodule.

### Carousel
Provides Carousel paragraph that display items similar to a Banner with
multiple slides. To get this paragraph type enable the EDW Paragraphs Carousel
submodule.

### Document
The Document paragraph allows editors to render documents. To get this paragraph
type enable the EDW Paragraphs Carousel submodule.

### Facts and figures
Provides Facts and figures paragraph to display numerical representations of
facts that are easier to portray visually through the use of statistics. To get
this paragraph type enable the EDW Paragraphs Facts and figures submodule.

### Gallery
This module provides a paragraph type that displays a gallery of media entities.
To get this paragraph type enable the EDW Paragraphs Gallery submodule.

### Heading
This module provides a paragraph type that displays headings (h2-h6). To get
this paragraph type enable the EDW Paragraphs Heading submodule.

### Text with Featured media
This module provides the Text with Featured media paragraph. To get this
paragraph type enable the EDW Paragraphs Media submodule.

### Timeline
Provides Timeline paragraph that displays items visually on a time axis. To get
this paragraph type enable the EDW Paragraphs Gallery submodule.