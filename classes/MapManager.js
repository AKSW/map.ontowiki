function MapManager(mapContainer, extent, jsonUrl) {

    /* properties */
    this.mapContainer = mapContainer;
    this.map = {};
    this.markers = false;

    this.extent = new OpenLayers.Bounds(extent.left, extent.bottom,
            extent.right, extent.top);

    this.jsonRequestUrl = jsonUrl;
    this.imgPath = '';
    this.iconDefaultCenter = '';
    this.iconDefaultSize = '';
    this.icon = {};
    this.iconSelected = {};
    this.clusterIcon = {};
    this.themePath = '';
    this.defaultLayer = '';
    this.defaultSelectedIndirect = 0;
    this.selectedResources = [];
    this.additionalResources = [];
    this.lol = 'lol';
    this.selectControl = false;
    this.modes = [];
    this.drawControls = {};
    this.disabledProviders = [];
    this.localMapTitel = '';
    this.localMapUrl = '';

    /* functions */
    this.init = initMap;
    this.loadMarkers = loadMarkers;
    this.markerCallbackHandler = markerCallbackHandler;
    this.prepare = prepareMap;
    this.zoomIn = zoomIn;
    this.zoomOut = zoomOut;
    this.zoomMax = zoomMax;
    this.zoomIdeal = zoomIdeal;
    this.zoomTo = zoomTo;
    this.selectEvent = selectEvent;
    this.onFeatureSelect = onFeatureSelect;
    this.onFeatureUnselect = onFeatureUnselect;
    this.addMarker = addResource;
    this.addResource = addResource;
    this.addResourceManual = addResourceManual;
    this.addResourceManualQuit = addResourceManualQuit;
    this.disableMode = disableMode;
    this.enableMode = enableMode;
    // this.onLayerChange = onLayerChange;
}

function prepareMap() {
    OpenLayers.ImgPath = this.imgPath;
}

