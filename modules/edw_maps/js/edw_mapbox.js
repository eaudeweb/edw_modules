(function ($, Drupal, debounce, drupalSettings) {

        Drupal.behaviors.edw_map = {
            attach: function (context, settings) {
                $(function () {
                        const mapboxStyleUrl = drupalSettings.edw_map['mapboxStyleUrl'];
                        const mapType = drupalSettings.edw_map['mapType'];
                        const containerId = drupalSettings.edw_map['containerId'];
                        const renderPins = drupalSettings.edw_map['renderPins'];
                        const renderClusters = drupalSettings.edw_map['renderClusters'];
                        const renderCountries = drupalSettings.edw_map['renderCountries'];
                        const renderAreas = drupalSettings.edw_map['renderAreas'];
                        const pinData = drupalSettings.edw_map['pinData'];
                        const countryData = drupalSettings.edw_map['countryData'];
                        const areaData = drupalSettings.edw_map['areaData'];
                        const countryColor = drupalSettings.edw_map['countryColor'];
                        const areaColor = drupalSettings.edw_map['areaColor'];
                        const clearMapSource = drupalSettings.edw_map['clearMapSource'];
                        const baseCountryCarto = ['rgb', 237, 237, 237];
                        const lineCarto = ['rgb', 165, 165, 165];

                        // True when map finished rendering clusters.
                        let ready = false;
                        let clusters = [];
                        let markers = [];
                        let featurePoints = [];
                        let hoveredStateId = null;
                        let currentPopup = null;
                        let sourceLayer = mapType === 'custom' ? 'country_boundaries' : '';
                        if (mapType === 'carto_tile') {
                            sourceLayer = 'bnda';
                        }

                        // Render map only once.
                        if (!$(`#${containerId}:empty`).length) {
                            return;
                        }

                        exposedForm();

                        // Set access token.
                        mapboxgl.accessToken = drupalSettings.edw_map['mapboxToken'];

                        // Create map.
                        const map = new mapboxgl.Map({
                            container: containerId, // container ID
                            style: getMapStyle(), // map style url
                            center: drupalSettings.edw_map['center'], // starting position
                            zoom: drupalSettings.edw_map['zoom'], // starting zoom
                            maxZoom: mapType === 'clear_map' ? 5 : undefined,
                            pitch: drupalSettings.edw_map['pitch'], // angle towards the horizon,
                            cooperativeGestures: true,
                            renderWorldCopies: drupalSettings.edw_map['worldCopies'],
                            projection: drupalSettings.edw_map['projection']
                        });

                        if (drupalSettings.edw_map['disableScrollZoom']) {
                            map.scrollZoom.disable();
                        }

                        // Create navigation control.
                        const nav = new mapboxgl.NavigationControl({
                            showCompass: false
                        });
                        map.addControl(nav, 'bottom-left');

                        // Get feature points for cluster rendering.
                        if (renderPins && renderClusters) {
                            getPointsCoordinates();
                        }

                        // Load country boundaries layer.
                        map.on('load', () => {
                            addBoundariesSources();
                            addMapExtraLayers();
                            draw();

                            map.getCanvas().style.cursor = 'default';
                        });

                        // Adds close button on exposed form.
                        function exposedForm() {
                            const form = $('.exposed-mapbox-filters');
                            const filtersBtn = $('#toggleFiltersForm');
                            if (form.length === 0) {
                                return;
                            }
                            const closeButton = $('<button>', {
                                class: 'close-button',
                                text: 'X',
                                click: function (ev) {
                                    ev.preventDefault();
                                    filtersBtn.toggleClass('button-visible');
                                    form.toggleClass('form-visible');
                                }
                            });
                            form.prepend(closeButton);

                            filtersBtn.on('click', function () {
                                $(this).toggleClass('button-visible');
                                form.toggleClass('form-visible');
                            });
                        }

                        // Adds source layers for country boundaries/areas.
                        function addBoundariesSources() {
                            if (mapType === 'custom') {
                                map.addSource('boundaries', {
                                    type: 'vector',
                                    url: 'mapbox://mapbox.country-boundaries-v1',
                                });
                            }

                            if (mapType === 'clear_map') {
                                map.addSource('boundaries', {
                                    type: 'geojson',
                                    data: clearMapSource
                                });
                            }

                            if (renderAreas && areaData.features) {
                                // Add area GeoJson source.
                                let geoJson = JSON.parse(JSON.stringify(areaData));
                                map.addSource('areas', {
                                    type: 'geojson',
                                    data: geoJson
                                });
                            }
                        }

                        // Ads layers used for highlighting countries/areas.
                        function addMapExtraLayers() {
                            let darkerCountryColor = lightenColor(countryColor, 20);
                            let darkerAreaColor = lightenColor(areaColor, 20);
                            let baseLayer = null;

                            switch (mapType) {
                                case 'carto_tile':
                                    baseLayer = 'bndl_solid';
                                    break;
                                case 'custom':
                                    baseLayer = 'admin-0-boundary';
                                    break;
                            }

                            if (renderCountries) {
                                map.addLayer(
                                    {
                                        id: 'country-boundaries',
                                        source: 'boundaries',
                                        'source-layer': sourceLayer,
                                        type: 'fill',
                                        paint: {
                                            'fill-color': [ // Colors state borders.
                                                'case',
                                                ['boolean', ['feature-state', 'hover'], false],
                                                '#' + darkerCountryColor,
                                                '#' + countryColor,
                                            ],
                                            'fill-outline-color': '#' + darkerCountryColor,
                                            'fill-opacity': 0,
                                        },
                                    },
                                    baseLayer
                                );

                                if (mapType === 'carto_tile') {
                                    // Highlight layer for carto tile map.
                                    map.addLayer(
                                        {
                                            id: 'country-boundaries-highlight',
                                            source: 'boundaries',
                                            'source-layer': sourceLayer,
                                            type: 'fill',
                                            paint: {
                                                'fill-color': '#' + darkerCountryColor,
                                                'fill-opacity': 0,
                                            },
                                        },
                                        baseLayer
                                    );
                                }
                            }

                            if (renderAreas && areaData.features) {
                                // Add area extra layers.
                                map.addLayer({
                                        'id': 'areas-layer',
                                        'type': 'fill',
                                        'source': 'areas',
                                        'paint': {
                                            'fill-color': '#' + areaColor,
                                            'fill-outline-color': '#' + areaColor,
                                            'fill-opacity': mapType === 'clear_map' ? 0.8 : 1,
                                        }
                                    },
                                    baseLayer);

                                map.addLayer({
                                        'id': 'areas-highlight-layer',
                                        'type': 'fill',
                                        'source': 'areas',
                                        'paint': {
                                            'fill-color': '#' + darkerAreaColor,
                                            'fill-outline-color': '#' + darkerAreaColor,
                                            'fill-opacity': 0,
                                        }
                                    },
                                    baseLayer);
                            }
                        }

                        // Draws polygons/markers on map
                        function draw() {
                            if (renderCountries) {
                                drawCountries();
                            }

                            if (renderAreas && areaData.features) {
                                addAreasHandlers();
                            }

                            if (renderPins && renderClusters) {
                                drawClusters();
                                map.on('moveend', updateMap);
                            } else if (renderPins && !renderClusters) {
                                drawMarkers();
                            }
                        }

                        // Gets points data as features for clustering.
                        function getPointsCoordinates() {
                            pinData.map(data => {
                                featurePoints.push({
                                    type: "Feature",
                                    properties: {cluster: false},
                                    geometry: {
                                        type: "Point",
                                        coordinates: data.coordinates,
                                    },
                                    data: data
                                });
                            })
                        }

                        // Function to redraw clusters after zooming/scrolling.
                        function updateMap() {
                            if (!ready) return;
                            removeMarkers();
                            drawClusters();
                        }

                        // Draws markers.
                        function drawMarkers() {
                            pinData.map(data => {
                                createMarker(data);
                            })
                        }

                        // Creates and adds to map a new marker element.
                        function createMarker(data, coordinates = null) {
                            if (coordinates === null) {
                                coordinates = data.coordinates;
                            }
                            const el = document.createElement('div');
                            el.className = 'edw-marker';
                            // Markers are rendered on the top of everything.
                            let marker = new mapboxgl.Marker(el).setLngLat(coordinates);
                            if (data.popup) {
                                let popup = new mapboxgl.Popup()
                                    .setLngLat(coordinates)
                                    .setHTML(data.popup);
                                marker.getElement().onclick = (ev) => {
                                    // Close last opened popup.
                                    if (currentPopup) {
                                        currentPopup.remove();
                                        currentPopup = null;
                                    }
                                    popup.addTo(map);
                                    currentPopup = popup;
                                    // Don't send event to the other layers.
                                    ev.stopPropagation();
                                }
                            }

                            marker.addTo(map);
                            markers.push(marker);
                        }

                        // Creates and adds to map a new cluster element.
                        function createCluster(supercluster, cluster, mapZoom) {
                            const coordinates = cluster.geometry.coordinates;
                            const abbreviatedPoints = cluster.properties.point_count;
                            let clusterMarker = document.createElement('div');
                            clusterMarker.innerHTML = "<span class=\"edw-cluster-marker\">" + abbreviatedPoints + "</span>";
                            clusterMarker.style.cursor = 'pointer';
                            clusterMarker.style.width = '40px';
                            clusterMarker.style.height = '40px';
                            clusterMarker.style.backgroundSize = '100%';

                            let nextZoomLevel = supercluster.getClusterExpansionZoom(cluster.properties.cluster_id, mapZoom) * 1.2;
                            clusterMarker.addEventListener("click", (ev) => {
                                map.flyTo({
                                    center: coordinates,
                                    essential: true,
                                    zoom: nextZoomLevel
                                });
                                ev.stopPropagation();
                            })

                            let clusterMarket = new mapboxgl.Marker(clusterMarker)
                                .setLngLat(coordinates)
                            clusters.push(clusterMarket);
                            clusterMarket.addTo(map)
                        }

                        // Draws clusters.
                        function drawClusters() {
                            if (pinData.length === 0) {
                                return;
                            }

                            const supercluster = new Supercluster({
                                maxZoom: mapType === 'clear_map' ? 4 : 16,
                            });
                            supercluster.load(featurePoints);

                            const bounds = map.getBounds();
                            const mapBounds = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
                            const mapZoom = Math.floor(map.getZoom());
                            let clusters = supercluster.getClusters(mapBounds, mapZoom);

                            clusters.map((cluster) => {
                                // The point may be either a cluster or a marker.
                                const isCluster = cluster.properties.cluster;
                                if (isCluster) {
                                    // Render a cluster, expand it at click.
                                    createCluster(supercluster, cluster, mapZoom);
                                } else {
                                    // Render a pin.
                                    createMarker(cluster.data, cluster.geometry.coordinates);
                                }
                            });
                            ready = true;
                        }

                        // Removes clusters and pins.
                        function removeMarkers() {
                            clusters.forEach(marker => marker.remove());
                            clusters = [];
                            markers.forEach(marker => marker.remove());
                            markers = [];
                        }

                        // Highlight country borders on map.
                        function drawCountries() {
                            let opacity = mapType === 'clear_map' ? 0.8 : 1;
                            let property = mapType === 'custom' ? 'iso_3166_1_alpha_3' : 'ISO3CD';
                            let iso3codes = countryData.map(data => data.iso3);
                            iso3codes.unshift('in', property);
                            map.setFilter('country-boundaries', iso3codes);
                            map.setPaintProperty('country-boundaries', 'fill-opacity', opacity);
                            if (mapType !== 'carto_tile') {
                                // Features on the carto-tile map don't have ids, so state can't be set on layer.
                                map.on('mousemove', 'country-boundaries', colorCountry);
                                map.on('mouseleave', 'country-boundaries', hoverOutCountryCursor);
                            } else {
                                cartotileMoseEvents();
                            }
                            map.on('click', 'country-boundaries', openCountryPopup);
                        }

                        // Open highlighted country popup at click.
                        function openCountryPopup(e) {
                            let iso3Code = e.features[0].properties.ISO3CD;
                            if (mapType === 'custom') {
                                iso3Code = e.features[0].properties.iso_3166_1_alpha_3;
                            }

                            let data = countryData.find(data => data.iso3 === iso3Code);
                            if (data.popup) {
                                if (currentPopup) {
                                    currentPopup.remove();
                                    currentPopup = null;
                                }

                                currentPopup = new mapboxgl.Popup()
                                    .setLngLat(e.lngLat)
                                    .setHTML(data.popup)
                                    .addTo(map);
                            }
                        }

                        // Color country at hover.
                        function colorCountry(e) {
                            let iso3Code = e.features[0].properties.ISO3CD;
                            if (mapType === 'custom') {
                                iso3Code = e.features[0].properties.iso_3166_1_alpha_3;
                            }

                            let data = countryData.find(data => data.iso3 === iso3Code);
                            if (e.features.length === 0 || data === undefined || !data.popup) {
                                return;
                            }
                            map.getCanvas().style.cursor = 'pointer';
                            if (hoveredStateId !== null) {
                                map.setFeatureState(
                                    {source: 'boundaries', sourceLayer: sourceLayer, id: hoveredStateId},
                                    {hover: false}
                                );
                            }
                            hoveredStateId = e.features[0].id;
                            map.setFeatureState(
                                {source: 'boundaries', sourceLayer: sourceLayer, id: hoveredStateId},
                                {hover: true}
                            );
                        }

                        function hoverOutCountryCursor() {
                            map.getCanvas().style.cursor = 'default';
                            if (hoveredStateId !== null) {
                                map.setFeatureState(
                                    {source: 'boundaries', sourceLayer: sourceLayer, id: hoveredStateId},
                                    {hover: false}
                                );
                            }
                            hoveredStateId = null;
                        }

                        // Sets mose events on a new layer because carto-tile features have no id.
                        function cartotileMoseEvents() {
                            map.on('mousemove', 'country-boundaries', (e) => {
                                let iso3Code = e.features[0].properties.ISO3CD;
                                let data = countryData.find(data => data.iso3 === iso3Code);
                                if (data === undefined || !data.popup) {
                                    return;
                                }
                                map.getCanvas().style.cursor = 'pointer';
                                map.setFilter('country-boundaries-highlight', ['==', ['get', 'ISO3CD'], iso3Code]);
                                map.setPaintProperty('country-boundaries-highlight', 'fill-opacity', 1);
                            });

                            map.on('mouseleave', 'country-boundaries-highlight', () => {
                                map.getCanvas().style.cursor = 'default';
                                map.setPaintProperty('country-boundaries-highlight', 'fill-opacity', 0);
                            })
                        }

                        // Adds handlers for areas layer.
                        function addAreasHandlers() {
                            map.on('mousemove', 'areas-layer', (e) => {
                                let opacity = mapType === 'clear_map' ? 0.8 : 1;
                                let popup = e.features[0].properties.popup;
                                let id = e.features[0].properties.id;
                                if (!popup) {
                                    return;
                                }
                                map.setFilter('areas-highlight-layer', ['==', ['get', 'id'], id]);
                                map.setPaintProperty('areas-highlight-layer', 'fill-opacity', opacity);
                                map.getCanvas().style.cursor = 'pointer';
                                e.originalEvent.stopPropagation();
                            });

                            map.on('mouseleave', 'areas-highlight-layer', () => {
                                map.getCanvas().style.cursor = 'default';
                                map.setPaintProperty('areas-highlight-layer', 'fill-opacity', 0);
                            })

                            map.on('click', 'areas-layer', (e) => {
                                let popup = e.features[0].properties.popup;
                                if (popup) {
                                    if (currentPopup) {
                                        currentPopup.remove();
                                        currentPopup = null;
                                    }
                                    currentPopup = new mapboxgl.Popup()
                                        .setLngLat(e.lngLat)
                                        .setHTML(popup)
                                        .addTo(map);
                                }
                                e.originalEvent.stopPropagation();
                            });
                        }

                        // Sets base style depending on map type.
                        function getMapStyle() {
                            if (mapType === 'clear_map') {
                                return {
                                    version: 8,
                                    sources: {
                                        'clear-map-source': {
                                            type: 'raster',
                                            tiles: ['https://geoservices.un.org/arcgis/rest/services/ClearMap_WebTopo/MapServer/tile/{z}/{y}/{x}'],
                                            tileSize: 256,
                                            minzoom: 0,
                                            maxzoom: 18
                                        }
                                    },
                                    layers: [{
                                        id: 'clear-map-layer',
                                        type: 'raster',
                                        source: 'clear-map-source',
                                        minzoom: 0,
                                        maxzoom: 18
                                    }]
                                };
                            }

                            if (mapType === 'carto_tile') {
                                return {
                                    version: 8,
                                    sources: {
                                        'boundaries': {
                                            type: 'vector',
                                            tiles: ['https://UN-Geospatial.github.io/cartotile-plain-design/data/cartotile_v01/{z}/{x}/{y}.pbf'],
                                            attribution: '<table><tr><td style="font-size: 7pt; line-height: 100%">The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.​ Final boundary between the Republic of Sudan and the Republic of South Sudan has not yet been determined.​<br>* Non-Self Governing Territories<br>** Dotted line represents approximately the Line of Control in Jammu and Kashmir agreed upon by India and Pakistan. The final status of Jammu and Kashmir has not yet been agreed upon by the parties.​<br>*** A dispute exists between the Governments of Argentina and the United Kingdom of Great Britain and Northern Ireland concerning sovereignty over the Falkland Islands (Malvinas).</td><td  style="font-size: 5pt; color: #009EDB" valign="bottom">Powered by<br><img src="https://unopengis.github.io/watermark/watermark.png" alt="UN OpenGIS logo" width="50" height="50"></td></tr></table>',
                                            maxzoom: 2,
                                            minzoom: 0
                                        }
                                    },
                                    glyphs: 'https://UN-Geospatial.github.io/cartotile-plain-design/font/{fontstack}/{range}.pbf',
                                    transition: {
                                        duration: 0,
                                        delay: 0
                                    },
                                    layers: [
                                        {
                                            id: 'background',
                                            type: 'background',
                                            layout: {'visibility': 'visible'},
                                            paint: {
                                                'background-color': baseCountryCarto
                                            }
                                        },
                                        {
                                            id: 'bnda',
                                            type: 'fill',
                                            source: 'boundaries',
                                            'source-layer': 'bnda',
                                            maxzoom: 4,
                                            minzoom: 0,
                                            filter: [
                                                'none',
                                                ['==', 'ISO3CD', 'ATA']
                                            ],
                                            paint: {
                                                'fill-color': lineCarto
                                            }
                                        },
                                        {
                                            id: 'bndl_solid',
                                            type: 'line',
                                            source: 'boundaries',
                                            'source-layer': 'bndl',
                                            maxzoom: 4,
                                            minzoom: 0,
                                            filter: [
                                                'any',
                                                ['==', 'BDYTYP', 1],
                                                ['==', 'BDYTYP', 0],
                                                ['==', 'BDYTYP', 2]
                                            ],
                                            paint: {
                                                'line-color': baseCountryCarto,
                                                'line-width': 0.8
                                            }
                                        },
                                        {
                                            id: 'bndl_dashed',
                                            type: 'line',
                                            source: 'boundaries',
                                            'source-layer': 'bndl',
                                            maxzoom: 4,
                                            minzoom: 0,
                                            filter: [
                                                'all',
                                                ['==', 'BDYTYP', 3]
                                            ],
                                            paint: {
                                                'line-color': baseCountryCarto,
                                                'line-dasharray': [3, 2],
                                                'line-width': 0.8
                                            }
                                        },
                                        {
                                            id: 'bndl_dotted',
                                            type: 'line',
                                            source: 'boundaries',
                                            'source-layer': 'bndl',
                                            maxzoom: 4,
                                            minzoom: 0,
                                            filter: [
                                                'all',
                                                ['==', 'BDYTYP', 4]
                                            ],
                                            paint: {
                                                'line-color': baseCountryCarto,
                                                'line-dasharray': [1, 2],
                                                'line-width': 0.8
                                            }
                                        },
                                        {
                                            id: 'hide_ata',
                                            type: 'fill',
                                            source: 'boundaries',
                                            'source-layer': 'bnda',
                                            maxzoom: 4,
                                            minzoom: 0,
                                            filter: ['==', 'ISO3CD', 'ATA'],
                                            paint: {
                                                'fill-color': baseCountryCarto,
                                                'fill-opacity': 1
                                            }
                                        },
                                    ]
                                };
                            }

                            return mapboxStyleUrl;
                        }

                        function lightenColor(col, amt) {
                            const num = parseInt(col, 16);
                            const r = (num >> 16) + amt;
                            const b = ((num >> 8) & 0x00FF) + amt;
                            const g = (num & 0x0000FF) + amt;
                            const newColor = g | (b << 8) | (r << 16);
                            return newColor.toString(16);
                        }
                    }
                )
            }
        }
    }
)
(jQuery, Drupal, Drupal.debounce, drupalSettings);
