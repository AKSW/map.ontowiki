/**
 * This file is part of the geocode extension for OntoWiki
 *
 * @author     Claudius Henrichs
 * @copyright  Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id: geocode.js $
 *
 */

/**
 * The main document.ready assignments and code
 */
$(document).ready(function() {
 
    $('#geocode-button').click(function(event) {
        doGeocode();
    });

    $("#geocode-searchString").keypress(function (e) {
	if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
		doGeocode();
		return false;
	} else {
		return true;
	}
    });

});

var last_selected_resource;

function geocode_hover(target) {
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

function togglemoreresults(target) {
    if (target.next().css("display") != "none")
    	$('.geocode-moreresults').hide();
    else
        target.next().show(); // or .slideDown()
}

function enableManualMode(resourceUri) {
    $('#geocode-manual-active').show();
    $('#geocode-manual').hide();
    $('#geocode-manual-marker').show();

    if (typeof minimap != 'undefined') {
        minimap.addResourceManual(resourceUri);
    }
}

function disableManualMode() {
    $('#geocode-manual-active').hide();
    $('#geocode-manual').show();

    if (typeof minimap != 'undefined') {
        minimap.addResourceManualQuit();
    }
}

function round_decimals(x, n) {
  if (n < 1 || n > 14) return false;
  var e = Math.pow(10, n);
  var k = (Math.round(x * e) / e).toString();
  if (k.indexOf('.') == -1) k += '.';
  k += e.toString().substring(1);
  return k.substring(0, k.indexOf('.') + n+1);
}