// initiate the map
function initMap() {

    // for information about OpenLayers spherical mercator projection
    // seeAlso: http://docs.openlayers.org/library/spherical_mercator.html
    // seeAlso: http://openlayers.org/dev/examples/spherical-mercator.html
    // (example)

    var options = {
        projection : new OpenLayers.Projection("EPSG:900913"),
        displayProjection : new OpenLayers.Projection("EPSG:4326"),
        units : "m",
        numZoomLevels : 18,
        maxResolution : 156543.0339,
        maxExtent : new OpenLayers.Bounds(-20037508.34, -20037508.34,
                20037508.34, 20037508.34),
        theme : this.themePath
    };

    this.map = new OpenLayers.Map(this.mapContainer, options);
    // this.map.theme = this.themePath;

    // transform the extent to the proper projection

    this.extent.transform(this.map.displayProjection, this.map.projection);

    var filterSelector = new OpenLayers.Control();

    var that = this;

    OpenLayers.Util
            .extend(
                    filterSelector,
                    {
                        draw : function() {
                            this.box = new OpenLayers.Handler.Box(
                                    filterSelector, {
                                        'done' : this.addFilter
                                    }, {
                                        keyMask : OpenLayers.Handler.MOD_CTRL
                                    });
                            this.box.activate();
                        },

                        // TODO: diese funktion separieren
                        addFilter : function(bounds) {
                            // add Filter
                            var latProp = 'http://www.w3.org/2003/01/geo/wgs84_pos#lat';
                            var longProp = 'http://www.w3.org/2003/01/geo/wgs84_pos#long';
                            var xsd = 'http://www.w3.org/2001/XMLSchema#'; // decimal';

                            // var projection = new
                            // OpenLayers.Projection("EPSG:900913");
                            // var displayProjection = new
                            // OpenLayers.Projection("EPSG:4326");
                            //
                            var topLeft = new OpenLayers.Pixel(bounds.left,
                                    bounds.top);
                            var bottomRight = new OpenLayers.Pixel(
                                    bounds.right, bounds.bottom);
                            topLeft = that.map.getLonLatFromPixel(topLeft);
                            bottomRight = that.map
                                    .getLonLatFromPixel(bottomRight);
                            topLeft.transform(that.map.projection,
                                    that.map.displayProjection);
                            bottomRight.transform(that.map.projection,
                                    that.map.displayProjection);

                            // alert('top-left: ' + topLeft + ' bottom-right: '
                            // + bottomRight);

                            filter.add('mapLatitudeBounds', // filter id
                            latProp, // property
                            false, //
                            'geo:lat', // 
                            'between', // filter type
                            '' + bottomRight.lat + '', // 1st value
                            '' + topLeft.lat + '', // 2nd value
                            'typed-literal', // 
                            xsd + 'float', // datatype
                            function() {
                            }, // callback
                            false, // 
                            false, // negate
                            true); // don't reload
                            filter.add('mapLongitudeBounds', // filter id
                            longProp, // property
                            false, //
                            'geo:long', // 
                            'between', // filter type
                            '' + topLeft.lon + '', // 1st value
                            '' + bottomRight.lon + '', // 2nd value
                            'typed-literal', // 
                            xsd + 'float', // datatype
                            function() {
                            }, // callback
                            false, // 
                            false, // negate
                            false); // don't reload
                        }
                    });

    // add controls to the main map and the detail map
    this.map.addControl(new OpenLayers.Control.PanZoom());
    this.map.addControl(new OpenLayers.Control.LayerSwitcher());
    // this.map.addControl(new OpenLayers.Control.MousePosition());
    this.map.addControl(filterSelector);

    var myStyles = new OpenLayers.StyleMap({
    /*
     * "default": new OpenLayers.Style({ fillColor: "#ffcc66", strokeColor:
     * "#ff9933", strokeWidth: 2, graphicZIndex: 1 }), "select": new
     * OpenLayers.Style({ fillColor: "#66ccff", strokeColor: "#3399ff",
     * graphicZIndex: 2 })
     */

    });

    this.markers = new OpenLayers.Layer.Vector("New Markers", {
        styleMap : myStyles,
        rendererOptions : {
            zIndexing : true
        }
    });

    this.map.addLayers([ this.markers ]);

    //alert('disabledProviders: ' + this.disabledProviders);

    // Create a set of Google Map layers for physical, street, hybrid and
    // satellite view has no projection problems, because i use the google
    // projection as default :-(
    if ($.inArray('google', this.disabledProviders) < 0) {
        //alert('enable google');
        var gmap = new OpenLayers.Layer.Google("Google Streets", {
            sphericalMercator : true
        }); // type ist default
        var ghyb = new OpenLayers.Layer.Google("Google Hybrid", {
            sphericalMercator : true,
            type : G_HYBRID_MAP
        });
        var gsat = new OpenLayers.Layer.Google("Google Satellite", {
            sphericalMercator : true,
            type : G_SATELLITE_MAP,
            numZoomLevels : 22
        });
        var gphy = new OpenLayers.Layer.Google("Google Physical", {
            sphericalMercator : true,
            type : G_PHYSICAL_MAP
        });

        // Adds the layers to the mainMap and detailMap, because i couldn't
        // clone them i only add the googlestreets layer to the detailmap
        this.map.addLayers([ gmap, ghyb, gsat, gphy ]);
    }

    if ($.inArray('osm', this.disabledProviders) < 0) {
        //alert('enable osm');
        // Create a set of OpenStreetMap (OSM) layers but the OSM layers have a
        // projection problem, will hopefully come back later
        var osmm = new OpenLayers.Layer.OSM();
        var osmt = new OpenLayers.Layer.OSM("OpenStreetMap (Tiles@Home)",
                "http://tah.openstreetmap.org/Tiles/tile/${z}/${x}/${y}.png");

        // Adds the layers to the mainMap and detailMap, because i couldn't
        // clone
        // them i only add the googlestreets layer to the detailmap
        this.map.addLayers([ osmm, osmt ]);
    }

    if ($.inArray('localMap', this.disabledProviders) < 0) {
        //alert('enable local');
        var localMap = new OpenLayers.Layer.OSM(this.localMapTitel, this.localMapUrl);
        this.map.addLayers([ localMap ]);
    }

    if ($.inArray('yahoo', this.disabledProviders) < 0) {
        //alert('enable yahoo');
        // mainMap.addLayers( [ yahooLayer ]);
    }

    this.drawControls = {
        point : new OpenLayers.Control.DrawFeature(this.markers,
                OpenLayers.Handler.Point, {
                    persist : true
                })
    };

    for ( var key in this.drawControls) {
        this.map.addControl(this.drawControls[key]);
    }

    function newMarker(event) {
        //alert('new feature added');
        log(event.type);
    }

    this.drawControls['point'].events.on({
        featureadded : function(e) {
            /* e.feature is a ref to the added feature */
            oldFeature = this.markers.getFeatureById(this.newResourceId);
            if (typeof oldFeature != 'undefined' && oldFeature != null) {
                oldFeature.destroy();
            }
            e.feature.id = this.newResourceId;
            // this.manualResource = e.feature;
            this.additionalResources.push(e.feature);

            // alert('feature added');
            var lon = e.feature.geometry.x;
            var lat = e.feature.geometry.y;

            var vectorLonLat = new OpenLayers.LonLat(lon, lat);

            // console.log(i + ": lat/long: " + resource.latitude + "/" +
            // resource.longitude);

            vectorLonLat.transform(this.map.projection,
                    this.map.displayProjection);

            // alert('lol=' + this.lol + 'new feature added long:' +
            // vectorLonLat.lon + ', lat: ' + vectorLonLat.lat);

            var data = {
                uri : e.feature.id,
                lon : vectorLonLat.lon,
                lat : vectorLonLat.lat
            };

            $('body').trigger('ontowiki.resource.placed', [ data ]);
        },
        scope : this
    });
    // OpenLayers.Events.register('featureadded', this, newMarker(event));
    // TODO have to register for 'featureadded' event here and produce a jQuery
    // event
    // also have to delete the last added marker

    this.selectControl = new OpenLayers.Control.SelectFeature(this.markers, {
        onSelect : function(feature) {
            onFeatureSelect(feature, that);
        },
        onUnselect : function(feature) {
            onFeatureUnselect(feature, that);
        },
        multipleKey : "ctrlKey",
        toggle : true
    });

    this.map.addControl(this.selectControl);
    this.selectControl.activate();

    // zoom the mainMap to the minimal extend containing all markers, hopefully
    this.map.zoomToExtent(this.extent, false);

    // and zoom out once, because mormaly not all markers are in the above
    // defined extend
    /*
     * if( this.map.getZoom( ) > 0 ) { this.map.zoomOut( ); }
     */

    // read the default layer from configuration
    this.map.setBaseLayer(this.map.getLayersByName(this.defaultLayer)[0]);

    // load the markers for the Map
    this.loadMarkers(false);

    // register events to reload the markers when the mainMap has been moved
    // and to move the detailMap on click on the mainMap
    var that = this;
    this.map.events.register('moveend', '', function(data) {
        that.loadMarkers(that);
    });

    // this.map.events.register('changelayer', '', that.onLayerChange);

    // don't know, what this is
    $('#selectedIndirect').change(function(data) {
        that.loadMarkers(that);
    });

    // Finally fire an event that tells the map has finished loading
    $('#mapContainer').trigger('initend');
}

