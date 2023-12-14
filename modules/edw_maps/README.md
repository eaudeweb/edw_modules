# EDW Mapbox Integration for Drupal

## Description
This module is designed to integrate Mapbox, a leading mapping platform, into your Drupal-powered websites.
This module provides a range of features to enhance your website with interactive and customizable maps using *views*.

**Note:** This module requires a _Mapbox account and API key_.

## Prerequisites
* Geofields on your contents of type latitue/logitude or Geofield WKT

## How to use

1. Enable the module.
2. Obtain a Mapbox API key [from your mapbox account](https://account.mapbox.com/), go to _/admin/config/system/edw_maps/settings_ and configure the module settings or add them in your _settings.local_ file:
```php
  $config['edw_maps.settings']['token'] = 'pk.xxxxxxxxxxxxx';
  $config['edw_maps.settings']['default_style_url'] = 'mapbox://styles/ccac-secretariat/cljcyeoxg000601plehvh667v';
```
3. Create a view block, select *EDW Mapbox Map* for format and *fields* for show.
4. Configure format settings: you need to select a GeoField as a source for pins/polygons display.
5. Easily embed maps into your Drupal content using a content block.

## How to style
1. To style markers use ``.edw-marker`` class in your css. You can choose a different icon or oen from the ``assets/icons`` folder:
```css
.edw-marker {
  background-image: url('mapbox-icon.png');
  background-size: cover;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  cursor: pointer;
}
```
2. To style clusters use ``.edw-cluster-marker`` class in your css.

## Altering tooltip data
You can display a rendered entity in a tooltip or any other field. You can alter the contents of those fields with  ``hook_preprocess_HOOK()`` or using ``edw_maps`` hooks:
* to alter pin popup data: ``hook_edw_maps_pin_tooltip_data_alter()``
* to alter country popup data: ``hook_edw_maps_country_tooltip_data_alter()``
* to alter area popup data: ``hook_edw_maps_area_tooltip_data_alter()``

## GEOFields examples
For pins - point:
- WKT format: ``POINT (10.0 51.0)``
- GeoJson format:
```JSON
{
  "type": "Feature",
  "geometry": {
    "type": "Point",
    "coordinates": [10.0, 51.0]
  },
  "properties": {}
}
```

For areas - polygon:
- WKT format: ``POLYGON ((73.4 42.1, 79.0 42.1, 79.0 35.8, 73.4 35.8, 73.4 42.1))``
- GeoJson format:
```JSON
  {
  "type": "Feature",
  "geometry": {
  "type": "Polygon",
  "coordinates": [
  [
  [73.4, 42.1],
  [79.0, 42.1],
  [79.0, 35.8],
  [73.4, 35.8],
  [73.4, 42.1]
  ]
  ]
  },
  "properties": {}
  }
```


## Key Features

1. **Interactive Maps:** Embed fully interactive maps on your Drupal site, allowing users to explore locations, zoom in/out, and interact with map features effortlessly.

2. **Custom Map Styles:** Leverage the flexibility of Mapbox's styling capabilities to create visually stunning and branded maps that match your website's design aesthetics.

3. **UN GIS support:** When displaying a map you can choose to render UN approved tiles: https://www.un.org/geospatial/mapsgeo/webservices. Keep in mind that "Carto Tile" cannot display pins.

4. **Dynamic Marker Placement:** Easily add markers to the map to highlight key points of interest. Customize markers with icons, colors, and pop-up information to convey valuable details.

5. **Marker clustering**

6. **Responsive Design:** Ensure a seamless experience across devices with responsive map designs that adapt to various screen sizes, enhancing usability for both desktop and mobile users.

7. **Drupal Configuration Integration:** Effortlessly configure and customize maps directly from the Drupal administration interface, providing administrators with a user-friendly experience.

8. **Open Source Compatibility:** Aligning with the principles of open-source development, this module integrates seamlessly with Drupal's ecosystem, fostering collaboration and community-driven improvements.


