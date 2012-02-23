function GeocodeManager ()
{
    // Properties
    this.last_selected_resource = '';
    this.themePath = '';

    // Methods
    this.doGeocode = doGeocode;
    this.doSavecoords = doSavecoords;
    this.geocode_hover = geocode_hover;
    this.togglemoreresults = togglemoreresults;
    this.enableManualMode = enableManualMode;
    this.disableManualMode = disableManualMode;
    this.round_decimals = round_decimals;
}

/**
 * Poll Ontowiki's geocoding component with the querystring given in the form field
 */
function doGeocode (searchstring, ) {
    $('#geocode-resultlist').html('<?php echo $this->_('Geocoding for') ?> "<span id="geocode-searchoutput">' + searchstring + '</span>" <img src="<?php echo $this->themeUrlBase; ?>images/spinner.gif"><br />');
    $('#geocode-resultlist').show();

    var jsonRequestUrl = htmlPartialUrl.replace(/__searchstring__/, encodeURI(searchstring));

    $.getJSON(jsonRequestUrl, function (data) {
            $('#geocode-resultlist').empty();

            results = false;

            htmlout  = "<?php echo $this->_('Geocoding results for') ?> \"" + searchstring + "\":<br />";
            htmlout += "<table class='separated-vertical'>";
            var extent = {};

            $.each (data, function (index, geocoder)
                {
                if (typeof geocoder[0] != 'undefined') {
                var marker_id = "<?php echo $this->data['resourceUri']; ?>/" + geocoder.label;

                if (typeof minimap != 'undefined') {
                minimap.addResource(geocoder[0].lat, geocoder[0].lon, marker_id);
                } else {
                //TODO: Bring up map if minimap module is not shown
                }

                htmlout += "<tr class='geocode-results' id='" + geocoder.label + "' resid='" + marker_id + "'>";
                htmlout += "<td>" + geocoder.label;
                htmlout += "</td>";

                htmlout += "<td style='width: 80px;'><div class='star-accuracy-inactive' style='width: " + 8*(10 - geocoder[0].accuracy) + "px; background-position: " + 8*(10 - geocoder[0].accuracy) + "px 0px' title='<?php echo $this->_('Accuracy (higher is better)') ?>'></div>";
                htmlout += "<div class='star-accuracy' style='width: " + 8*(geocoder[0].accuracy) + "px;' title='<?php echo $this->_('Accuracy (higher is better)') ?>'></div>";

                htmlout += "</td><td>";
                htmlout += "<span property='http://www.w3.org/2003/01/geo/wgs84_pos#lat_long' content='" + geocoder[0].lat + "," + geocoder[0].lon + "' accuracy='" + geocoder[0].accuracy + "'></span>";
                //htmlout += "<a class='button geocode-usebutton' coordinates='" + geocoder[0].lat + "," + geocoder[0].lon + "'><?php echo $this->_('Use this') ?></a></td></tr>";
                htmlout += "<a class='button geocode-usebutton' coordinates='" + geocoder[0].lat + "," + geocoder[0].lon + "' title='<?php echo $this->_('Use this') ?>'><img src='<?php echo $this->themeUrlBase; ?>images/icon-save.png' /></a></td></tr>";

                // Show more results if the geocode did return more
                if (typeof geocoder[1] != 'undefined') {
                    htmlout += "<tr><td colspan='3' style='border-top: none;'>";
                    htmlout += "<a class='geocode-more'><span class='icon-button expand'></span> <?php echo $this->_('More results') ?></a>";
                    htmlout += "<table class='seperated-vertical geocode-moreresults' style='display:none;'>";

                    moreresults = 1;

                    while (typeof geocoder[moreresults] != 'undefined') {
                        htmlout += "<tr><td>" + geocoder[moreresults].name + "<br />";
                        htmlout += "<span property='http://www.w3.org/2003/01/geo/wgs84_pos#lat_long' content='" + geocoder[moreresults].lat + "," + geocoder[moreresults].lon + "' accuracy='" + geocoder[moreresults].accuracy + "'></span>";
                        htmlout += "<a class='button geocode-usebutton' title='<?php echo $this->_('Use this') ?>'><img src='<?php echo $this->themeUrlBase; ?>images/icon-save.png' /></a>";
                        htmlout += "<div class='star-accuracy-inactive' style='width: " + 8*(10 - geocoder[0].accuracy) + "px; background-position: " + 8*(10 - geocoder[moreresults].accuracy) + "px 0px' title='<?php echo $this->_('Accuracy (higher is better)') ?>'></div>";
                        htmlout += "<div class='star-accuracy' style='width: " + 8*(geocoder[moreresults].accuracy) + "px;' title='<?php echo $this->_('Accuracy (higher is better)') ?>'></div>";
                        htmlout += "</td>";
                        moreresults++;
                    }
                    htmlout += "</table></td></tr>";
                }
                results = true;
                }
                else {
                    if (results != true) {
                        results = false;
                    }
                }
                }
    );

    if (typeof minimap != 'undefined') {
        minimap.zoomIdeal(); // Zoom minimap so that all markers are shown
    }

    htmlout += "</table>";
    htmlout += "<div id='geocode-manual'><a class='button' id='geocode-manual-button'><img src='<?php echo $this->themeUrlBase; ?>images/icon-add.png'>&nbsp;<?php echo $this->_('Set location manually') ?></a></div>";
    htmlout += "<div id='geocode-manual-active' style='display: none;'><a class='button' id='geocode-exitmanual-button'><img src='<?php echo $this->themeUrlBase; ?>images/icon-delete-grey.png'>&nbsp;<?php echo $this->_('Leave manual placement mode') ?></a></div>";
    htmlout += "<div id='geocode-manual-marker' style='display: none;'><?php echo $this->_('Manually placed marker') ?>: <span property='http://www.w3.org/2003/01/geo/wgs84_pos#lat_long' content='' accuracy='9' id='geocode-manual-output' style='color: grey;'><?php echo $this->_('Not set') ?></span>";
    htmlout += "<a class='button geocode-usebutton' id='geocode-manual-save' title='<?php echo $this->_('Use this') ?>'><img src='<?php echo $this->themeUrlBase; ?>images/icon-save.png' /></a></div>";

    $('#geocode-resultlist').append(htmlout);

    $('.geocode-results').hover(function(event) {
            geocode_hover($(this));
            });

    $('.geocode-usebutton').click(function(event) {
            doSavecoords($(this).prev().attr("content"), $(this).prev().attr("accuracy"));
            });

    $('.geocode-more').click(function(event) {
            togglemoreresults($(this));
            });

    $('#geocode-manual-button').click(function(event) {
            enableManualMode('<?php echo $this->data['resourceUri'] ?>/manualMarker');
            });

    $('#geocode-exitmanual-button').click(function(event) {
            disableManualMode();
            });

    // Listen for manual resource placement (event triggered by mapmanager in map component)
    $('body').bind('ontowiki.resource.placed', function(event, data) {
            $('#geocode-manual-output').attr("content", data.lat+','+data.lon);
            $('#geocode-manual-output').html('<?php echo $this->_('Set') ?>');
            $('#geocode-manual-output').css("color","green");
            });

    if (!results) {
        $('#geocode-resultlist').empty();
        htmlout =
            $('#geocode-resultlist').append('<?php echo $this->_('Unfortunately no geocoding results were returned. Please try a different name.') ?><br />');
        //$('#geocode-form').show();
    }

    });
    return false;
}

