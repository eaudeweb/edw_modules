<?php

namespace Drupal\edw_maps\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use geoPHP;

/**
 * The EDW MAPS data service.
 */
class EdwMapsDataService {

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
  private Renderer $renderer;

  /**
   * The module handler to invoke hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  private ModuleHandler $moduleHandler;

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
   * @param ViewExecutable $view
   *   The view.
   * @param string $dataSource
   *   The data source field id.
   * @param string $popupSource
   *   The popup source field id.
   *
   * @return array
   *   The formatted pin data.
   *
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
      $popupInfo = NULL;
      if (!empty($popupSource)) {
        $popupInfo = $view->field[$popupSource]->AdvancedRender($row)
          ->__toString();
      }
      $data[] = [
        'coordinates' => $pinCoordinates,
        'popup' => $popupInfo,
      ];
    }

    return $data;
  }

  /**
   * Get data objects for highlighting country polygons on mapbox.
   *
   * @param ViewExecutable $view
   *   The view.
   * @param string $dataSource
   *   The data source field id.
   * @param string $popupSource
   *   The popup source field id.
   *
   * @return array
   *   The formatted country data.
   *
   */
  public function getCountryData(ViewExecutable $view, string $dataSource, string $popupSource) {
    $rows = $view->result;
    $data = [];
    foreach ($rows as $row) {
      $entity = $this->getEntity($row, $dataSource);
      $popupInfo = NULL;
      if (!empty($popupSource)) {
        $popupInfo = $view->field[$popupSource]->AdvancedRender($row)
          ->__toString();
      }
      $data[] = [
        'iso3' => $entity->get($dataSource)->value,
        'popup' => $popupInfo,
      ];
    }

    return $data;
  }

  /**
   * Get data objects for rendering area polygons on mapbox.
   *
   * @param ViewExecutable $view
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
    $data = [];
    foreach ($rows as $row) {
      $entity = $this->getEntity($row, $dataSource);
      $popupInfo = NULL;
      if (!empty($popupSource)) {
        $popupInfo = $view->field[$popupSource]->AdvancedRender($row)
          ->__toString();
      }

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
            'popup' => $popupInfo,
          ],
        ];

        $data['type'] = 'FeatureCollection';
        $data['features'][] = $geoJsonFeature;
      } catch (\exception $e) {
        continue;
      }
    }

    return $data;
  }

  /**
   * Gets url for geojson boundaries for clear map.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   The absolute url for the geojson.
   */
  public function getClearMapSource() {
    // GeoJson file is too big for GitHub.
    $path = $this->moduleHandler->getModule('edw_maps')
        ->getPath() . '/assets/country_boundaries';
    if (!file_exists("/$path/country_polygon.geojso")) {
      $zip = new \ZipArchive();
      $res = $zip->open("$path/country_polygon.zip");
      if ($res !== TRUE) {
        return NULL;
      }
      $zip->extractTo($path);
      $zip->close();
    }
    return Url::fromUserInput("/$path/country_polygon.geojson", ['absolute' => TRUE])
      ->toString();
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
  private function getEntity(ResultRow $row, string $fieldId) {
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

}