// load resources to display from MapController and build and display markers on
// the maps
function loadMarkers(that) {
    if (!that) {
        that = this;
    }

    that.markers.destroyFeatures();

    // that.features = new Array();

    // get marker from MapController with JSON
    // replace __extent__ by the actual viewable extend
    bounds = that.map.getExtent();

    bounds.transform(this.map.projection, this.map.displayProjection);

    url = that.jsonRequestUrl.replace(/__extent__/, bounds.top + ','
            + bounds.right + ',' + bounds.bottom + ',' + bounds.left);

    if (typeof ($('#selectedIndirect')) != 'undefined'
            && escape($('#selectedIndirect').val()) != 'undefined') {
        url = url.replace(/__indirect__/, escape($('#selectedIndirect').val()));
    } else {
        url = url.replace(/__indirect__/, that.defaultSelectedIndirect);
    }

    /**
     * Restore additionaly added Resources
     */
    that.markers.addFeatures(that.additionalResources);

    $.getJSON(url, '', function(data) {
        that.markerCallbackHandler(data, that);
    });
}

function markerCallbackHandler(data, that) {
    if (!that) {
        that = this;
    }

    if (data) {
        // read data from result
        var features = new Array();
        var selectedFeature = null;

        for ( var i = 0; i < data.length; i++) {
            resource = data[i];
            // single Marker
            // at the moment the marker doesn't bring a own icon with it
            // icon: that.icon.clone( )
            // uri: data[i].uri
            // console.log(i + ": " + $.toJSON(resource));

            var vectorLonLat = new OpenLayers.LonLat(resource.longitude,
                    resource.latitude);

            // console.log(i + ": lat/long: " + resource.latitude + "/" +
            // resource.longitude);

            vectorLonLat.transform(this.map.displayProjection,
                    this.map.projection);

            // console.log(i + ": transformed" );
            var point = new OpenLayers.Geometry.Point(vectorLonLat.lon,
                    vectorLonLat.lat);
            var attri = null;
            var style = null;

            if (resource.isCluster) {
                // should change the color or something like that
                // console.log(i + ": " + "cluster");
                style = null;
            }

            var vector = new OpenLayers.Feature.Vector(point, attri, style);

            // console.log(i + ": uri: " + resource.uri );
            vector.id = resource.uri;

            if (resource.isCluster) {
                // adding the uris of the contained resources to the vector wich
                // represents them
                vector.isCluster = true;
                vector.content = [];

                for (j in resource.containingMarkers) {
                    // console.log($.toJSON(resource.containingMarkers[j]));
                    vector.content.push(resource.containingMarkers[j].uri);
                }
            } else {
                vector.isCluster = false;
            }

            features.push(vector);

            if ($.inArray(resource.uri, this.selectedResources)) {
                selectedFeature = vector;
            }

        }
        this.markers.addFeatures(features);

        $.each(this.selectedResources, function(key, value) {
            that.selectControl.select(that.markers.getFeatureById(value));
        });

        $('body').trigger('ontowiki.mapmanager.markersloaded');
    }
}