/**
 * Poll Ontowiki's geocoding component with the querystring given in the form field
 */
function doSavecoords (coordinates, accuracy) {
    // Store the coordinates that are about to be stored
    //coordinates = target.parent().next().children().attr("content");

    $('#geocode-resultlist').empty();
    $('#geocode-resultlist').append('<?php echo $this->_('Saving…') ?> <img src="<?php echo $this->themeUrlBase; ?>images/spinner.gif"><br /><br />');

    <?php
        $htmlPartialUrl = new OntoWiki_Url(array('controller' => 'map', 'action' => 'storecoords'));
    $htmlPartialUrl->setParam('coordinates', '__coordinates__');
    $htmlPartialUrl->setParam('accuracy', '__accuracy__');
    ?>

        var htmlPartialUrl = '<?php echo $htmlPartialUrl; ?>';
    htmlPartialUrl = htmlPartialUrl.replace(/__coordinates__/, encodeURI(coordinates));
    var jsonRequestUrl = htmlPartialUrl.replace(/__accuracy__/, encodeURI(accuracy));

    $.getJSON(jsonRequestUrl, function (data) {
            $('#geocode-resultlist').empty();
            if (data.status == 'OK') {
            htmlout = '<?php echo $this->_('New coordinates have been saved.') ?><br/>';

            <?php
            $htmlPartialUrl = new OntoWiki_Url(array('controller' => $this->data['controller'], 'action' => $this->data['action'] ));
            ?>

            htmlout+= '<a href="<?php echo $htmlPartialUrl; ?>"><?php echo $this->_('Click here to reload this page') ?></a>';
            } else {
            htmlout = '<?php echo $this->_('New coordinates could not be saved.') ?><br/>';
            htmlout+= data.message;

            }
            $('#geocode-resultlist').append(htmlout);        
            //$('#geocode-resultlist').animate({ backgroundColor: "#FF0000" }, 1000);
            });
}

var last_selected_resource;

function geocode_hover(target)
{
    if (typeof OntoWiki.selectedResources == "undefined") {
        OntoWiki.selectedResources = [];
    }

    var pos = $.inArray(last_selected_resource, OntoWiki.selectedResources);
    if (pos > -1) {
        OntoWiki.selectedResources.splice(pos, 1);
    }

    // Trigger selected events for other components and modules to listen
    $('body').trigger('ontowiki.resource.selected', target.attr("resid"));
    OntoWiki.selectedResources.push(target.attr("resid"));
    $('body').trigger('ontowiki.selection.changed', [ OntoWiki.selectedResources ]);
    last_selected_resource = target.attr("resid");
}

function togglemoreresults(target)
{
    if (target.next().css("display") != "none") {
        $('.geocode-moreresults').hide();
    } else {
        target.next().show(); // or .slideDown()
    }
}

function enableManualMode(resourceUri)
{
    $('#geocode-manual-active').show();
    $('#geocode-manual').hide();
    $('#geocode-manual-marker').show();

    if (typeof minimap != 'undefined') {
        minimap.addResourceManual(resourceUri);
    }
}

function disableManualMode()
{
    $('#geocode-manual-active').hide();
    $('#geocode-manual').show();

    if (typeof minimap != 'undefined') {
        minimap.addResourceManualQuit();
    }
}

function round_decimals(x, n)
{
    if (n < 1 || n > 14) {
        return false;
    }
    var e = Math.pow(10, n);
    var k = (Math.round(x * e) / e).toString();
    if (k.indexOf('.') == -1) {
        k += '.';
    }
    k += e.toString().substring(1);
    return k.substring(0, k.indexOf('.') + n+1);
}
