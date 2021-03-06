// vim: sw=4:sts=4:expandtab

function NewMapManager ( mapContainer, extent ) {

    /* properties */
    this.mapContainer            = mapContainer;
    this.map                     = {};
    this.markers                 = false;
    this.extent                  = extent;
    this.jsonRequestUrl          = jsonUrl;
    this.imgPath                 = '';
    this.iconDefaultCenter       = '';
    this.iconDefaultSize         = '';
    this.icon                    = {};
    this.clusterIcon             = {};
    this.themePath               = '';
    this.defaultLayer            = '';
    this.defaultSelectedIndirect = 0;

    /* functions */
    this.init                   = initMap;
    this.loadMarkers            = loadMarkers;
    this.markerCallbackHandler  = markerCallbackHandler;
    this.prepare                = prepareMap;
}

function prepareMap() {
    OpenLayers.ImgPath = this.imgPath;
}

// initiate the map
function initMap ( ) {

    // for information about OpenLayers spherical mercator projection
    // seeAlso: http://docs.openlayers.org/library/spherical_mercator.html
    // seeAlso: http://openlayers.org/dev/examples/spherical-mercator.html (example)

    var options = {
        projection: new OpenLayers.Projection("EPSG:900913"),
        displayProjection: new OpenLayers.Projection("EPSG:4326"),
        units: "m",
        numZoomLevels: 18,
        maxResolution: 156543.0339,
        maxExtent: new OpenLayers.Bounds(-20037508.34, -20037508.34, 20037508.34, 20037508.34),
        theme: this.themePath
    };

    this.map	    = new OpenLayers.Map( this.mapContainer, options);
    //    this.map.theme  = this.themePath;

    // add controls to the main map and the detail map
    this.map.addControl( new OpenLayers.Control.PanZoom( ) );
    this.map.addControl( new OpenLayers.Control.LayerSwitcher( ) );

    // Create a set of OpenStreetMap (OSM) layers
    // but the OSM layers have a projection problem, will hopefully come back later

    // Create a set of Google Map layers for physical, street, hybrid and satellite view
    // has no projection problems, because i use the google projection as default :-(
    var gmap = new OpenLayers.Layer.Google( "Google Streets",   {sphericalMercator:true}); // type ist default
    var ghyb = new OpenLayers.Layer.Google( "Google Hybrid",    {sphericalMercator:true, type: G_HYBRID_MAP});
    var gsat = new OpenLayers.Layer.Google( "Google Satellite", {sphericalMercator:true, type: G_SATELLITE_MAP, numZoomLevels: 22});
    var gphy = new OpenLayers.Layer.Google( "Google Physical",  {sphericalMercator:true, type: G_PHYSICAL_MAP});

    // create OpenStreetMap layer
    var osmm = new OpenLayers.Layer.OSM();
    var osmt = new OpenLayers.Layer.OSM( "OpenStreetMap (Tiles@Home)", "http://tah.openstreetmap.org/Tiles/tile/${z}/${x}/${y}.png");

    // Adds the layers to the mainMap and detailMap, because i couldn't clone them i only add the googlestreets layer to the detailmap
    // mainMap.addLayers( [gmap, ghyb, gsat, gphy, osmmnk, osmtah, osmcyc, yahooLayer]);
    this.map.addLayers( [ gmap, ghyb, gsat, gphy, osmm, osmt ] );

    // zoom the mainMap to the minimal extend containing all markers, hopefully
    /*var maxExtent = new OpenLayers.Bounds( this.extent.left, this.extent.bottom, this.extent.right, this.extent.top );
    maxExtent.transform(this.map.displayProjection, this.map.projection);
    this.map.zoomToExtent( maxExtent, false );*/

    // read the default layer from configuration
    this.map.setBaseLayer( this.map.getLayersByName( this.defaultLayer )[0] );

    // load the markers for the Map
    //this.loadMarkers(false);
}

// load resources to display from MapController and build and display markers on the maps
function loadMarkers(that) {
    if(!that) {
        that = this;
    }
    // destroy old Markers
    //console.log("going to destroy all markers");
    if( that.markers ) { 
        that.markers.destroy();
        //    console.log("markers destoyes");
    } else {
        //    console.log("there where no markers to destoy");
    }

    // create and add new markerlayer to the maps
    //console.log("create new markers layer");
    that.markers = new OpenLayers.Layer.Markers( "Markers" );
    //console.log("markers layer created");
    that.map.addLayer( that.markers );
    //console.log("added markers layer to map");

    // get marker from MapController with JSON
    // replace __extent__ by the actual viewable extend
    bounds = that.map.getExtent( );
    url = that.jsonRequestUrl.replace( /__extent__/ , bounds.top+','+bounds.right+','+bounds.bottom+','+bounds.left);

    if( typeof($('#selectedIndirect')) != 'undefined' && escape($('#selectedIndirect').val()) != 'undefined') {
        url = url.replace( /__indirect__/ ,  escape($('#selectedIndirect').val()));
    } else {
        url = url.replace( /__indirect__/ ,  that.defaultSelectedIndirect);
    }

    $.getJSON( url, '', function(data) {that.markerCallbackHandler.call(that,data)});
}

function markerCallbackHandler (data,that) {
    if(!that) {
        that = this;
    }
    if( data ) {
        // read data from result
        for ( var i in data ) {
            // single Marker
            if ( data[i].containingMarkers == null ) {
                // at the moment the marker doesn't bring a own icon with it
                var featureData = { icon: that.icon.clone( ), uri: data[i].uri};
            }
            // Cluster
            else {
                // at the moment the marker doesn't bring a own icon with it
                var featureData = { icon: that.clusterIcon.clone( ), content: data[i].containingMarkers };
            }

            // create new feature with the special properties
            // feature is a very abstract thing
            // console.log("create new feature on the markers layer");
            var featureLonLat = new OpenLayers.LonLat( data[i].longitude, data[i].latitude ).transform(that.map.displayProjection, that.map.projection);
            var feature = new OpenLayers.Feature( that.markers, featureLonLat, featureData );
            // console.log("created new feature on the markers layer ... done");

            // create a marker from the feature
            var marker = feature.createMarker( );

            // register events for the marker to open popup and to move the detailmap
            // the second parameter gives the content in which the function will be called (accessible as this in the function)
            // === here will come a great new thing, I don't know what, but it will ===
            //marker.events.register( 'click', feature, selected );
            //marker.events.register( 'mouseover', feature, detailView );

            // add the marker to the markerlayer of the mainMap
            //console.log("putting new marker on the markers layer");
            that.markers.addMarker( marker );
            //console.log("put new marker on the markers layer ... done");
        }
    }
}

