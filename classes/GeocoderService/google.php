<?php

/**
 * Google geocoding service implementation for Geocoding component.
 * Service Documentation: http://code.google.com/apis/maps/documentation/geocoding/
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @version $Id$
 * TODO comments
 */
class GeocoderService_Google implements GeocoderService
{
    public function servicepollaction ($searchstring, $searchstringShort, $uri)
    {
        // Nice string for the geocoding service
        $data['label'] = "Google";

        // Build the URL to send the geocoding request to
        //$url  = "http://maps.google.com/maps/api/geocode/json?region=nl&sensor=false&address=";
        $url  = "http://maps.google.com/maps/api/geocode/json?sensor=false&address=";
        $url .= urlencode($searchstring);

        // Send the request
        $result = json_decode(@file_get_contents($url), true);

        //Counter for multiple result entries
        $resultcounter = 0;

        while (isset($result['results'][$resultcounter]) && $resultcounter < 10) {
            $data[$resultcounter]['name'] = $result['results'][$resultcounter]['formatted_address'];
            $data[$resultcounter]['lat'] = $result['results'][$resultcounter]['geometry']['location']['lat'];
            $data[$resultcounter]['lon'] = $result['results'][$resultcounter]['geometry']['location']['lng'];
            $data[$resultcounter]['service_accuracy'] = $result['results'][$resultcounter]['geometry']['location_type'];
            switch ($data[$resultcounter]['service_accuracy']) {
                case "ROOFTOP":
                    $data[$resultcounter]['accuracy'] = "8";
                    break;
                case "RANGE_INTERPOLATED":
                    $data[$resultcounter]['accuracy'] = "7";
                    break;
                case "GEOMETRIC_CENTER":
                    $data[$resultcounter]['accuracy'] = "5";
                    break;
                case "APPROXIMATE":
                    $data[$resultcounter]['accuracy'] = "4";
                    break;
                default:
                    $data[$resultcounter]['accuracy'] = $data[$resultcounter]['service_accuracy'];
            }
            $data['status'] = $result['status'];
            $resultcounter++;
        }
        if (empty($result)) {
            $data['status'] = "No data returned";
        }
        return $data;
    }
}
