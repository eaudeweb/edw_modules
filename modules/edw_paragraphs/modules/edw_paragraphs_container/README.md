# EDW Paragraphs Container

This module provides a paragraph Container useful when you want to store other components.

Using the "Layout Paragraphs"(https://www.drupal.org/project/layout_paragraphs) module, everything related to the container will be used from the module's settings.

#### Block Item

| Field label      | Field name             | Description | Field type       | Cardinality | Required | Translatable | Widget      |
| ---------------- | ---------------------- | ----------- | ---------------- | ----------- | -------- | ------------ | ----------- |
| Background color | field_background_color | -           | List (text)      | Single      | No       | No           | Select list |
| Background image | field_background_media | -           | Entity reference | Single      | No       | No           | Select list |

<!--
| Container size   | field_container_size   | -           | List (text)                | Single      | Yes      | No           | Select list |
| Content          | field_paragraphs       | -           | Entity reference revisions | Multiple    | Yes      | No           | Paragraph   |
| Hide on mobile   | field_hide_on_mobile   | -           | Boolean                    | Single      | No       | No           | Checkbox    |
-->

## Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_paragraphs_container`

### Info

It is based on class "grid-container"
Ex:

```pcss
.grid-container {
  --_cg-rows: 6;
  --_cg-mw: calc(var(--container-mw, 100%) - var(--container-py) * 2);
  --_cg-mi: 20px;
  --_cg-gap: 16px;
  --_cg-col_default: container;

  @media (--md) {
    --_cg-rows: 12;
    --_cg-gap: 40px;
    --_cg-mw: calc(var(--container-mw, 100%) - var(--container-py));
  }

  /* Calcul */
  --_calc_cg-mi: calc(var(--_cg-mi) - var(--_cg-gap));

  display: grid;
  grid-template-columns:
    [full-start] minmax(var(--_calc_cg-mi), 1fr)
    [container-start]
    repeat(
      var(--_cg-rows),
      calc(
        (var(--_cg-mw) - var(--_cg-gap) * (var(--_cg-rows) - 1)) / var(
            --_cg-rows
          )
      )
    )
    [container-end]
    minmax(var(--_calc_cg-mi), 1fr) [full-end];
  gap: var(--_cg-gap);

  & > * {
    grid-column: var(--_cg-col_default);
  }

  & > .full,
  & > *:has(.grid-container) {
    --_cg-col_default: full;
  }
}
```
