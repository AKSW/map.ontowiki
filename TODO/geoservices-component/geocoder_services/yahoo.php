<?php

/**
 * Yahoo geocoding service implementation for Geocoding component.
 * Service documentation: http://developer.yahoo.com/maps/rest/V1/geocode.html 
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @version $Id$
 * TODO comments
 */
class Yahoo_service implements Geocoder_service
{
    public function servicepollaction ($searchstring, $searchstring_short, $uri)
    {
	$yahoo_appid = 'YD-9G7bey8_JXxQP6rxl.fBFGgCdNjoDMACQA--'; // <= Your Yahoo AppID here

	// Nice string for the geocoding service
	$data['label'] = "Yahoo";
        
	// Build the URL to send the geocoding request to
	//  Yahoo request could be split to street=,city=,state= requests instead of location= 
        $url  = "http://local.yahooapis.com/MapsService/V1/geocode?appid=".$yahoo_appid."&output=php&location=";	
	$url .= urlencode($searchstring);

	// Make the request
	$phpserialized = file_get_contents($url);
	
	// Parse the serialized response
	$result = unserialize($phpserialized);
	
	//if(!empty($result) AND isset($result['results'][0])){
	if(empty($result)) $data['status'] = "No data returned";

	//Counter for multiple result entries
	$resultcounter = 0;	

	while (isset($result[$resultcounter]) && $resultcounter < 10 ) {

		$data['lat'] = $result['ResultSet']['Result']['Latitude'];
		$data['lon'] = $result['ResultSet']['Result']['Longitude'];
		$data['accuracy'] = $result['ResultSet']['Result']['precision'];
		$data['status'] = "OK";
		$resultcounter++;
	}
	return $data;
    }
}

