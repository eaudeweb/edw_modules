<?php

namespace Drupal\edw_maps\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Exception;
use geoPHP;

/**
 * The EDW MAPS data service.
 */
class EdwMapsDataService {

  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The module handler to invoke hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected ModuleHandler $moduleHandler;

  /**
   * The EdwMapsDataService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Renderer $renderer, ModuleHandler $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Get data objects for rendering markers on mapbox.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param string $dataSource
   *   The data source field id.
   * @param string $popupSource
   *   The popup source field id.
   *
   * @return array
   *   The formatted pin data.
   */
  public function getPinData(ViewExecutable $view, string $dataSource, string $popupSource) {
    $rows = $view->result;
    $data = [];
    foreach ($rows as $row) {
      $entity = $this->getEntity($row, $dataSource);
      $coordinates = $entity->get($dataSource)->getValue();
      $coordinates = reset($coordinates);
      if (empty($coordinates) || $coordinates['geo_type'] !== 'Point') {
        continue;
      }
      // Mapbox uses lng - lat format.
      $pinCoordinates = [$coordinates['lon'], $coordinates['lat']];
      $data[] = [
        'coordinates' => $pinCoordinates,
        'popup' => $this->getPopupContent($view, $row, $popupSource, 'pin'),
      ];
    }

    return $data;
  }

  /**
   * Get data objects for highlighting country polygons on mapbox.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param string $dataSource
   *   The data source field id.
   * @param string $popupSource
   *   The popup source field id.
   *
   * @return array
   *   The formatted country data.
   */
  public function getCountryData(ViewExecutable $view, string $dataSource, string $popupSource, string $linkSource) {
    $rows = $view->result;
    $data = [];
    foreach ($rows as $row) {
      $entity = $this->getEntity($row, $dataSource);
      $iso3 = $entity->get($dataSource)->value;
      if (empty($iso3)) {
        continue;
      }
      $data[] = [
        'iso3' => $entity->get($dataSource)->value,
        'popup' => $this->getPopupContent($view, $row, $popupSource, 'country'),
        'link' => $this->getRedirectLink($row, $linkSource),
      ];
    }

    return $data;
  }

  /**
   * Get data objects for rendering area polygons on mapbox.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param string $dataSource
   *   The data source field id.
   * @param string $popupSource
   *   The popup source field id.
   *
   * @return array
   *   The formatted area data.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function getAreaData(ViewExecutable $view, string $dataSource, string $popupSource) {
    $rows = $view->result;
    $data = [
      'type' => 'FeatureCollection',
      'features' => [],
    ];
    foreach ($rows as $row) {
      $entity = $this->getEntity($row, $dataSource);
      $coordinates = $entity->get($dataSource)->getValue();
      $coordinates = reset($coordinates);
      if (empty($coordinates) || $coordinates['geo_type'] !== 'Polygon') {
        continue;
      }
      $wktPolygon = $entity->get($dataSource)->value;
      try {
        $geometry = GeoPHP::load($wktPolygon, 'wkt');
        // Create a GeoJSON feature.
        $geoJsonGeometry = json_decode($geometry->out('json'), TRUE);

        // Create the GeoJSON feature structure.
        $geoJsonFeature = [
          'type' => 'Feature',
          'geometry' => $geoJsonGeometry,
          'properties' => [
            'id' => $entity->id(),
            'popup' => $this->getPopupContent($view, $row, $popupSource, 'area'),
          ],
        ];

        $data['features'][] = $geoJsonFeature;
      }
      catch (\exception) {
        continue;
      }
    }

    return $data;
  }

  /**
   * Gets url for GeoJson boundaries for clear map.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   The absolute url for the GeoJson.
   */
  public function getClearMapSource() {
    $path = $this->moduleHandler->getModule('edw_maps')
        ->getPath() . '/assets/country_boundaries/country_polygon.geojson';
    if (!file_exists($path)) {
      $this->unzipGeoJson();
    }
    return Url::fromUserInput("/$path", ['absolute' => TRUE])
      ->toString();
  }

  /**
   * Unzips geoJson file with country borders for clear map.
   */
  public function unzipGeoJson() {
    $path = $this->moduleHandler->getModule('edw_maps')
        ->getPath() . '/assets/country_boundaries';
    try {
      $zip = new \ZipArchive();
      $res = $zip->open("$path/country_polygon.zip");
      if ($res === TRUE) {
        $zip->extractTo($path);
        $zip->close();
      }
    } catch (Exception $e) {
      $this->messenger()->addWarning('Could not extract geoJson file. Error message ' . $e->getMessage());

    }
  }

  /**
   * Renders popup content after calling altering hooks.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\views\ResultRow $row
   *   The current row.
   * @param string $popupSource
   *   The field id of the popup source.
   * @param string $renderItem
   *   The name of the render item. One of pin, area or country,.
   *
   * @return callable|\Drupal\Component\Render\MarkupInterface|mixed|void|null
   *   The rendered item or null.
   */
  protected function getPopupContent(ViewExecutable $view, ResultRow $row, string $popupSource, string $renderItem) {
    if (empty($popupSource) || $popupSource == '_none') {
      return NULL;
    }
    $renderValue = $view->field[$popupSource]->render($row);
    if (!is_array($renderValue)) {
      try {
        return $view->field[$popupSource]->advancedRender($row);
      }
      catch (\LogicException) {
        return $view->field[$popupSource]->render($row)
          ->__toString();
      }
    }

    $this->moduleHandler->invokeAll('edw_maps_' . $renderItem . '_tooltip_data_alter', [&$renderValue]);
    return $this->renderer->renderPlain($renderValue);
  }

  /**
   * Gets entity with the field id form view or relationships.
   *
   * @param \Drupal\views\ResultRow $row
   *   The view's row.
   * @param string $fieldId
   *   The field id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity.
   */
  protected function getEntity(ResultRow $row, string $fieldId) {
    $entity = $row->_entity;
    $relationShipEntities = $row->_relationship_entities;
    if ($entity->hasField($fieldId)) {
      return $entity;
    }

    foreach ($relationShipEntities as $relationShipEntity) {
      if ($relationShipEntity->hasField($fieldId)) {
        return $relationShipEntity;
      }
    }

    return $entity;
  }

  /**
   * Gets link to redirect to when clicking on a territory.
   *
   * @param \Drupal\views\ResultRow $row
   *   The current row.
   * @param string $linkSource
   *   The link's source.
   *
   * @return string|null
   *   The url if it exists, otherwise null.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getRedirectLink(ResultRow $row, string $linkSource) {
    if (empty($linkSource) || $linkSource == '_none') {
      return NULL;
    }

    if ($linkSource == 'default') {
      $entity = $row->_entity;
      if (empty($entity)) {
        return NULL;
      }
      return $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    }

    $entity = $this->getEntity($row, $linkSource);
    $link = $entity->get($linkSource)->getValue();
    $link = reset($link);
    if (!empty($link)) {
      // Convert internal links to absolute links.
      $url = Url::fromUri($link['uri']);
      if ($url->isRouted()) {
        return $url->setAbsolute()->toString();
      }
      return $link['uri'];
    }

    return NULL;
  }

}
