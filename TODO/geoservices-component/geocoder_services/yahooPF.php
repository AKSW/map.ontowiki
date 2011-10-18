<?php

/**
 * Yahoo Placefinder geocoding service implementation for Geocoding component.
 * Service documentation: http://developer.yahoo.com/geo/placefinder/guide/ 
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @version $Id$
 * TODO comments
 */
class YahooPF_service implements Geocoder_service
{
    public function servicepollaction ($searchstring, $searchstring_short, $uri)
    {
	$yahoo_appid = 'YD-9G7bey8_JXxQP6rxl.fBFGgCdNjoDMACQA--'; // <= Your Yahoo AppID here

	// Nice string for the geocoding service
	$data['label'] = "YahooPF";
        
	// Build the URL to send the geocoding request to
	//  Yahoo request could be split to street=,city=,state= requests instead of location= 
        $url  = "http://where.yahooapis.com/geocode?appid=".$yahoo_appid."&flags=P&location=";	
	$url .= urlencode($searchstring);

	// Make the request
	$phpserialized = file_get_contents($url);
	
	// Parse the serialized response
	$result = unserialize($phpserialized);
	
	//if(!empty($result) AND isset($result['results'][0])){
	if(empty($result)) return false;

	//Counter for multiple result entries
	$resultcounter = 0;	

	while (isset($result['ResultSet']['Result'][$resultcounter]) && $resultcounter < 10 ) {

		$data[$resultcounter]['name'] = $result['ResultSet']['Result'][$resultcounter]['city'].", ".$result['ResultSet']['Result'][$resultcounter]['state'].", ".$result['ResultSet']['Result'][$resultcounter]['country'];
		$data[$resultcounter]['lat'] = $result['ResultSet']['Result'][$resultcounter]['latitude'];
		$data[$resultcounter]['lon'] = $result['ResultSet']['Result'][$resultcounter]['longitude'];
		$data[$resultcounter]['service_accuracy'] = $result['ResultSet']['Result'][$resultcounter]['quality'];
		$data[$resultcounter]['accuracy'] = ($result['ResultSet']['Result'][$resultcounter]['quality'])/10;
		$data['status'] = "OK";
		$resultcounter++;
	}
	return $data;
    }
}