function zoomIn() {
    this.map.zoomIn();
}

function zoomOut() {
    this.map.zoomOut();
}

function zoomMax() {
    this.map.zoomToMaxExtent();
}

function zoomIdeal() {
    // zoom the mainMap to the minimal extend containing all markers, hopefully
    // this.map.zoomToExtent(this.extent, false);
    this.map.zoomToExtent(this.markers.getDataExtent(), false);

}

function zoomTo(extent) {
    var newExtent = new OpenLayers.Bounds(extent.left, extent.bottom,
            extent.right, extent.top);
    newExtent.transform(this.map.displayProjection, this.map.projection);
    this.map.zoomToExtent(newExtent, false);
}

/**
 * This Method is called by the maps ontowiki.selection.changed callbackfunction
 * to update the maps internal list of selected resource
 * 
 * @param data
 *            The data given with the event in this case it should be the same
 *            as OntoWiki.selectedResources
 * @param that
 *            The valid MapManager-Object. This is needed, because this function
 *            is not called in the right context of this object
 * @return nothing
 */
function selectEvent(data, that) {
    if (!that) {
        that = this;
    }

    // alert('selectEvent');

    var toUnselect = [];
    // data von selectedResources abziehen und ergebnis deaktivieren
    // und aus selectedResources entfernen
    $.each(that.selectedResources, function(key, value) {
        // check if this resources is not in the OntoWiki.selectedResources
        var pos = $.inArray(value, data);
        if (pos < 0) {
            // is so, remove it from oure list und unselect it
            toUnselect[key] = value;
        }
    });

    $.each(toUnselect, function(key, value) {
        // remove from selectedResources list
        that.selectedResources.splice(key, 1);

        // unselect Map Feature
        var vector = that.markers.getFeatureById(value);
        if (vector != null) {
            that.selectControl.unselect(vector);
        }
    });

    delete toUnselect;

    var toSelect = [];
    // selectedResources von data abziehen und ergebnis aktivieren
    // und in selectedResources einfügen
    $.each(data, function(key, value) {
        // check if this resources is not in the oure selectedResources list
        var pos = $.inArray(value, that.selectedResources);
        if (pos < 0) {
            // if so, add it to oure list and select ist
            toSelect[key] = value;
        }
    });

    $.each(toSelect, function(key, value) {
        // add it to selectedResources list
        that.selectedResources.push(value);

        // select Map Feature
        var vector = that.markers.getFeatureById(value);
        if (vector != null) {
            that.selectControl.select(vector);
        }
    });

    delete toSelect;

}

/**
 * Callbackfunction for the select-event of the OpenLayers SelectControl
 * 
 * @param feature
 *            the feature which is selected
 * @param that
 *            The valid MapManager-Object. This is needed, because this function
 *            is not called in the right context of this object
 * @return nothing
 */
