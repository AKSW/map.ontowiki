<?php

/**
 * Geonames geocoding service implementation for Geocoding component.
 * Service documentation: http://www.geonames.org/export/geonames-search.html
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @version $Id$
 * TODO comments
 */
class Geonames_service implements Geocoder_service
{
    public function servicepollaction ($searchstring, $searchstring_short, $uri)
    {
	// Nice string for the geocoding service
	$data['label'] = "GeoNames";
        
	// Build the URL to send the geocoding request to
        $url  = "http://ws.geonames.org/searchJSON?maxRows=10";
	//only populated places, administrative entities, buildings or roads
	$url .= "&featureClass=P&featureClass=A&featureClass=S&featureClass=R"; 
	$url .= "&continentCode=EU&style=FULL&lang=nl&q=";
	$url .= urlencode($searchstring_short);

	// Send the request
	$result = json_decode( @file_get_contents($url), true );

	//if(!empty($result) AND isset($result['geonames'][0])){
	//Counter for multiple result entries
	$resultcounter = 0;	

        if(!isset($result['geonames'][$resultcounter])) return false;
	
	while (isset($result['geonames'][$resultcounter]) && $resultcounter < 10 ) {
	
		$data[$resultcounter]['name'] = $result['geonames'][$resultcounter]['name'];
		$data[$resultcounter]['lat'] = $result['geonames'][$resultcounter]['lat'];
		$data[$resultcounter]['lon'] = $result['geonames'][$resultcounter]['lng'];

		// Extract Wikipedia link from resultset (stored in lang="link" in alternateNames)
		if (isset ($result['geonames'][$resultcounter]['alternateNames'])) {
		foreach ($result['geonames'][$resultcounter]['alternateNames'] as $altkey => $altname) {
			foreach ($altname as $key => $value) {
				if ($key == "lang" && $value == "link") {
					$linkname = $savedvalue;
					// Only take the link if it's a wikipedia one
					if (strpos($linkname, "wikipedia")) {
					   $pos = strrpos($linkname, "/");
					   $data[$resultcounter]['semantic_data'] = array("owl:sameAs" => "http://dbpedia.org/resource" . substr($linkname, $pos));
					}
				}
				if ($key == "name")				
					$savedvalue = $value;
			}
		}
		}
		$data[$resultcounter]['service_accuracy'] = "Low";
		$data[$resultcounter]['accuracy'] = "4";
		$data['status'] = "OK";

		$resultcounter++;                
        }
	return $data;
    }
}

