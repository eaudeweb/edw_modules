## Accordion

The accordion component is a simple way to show and hide content.

### Component Properties

The Accordion component takes a variety of properties to customize its appearance and content:

Available properties:

- accordion_id (int) (default: accordion-random(1000))
- title (string) (default: '')
- title_tag (string) (default: 'h2')
- flush (boolean) (default: false)
- stay_open (boolean) (default: false)
- open_item_id (int) (default: 0)
- icon_position (none|left|right) (default: rigth)
- items (array) (default: []): format: [
  {
  title: (string),
  title_tag: (string),
  content: (block),
  },
  ]
- accordion_uc (array) (default: '')
- accordion_item_uc (array) (default: '')
- accordion_att (Drupal\Core\Template\Attribute)
- title_att (drupal attrs) (Drupal\Core\Template\Attribute)
- accordion_item_att (Drupal\Core\Template\Attribute)

`title` : The title of the accordion.
`title_tag` : The HTML tag to use for the title. Default is `h2`.
`title_link` : A link to wrap the title in.
`items` (required): An array of items to display in the accordion.
`accordion_item_utility_classes` : An array of utility classes to apply to each accordion item.
`accordion_utility_classes` : An array of classes to apply to the accordion.
`flush` : A boolean to determine if the accordion should have flush borders.
`open_item_id` : The id of the item to be open by default.
`id` : The id of the accordion.
`stay_open` : A boolean to determine if an accordion item should stay open when another item is opened. Default is `false`.

### Usage

**Example 1**: Simple Accordion usage

```twig
  {%
    include 'edw_paragraphs_accordion:accordion' with {
      items: [
        {
          title: 'Item 1',
          title_tag: 'h3',
          content: 'Content 1',
          stay_open: true,
        },
        {
          title: 'Item 2',
          title_tag: 'h3',
          content: 'Content 2',
        }
      ],
    }
  %}
```

```twig
  {%
    include 'edw_paragraphs_accordion:accordion' with {
      accordion_id: {{ content.id }}
      title: 'Yolo!',
      title_tag: 'h2',
      flush: true,
      open_item_id: 2,
      icon_position: 'left',
      accordion_uc: ['custom-class'],
      accordion_item_uc: ['custom-class'],
      items: [
        {
          title: 'Item 1',
          title_tag: 'h3',
          content: 'Content 1',
          stay_open: true,
        },
        {
          title: 'Item 2',
          title_tag: 'h3',
          content: 'Content 2',
        },
        {
          title: 'Item 3',
          title_tag: 'h3',
          content: 'Content 3',
        },
      ],
    }
  %}
```

**Example 2**: Advanced usage of Accordion usage in `views-view--unformatted`:

```twig
{% embed "edw_paragraphs_accordion:views-view--unformatted" %}
  {% block views_unformatted_rows %}
    {% set accordion_items = [] %}
    {% for row in rows %}
      {% set node = row.content['#node'] %}
      {% set node_title = node.getTitle() %}
      {% set node_body = node.body.processed %}
      {% set accordion_item = {
        title_tag: 'h3',
        title: node_title,
        content: node_body,
      } %}

      {% set accordion_items = accordion_items|merge([accordion_item]) %}
    {% endfor %}

    {%
      include 'edw_paragraphs_accordion:accordion' with {
        id: 'faq',
        items: accordion_items
      }
    %}
  {% endblock %}
{% endembed %}
```