function onFeatureSelect(feature, that) {
    if (!that) {
        that = this;
    }

    // should check weather the feature ist a cluster
    /*
     * if(feature.isCluster) { // the uris are in the content than
     * feature.content; }
     */

    /*
     * The following case could or will be a problem:
     * 
     * A resource is selected in the resource list, this resource is represented
     * by a cluster on the map, the selectEvent() will be fired, which fires
     * onFeatureSelect, wich also selects all other contained resources, wich
     * again fires the event and will select all these resources on the map.
     * 
     * One solution would be to introduce a status between selected and
     * unselecte, like half-selected.
     */

    // alert('select feature: ' + feature.id);
    if (typeof OntoWiki.selectedResources == "undefined") {
        OntoWiki.selectedResources = [];
    }

    // check if resource is already added to oure list
    var pos = $.inArray(feature.id, that.selectedResources);
    if (pos < 0) {
        // not added --> called by selectControl
        that.selectedResources.push(feature.id);
        OntoWiki.selectedResources.push(feature.id);
        $('body').trigger('ontowiki.resource.selected', [ feature.id ]);
        $('body').trigger('ontowiki.selection.changed',
                [ OntoWiki.selectedResources ]);
    } else {
        // called by selectEvent, all done
    }

}

/**
 * Callbackfunction for the unselect-event of the OpenLayers SelectControl
 * 
 * @param feature
 *            the feature which is unselected
 * @param that
 *            The valid MapManager-Object. This is needed, because this function
 *            is not called in the right context of this object
 * @return nothing
 */
function onFeatureUnselect(feature, that) {
    if (!that) {
        that = this;
    }

    // alert('unselect feature: ' + feature.id);

    if (typeof OntoWiki.selectedResources == "undefined") {
        OntoWiki.selectedResources = [];
    }

    // check if resource is already removed from oure list
    var pos = $.inArray(feature.id, that.selectedResources);
    if (pos > -1) {
        // not removed --> called by selectControl
        that.selectedResources.splice(pos, 1);

        pos = $.inArray(feature.id, OntoWiki.selectedResources);
        if (pos > -1) {
            OntoWiki.selectedResources.splice(pos, 1);
        }

        $('body').trigger('ontowiki.resource.unselected', [ feature.id ]);
        $('body').trigger('ontowiki.selection.changed',
                [ OntoWiki.selectedResources ]);
    } else {
        // called by selectEvent, all done
    }

}

function addResource(lat, lon, uri) {

    // console.log("lat/long(" + label + "): " + lat + "/" + lon);

    this.lol = "rofl";

    var features = new Array();

    var vectorLonLat = new OpenLayers.LonLat(lon, lat);

    vectorLonLat.transform(this.map.displayProjection, this.map.projection);

    // console.log(i + ": transformed" );
    var point = new OpenLayers.Geometry.Point(vectorLonLat.lon,
            vectorLonLat.lat);
    var attri = null;

    // TODO: hier muss ein anderes als das standart syleobjekt eingesetzt werden
    var style = null;

    var vector = new OpenLayers.Feature.Vector(point, attri, style);

    // console.log(i + ": uri: " + resource.uri );
    vector.id = uri;

    features.push(vector);

    this.additionalResources.push(vector);

    this.markers.addFeatures(features);

}

function addResourceManual(uri) {
    this.enableMode('addMarker');
    this.newResourceId = uri;
}

function addResourceManualQuit() {
    this.disableMode('addMarker');
}

function enableMode(mode) {
    if (mode == "addMarker") {
        this.modes.push(mode);
        this.drawControls['point'].activate();
    } else {
        // unsupported mode
    }

}

function disableMode(mode) {
    if (mode == "addMarker") {
        this.drawControls['point'].deactivate();
        var pos = $.inArray(mode, this.modes);
        if (pos > -1) {
            this.modes.splice(pos, 1);
        }
    } else {
        // unsupported mode
    }
}

/**
 * Callbackfunction, which is called when tha layers is changed TODO still under
 * construction
 * 
 * @param data
 * @return nothing
 */
/*
 * function onLayerChange(data) {
 * 
 * if (data.property == 'visibility') { //alert('layertype: {' + typeof
 * data.layer.type + '} layer: {' + typeof data.layer + '} classname {' +
 * data.layer.className + "}"); } }
 */

$('body').trigger('ontowiki.mapmanager.loaded');
