<?php

/**
 * Bing geocoding service implementation for Geocoding component.
 * Service Documentation: http://msdn.microsoft.com/en-us/library/cc980922.aspx
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @version $Id$
 * TODO comments
 */

class BingMaps
{
    // get an application key from https://www.bingmapsportal.com/application/
    private $_key = 'Ala_WCtgL2SKazDtEPUs5DntE5AgGxkk_6GVfC5qai7IxSR8ZrpjvBFOaP_sAjbX';
    private $_server = 'http://dev.virtualearth.net/webservices/v1';

    function geocode($query, $n = 1)
    {
        $client = new SoapClient($this->_server . '/geocodeservice/geocodeservice.svc?wsdl');
        $request = $this->request($query, $n);
        $result = $client->Geocode($request);
        return $result->GeocodeResult->Results->GeocodeResult;
    }

    private function request($query, $n)
    {
        return array(
            'request' => array(
                'Credentials' => array('ApplicationId' => $this->_key),
                'Query' => $query,
                'Options' => array('Count' => $n),
            )
        );
    }
}


class GeocoderService_Bing implements GeocoderService
{
    public function servicepollaction ($searchstring, $searchstringShort, $uri)
    {
        // Nice string for the geocoding service
        $data['label'] = "Bing Maps";

        /* Bing geocoding */
        $api = new BingMaps();
        $result = $api->geocode($searchstring);

        //Counter for multiple result entries
        $resultcounter = 0;

        // TODO: fix API, xml-layout seams to has changed
        while (isset($result->Locations->GeocodeLocation[$resultcounter]->Latitude) && $resultcounter < 10 ) {
            $data[$resultcounter]['name'] = $result->Address->FormattedAddress;
            $data[$resultcounter]['lat'] = $result->Locations->GeocodeLocation[$resultcounter]->Latitude;
            $data[$resultcounter]['lon'] = $result->Locations->GeocodeLocation[$resultcounter]->Longitude;
            switch ($result->Locations->GeocodeLocation[$resultcounter]->CalculationMethod) {
                case "Interpolation":
                    $data[$resultcounter]['accuracy'] = 5;
                    break;
                case "InterpolationOffset":
                    $data[$resultcounter]['accuracy'] = 6;
                    break;
                case "Parcel":
                    $data[$resultcounter]['accuracy'] = 7;
                    break;
                case "Rooftop":
                    $data[$resultcounter]['accuracy'] = 8;
                    break;
                default:
                    $data[$resultcounter]['accuracy'] = 4;
            }
            $data[$resultcounter]['service_accuracy']  = $result->Confidence
                . " (" . $result->Locations->GeocodeLocation[$resultcounter]->CalculationMethod . ")";
            $data['status'] = "OK";
            $resultcounter++;
        }
        if (empty($result)) {
            $data['status'] = "No data returned";
        }
        return $data;
    }
}
