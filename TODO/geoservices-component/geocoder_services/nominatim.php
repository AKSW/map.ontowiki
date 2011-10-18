<?php

/**
 * Nominatim geocoding service implementation for Geocoding component.
 * Service documentation: http://wiki.openstreetmap.org/wiki/Nominatim
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @version $Id$
 * TODO comments
 */
class Nominatim_service implements Geocoder_service
{
    public function servicepollaction ($searchstring, $searchstring_short, $uri)
    {
	/*
	   If you are making large numbers of request please include a valid email address
	   or alternatively include your email address as part of the User-Agent string.
	   This information will be kept confidential and only used to contact you in the
	   event of a problem, see Usage Policy for more details.
	*/
	$contact_email = ''; // <= Enter your e-mail here

	// Nice string for the geocoding service
	$data['label'] = "Nominatim (OSM)";
        
	// Build the URL to send the geocoding request to
        $url  = "http://nominatim.openstreetmap.org/search?format=json&polygon=0&addressdetails=0&email=".$contact_email."&q=";	
	$url .= urlencode($searchstring);

	// Send the request
	$result = json_decode( @file_get_contents($url), true );
		
	//if(!empty($result) AND isset($result['results'][0])){
	//if(!empty($result)) {

	//Counter for multiple result entries
	$resultcounter = 0;	

	while (isset($result[$resultcounter]) && $resultcounter < 10 ) {
		$data[$resultcounter]['name']  = $result[$resultcounter]['display_name'];
		$data[$resultcounter]['lat'] = $result[$resultcounter]['lat'];
		$data[$resultcounter]['lon'] = $result[$resultcounter]['lon'];
		$data[$resultcounter]['accuracy'] = $result[$resultcounter]['class'] . $result[$resultcounter]['type'];

		$data[$resultcounter]['service_accuracy'] = $result[$resultcounter]['class'] . $result[$resultcounter]['type'];
		switch ($result[$resultcounter]['class']) {
		    case "highway":
		        $data[$resultcounter]['accuracy'] = "6";
			break;
                    case "place":
			if ($result[$resultcounter]['type'] == "house")
				$data[$resultcounter]['accuracy'] = "8";
			else
		        	$data[$resultcounter]['accuracy'] = "4";
			break;
                    case "boundary":
		        $data[$resultcounter]['accuracy'] = "3";
			break;
		    default:
                        $data[$resultcounter]['accuracy'] = $data[$resultcounter]['service_accuracy'];
		}
		$data['status'] = "OK";

		$resultcounter++;
	}
	if(empty($result)) {
		$data['status'] = "No data returned";
	}
	return $data;
    }
}

