<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    'tw-elements' => [
        'path' => './assets/lib/tw-elements.umd.min.js',
    ],
    'tw-elements/css/tw-elements.min.css' => [
        'path' => './assets/lib/tw-elements.min.css',
        'type' => 'css',
    ],
    '@symfony/ux-leaflet-map' => [
        'path' => './vendor/symfony/ux-leaflet-map/assets/dist/map_controller.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'tom-select/dist/css/tom-select.default.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'tom-select' => [
        'version' => '2.6.2',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'tom-select/dist/css/tom-select.default.min.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'tom-select/dist/css/tom-select.bootstrap4.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'tom-select/dist/css/tom-select.bootstrap5.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    'lodash-es' => [
        'version' => '4.18.1',
    ],
    'parchment' => [
        'version' => '3.0.0',
    ],
    'eventemitter3' => [
        'version' => '5.0.4',
    ],
    'fast-diff' => [
        'version' => '1.3.0',
    ],
    'lodash.clonedeep' => [
        'version' => '4.5.0',
    ],
    'lodash.isequal' => [
        'version' => '4.5.0',
    ],
    'quill/dist/quill.snow.css' => [
        'version' => '2.0.3',
        'type' => 'css',
    ],
    'quill/dist/quill.bubble.css' => [
        'version' => '2.0.3',
        'type' => 'css',
    ],
    'quill' => [
        'version' => '2.0.3',
    ],
    'quill-delta' => [
        'version' => '5.1.0',
    ],
    'quill-table-better' => [
        'version' => '1.2.3',
    ],
    'quill-table-better/dist/quill-table-better.css' => [
        'version' => '1.2.3',
        'type' => 'css',
    ],
    'axios' => [
        'version' => '1.19.0',
    ],
    'quill2-emoji' => [
        'version' => '0.1.2',
    ],
    'quill2-emoji/dist/style.css' => [
        'version' => '0.1.2',
        'type' => 'css',
    ],
    'quill-resize-image' => [
        'version' => '1.0.11',
    ],
    'quill-toggle-fullscreen-button' => [
        'version' => '0.2.0',
    ],
    'quill-html-edit-button' => [
        'version' => '3.0.0',
    ],
    '@kurkle/color' => [
        'version' => '0.4.0',
    ],
    'leaflet' => [
        'version' => '1.9.4',
    ],
    'leaflet/dist/leaflet.min.css' => [
        'version' => '1.9.4',
        'type' => 'css',
    ],
    '@turf/turf' => [
        'version' => '7.4.0',
    ],
    'leaflet.markercluster' => [
        'version' => '1.5.3',
    ],
    '@turf/along' => [
        'version' => '7.4.0',
    ],
    '@turf/angle' => [
        'version' => '7.4.0',
    ],
    '@turf/area' => [
        'version' => '7.4.0',
    ],
    '@turf/bbox' => [
        'version' => '7.4.0',
    ],
    '@turf/bbox-clip' => [
        'version' => '7.4.0',
    ],
    '@turf/bbox-polygon' => [
        'version' => '7.4.0',
    ],
    '@turf/bearing' => [
        'version' => '7.4.0',
    ],
    '@turf/bezier-spline' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-clockwise' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-concave' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-contains' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-crosses' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-disjoint' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-equal' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-intersects' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-overlap' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-parallel' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-point-in-polygon' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-point-on-line' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-touches' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-valid' => [
        'version' => '7.4.0',
    ],
    '@turf/boolean-within' => [
        'version' => '7.4.0',
    ],
    '@turf/buffer' => [
        'version' => '7.4.0',
    ],
    '@turf/center' => [
        'version' => '7.4.0',
    ],
    '@turf/center-mean' => [
        'version' => '7.4.0',
    ],
    '@turf/center-median' => [
        'version' => '7.4.0',
    ],
    '@turf/center-of-mass' => [
        'version' => '7.4.0',
    ],
    '@turf/centroid' => [
        'version' => '7.4.0',
    ],
    '@turf/circle' => [
        'version' => '7.4.0',
    ],
    '@turf/clean-coords' => [
        'version' => '7.4.0',
    ],
    '@turf/clone' => [
        'version' => '7.4.0',
    ],
    '@turf/clusters' => [
        'version' => '7.4.0',
    ],
    '@turf/clusters-dbscan' => [
        'version' => '7.4.0',
    ],
    '@turf/clusters-kmeans' => [
        'version' => '7.4.0',
    ],
    '@turf/collect' => [
        'version' => '7.4.0',
    ],
    '@turf/combine' => [
        'version' => '7.4.0',
    ],
    '@turf/concave' => [
        'version' => '7.4.0',
    ],
    '@turf/convex' => [
        'version' => '7.4.0',
    ],
    '@turf/destination' => [
        'version' => '7.4.0',
    ],
    '@turf/difference' => [
        'version' => '7.4.0',
    ],
    '@turf/dissolve' => [
        'version' => '7.4.0',
    ],
    '@turf/distance' => [
        'version' => '7.4.0',
    ],
    '@turf/distance-weight' => [
        'version' => '7.4.0',
    ],
    '@turf/ellipse' => [
        'version' => '7.4.0',
    ],
    '@turf/envelope' => [
        'version' => '7.4.0',
    ],
    '@turf/explode' => [
        'version' => '7.4.0',
    ],
    '@turf/flatten' => [
        'version' => '7.4.0',
    ],
    '@turf/flip' => [
        'version' => '7.4.0',
    ],
    '@turf/geojson-rbush' => [
        'version' => '7.4.0',
    ],
    '@turf/great-circle' => [
        'version' => '7.4.0',
    ],
    '@turf/helpers' => [
        'version' => '7.4.0',
    ],
    '@turf/hex-grid' => [
        'version' => '7.4.0',
    ],
    '@turf/interpolate' => [
        'version' => '7.4.0',
    ],
    '@turf/intersect' => [
        'version' => '7.4.0',
    ],
    '@turf/invariant' => [
        'version' => '7.4.0',
    ],
    '@turf/isobands' => [
        'version' => '7.4.0',
    ],
    '@turf/isolines' => [
        'version' => '7.4.0',
    ],
    '@turf/kinks' => [
        'version' => '7.4.0',
    ],
    '@turf/length' => [
        'version' => '7.4.0',
    ],
    '@turf/line-arc' => [
        'version' => '7.4.0',
    ],
    '@turf/line-chunk' => [
        'version' => '7.4.0',
    ],
    '@turf/line-intersect' => [
        'version' => '7.4.0',
    ],
    '@turf/line-offset' => [
        'version' => '7.4.0',
    ],
    '@turf/line-overlap' => [
        'version' => '7.4.0',
    ],
    '@turf/line-segment' => [
        'version' => '7.4.0',
    ],
    '@turf/line-slice' => [
        'version' => '7.4.0',
    ],
    '@turf/line-slice-along' => [
        'version' => '7.4.0',
    ],
    '@turf/line-split' => [
        'version' => '7.4.0',
    ],
    '@turf/line-to-polygon' => [
        'version' => '7.4.0',
    ],
    '@turf/mask' => [
        'version' => '7.4.0',
    ],
    '@turf/meta' => [
        'version' => '7.4.0',
    ],
    '@turf/midpoint' => [
        'version' => '7.4.0',
    ],
    '@turf/moran-index' => [
        'version' => '7.4.0',
    ],
    '@turf/nearest-neighbor-analysis' => [
        'version' => '7.4.0',
    ],
    '@turf/nearest-point' => [
        'version' => '7.4.0',
    ],
    '@turf/nearest-point-on-line' => [
        'version' => '7.4.0',
    ],
    '@turf/nearest-point-to-line' => [
        'version' => '7.4.0',
    ],
    '@turf/planepoint' => [
        'version' => '7.4.0',
    ],
    '@turf/point-grid' => [
        'version' => '7.4.0',
    ],
    '@turf/point-on-feature' => [
        'version' => '7.4.0',
    ],
    '@turf/points-within-polygon' => [
        'version' => '7.4.0',
    ],
    '@turf/point-to-line-distance' => [
        'version' => '7.4.0',
    ],
    '@turf/point-to-polygon-distance' => [
        'version' => '7.4.0',
    ],
    '@turf/polygonize' => [
        'version' => '7.4.0',
    ],
    '@turf/polygon-smooth' => [
        'version' => '7.4.0',
    ],
    '@turf/polygon-tangents' => [
        'version' => '7.4.0',
    ],
    '@turf/polygon-to-line' => [
        'version' => '7.4.0',
    ],
    '@turf/projection' => [
        'version' => '7.4.0',
    ],
    '@turf/quadrat-analysis' => [
        'version' => '7.4.0',
    ],
    '@turf/random' => [
        'version' => '7.4.0',
    ],
    '@turf/rectangle-grid' => [
        'version' => '7.4.0',
    ],
    '@turf/rewind' => [
        'version' => '7.4.0',
    ],
    '@turf/rhumb-bearing' => [
        'version' => '7.4.0',
    ],
    '@turf/rhumb-destination' => [
        'version' => '7.4.0',
    ],
    '@turf/rhumb-distance' => [
        'version' => '7.4.0',
    ],
    '@turf/sample' => [
        'version' => '7.4.0',
    ],
    '@turf/sector' => [
        'version' => '7.4.0',
    ],
    '@turf/shortest-path' => [
        'version' => '7.4.0',
    ],
    '@turf/simplify' => [
        'version' => '7.4.0',
    ],
    '@turf/square' => [
        'version' => '7.4.0',
    ],
    '@turf/square-grid' => [
        'version' => '7.4.0',
    ],
    '@turf/standard-deviational-ellipse' => [
        'version' => '7.4.0',
    ],
    '@turf/tag' => [
        'version' => '7.4.0',
    ],
    '@turf/tesselate' => [
        'version' => '7.4.0',
    ],
    '@turf/tin' => [
        'version' => '7.4.0',
    ],
    '@turf/transform-rotate' => [
        'version' => '7.4.0',
    ],
    '@turf/transform-scale' => [
        'version' => '7.4.0',
    ],
    '@turf/transform-translate' => [
        'version' => '7.4.0',
    ],
    '@turf/triangle-grid' => [
        'version' => '7.4.0',
    ],
    '@turf/truncate' => [
        'version' => '7.4.0',
    ],
    '@turf/union' => [
        'version' => '7.4.0',
    ],
    '@turf/unkink-polygon' => [
        'version' => '7.4.0',
    ],
    '@turf/voronoi' => [
        'version' => '7.4.0',
    ],
    '@turf/directional-mean' => [
        'version' => '7.4.0',
    ],
    'leaflet.markercluster/dist/MarkerCluster.min.css' => [
        'version' => '1.5.3',
        'type' => 'css',
    ],
    'geojson-equality-ts' => [
        'version' => '1.0.2',
    ],
    'point-in-polygon-hao' => [
        'version' => '1.2.4',
    ],
    '@turf/jsts' => [
        'version' => '2.7.2',
    ],
    'd3-geo' => [
        'version' => '2.0.2',
    ],
    'rbush' => [
        'version' => '3.0.1',
    ],
    'skmeans' => [
        'version' => '0.9.7',
    ],
    'topojson-client' => [
        'version' => '3.1.0',
    ],
    'topojson-server' => [
        'version' => '3.0.1',
    ],
    'concaveman' => [
        'version' => '1.2.1',
    ],
    'polyclip-ts' => [
        'version' => '0.16.8',
    ],
    'arc' => [
        'version' => '0.2.0',
    ],
    'tinyqueue' => [
        'version' => '2.0.3',
    ],
    'robust-predicates' => [
        'version' => '2.0.4',
    ],
    'fast-deep-equal' => [
        'version' => '3.1.3',
    ],
    'earcut' => [
        'version' => '2.2.4',
    ],
    'd3-voronoi' => [
        'version' => '1.1.2',
    ],
    'd3-array' => [
        'version' => '2.12.1',
    ],
    'quickselect' => [
        'version' => '2.0.0',
    ],
    'point-in-polygon' => [
        'version' => '1.1.0',
    ],
    'robust-predicates/umd/orient2d.min.js' => [
        'version' => '2.0.4',
    ],
    'bignumber.js' => [
        'version' => '9.1.2',
    ],
    'splaytree-ts' => [
        'version' => '1.0.2',
    ],
    'internmap' => [
        'version' => '1.0.1',
    ],
    'leaflet.markercluster/dist/MarkerCluster.css' => [
        'version' => '1.5.3',
        'type' => 'css',
    ],
    'leaflet.markercluster/dist/MarkerCluster.Default.css' => [
        'version' => '1.5.3',
        'type' => 'css',
    ],
    'leaflet/dist/leaflet.css' => [
        'version' => '1.9.4',
        'type' => 'css',
    ],
];
