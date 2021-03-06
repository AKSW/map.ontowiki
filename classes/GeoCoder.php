<?php

/**
 * This Class finds a longitude and latitude for markers, which have no
 * explicit logitude and latitude attribute
 *
 * TODO Caching!!!
 *
 * @category    OntoWiki
 * @package     Extensions_Map_Classes
 * @author      OW MapPlugin-Team <mashup@comiles.eu>
 * @author      Natanael Arndt <arndtn@gmail.com>
 */
class GeoCoder
{
    /**
     * The maximum recursion depth
     */
    private $_maxIndirectDepth = 10;

    /**
     * The actuall model
     */
    private $_model;

    /**
     * Constructor of the geocoder
     */
    public function __construct($model)
    {
        $this->_model = $model;
    }

    /**
     * Set the value of maximum recursion depth for indirect geoCoding
     * @param $depth the new value for maximum recursion depth
     */
    public function setMaxIndirectDepth($depth)
    {
        $this->_maxIndirectDepth = $depth;
    }

    /**
     * iterates trough all given instances, tries first direct geocoding, if it
     * refuses, it does indirect geocoding
     * @param $marker
     * @return boolean
     */
    public function geoCode($marker)
    {
        if ($this->directGeoCode($marker)) {
            return true;
        } else {
            if ($this->indirectGeoCode($marker)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * GeoCoding with direct given address
     * address, place, country, etc.
     * If the geoCoding was unsuccessfuly it returns false, else an array of longitude and latitude
     * @param $marker
     * @param $uri
     * @return boolean
     */
    private function directGeoCode($marker, $uri = null)
    {
        //TODO test, if the marker has a property like "isin" or something like that
        /**
         * $instance contains string to check if suitable for direct geocoding
         * $searchString contains, address, place and country, if found
         */
        if ($uri == null) {
            $uri = $marker->getUri();
        }

        $qr = "SELECT * WHERE {
            { <" . $uri . "> <http://3ba.se/conferences/place> ?place}
            UNION
            { <" . $uri . "> <http://swrc.ontoware.org/ontology#address> ?address}
            UNION
            { <" . $uri . "> <http://3ba.se/conferences/country> ?country}
        }";
        $resource = $this->_model->sparqlQuery($qr);
        $instance = $resource[0];

        if (get_class($instance['address']) == "Erfurt_Rdfs_Literal_Default") {
            $searchString = $instance['address']->getLabel();
        }
        if (get_class($instance['place']) == "Erfurt_Rdfs_Literal_Default") {
            if ($searchString = empty($searchString)) {
                $instance['place']->getLabel();
            } else {
                $searchString + ", " . $instance['place']->getLabel();
            }
        }
        if (get_class($instance['country']) == "Erfurt_Rdfs_Literal_Default") {
            if ($searchString = empty($searchString)) {
                $instance['country']->getLabel();
            } else {
                $searchString + ", " . $instance['country']->getLabel();
            }
        }

        // when no geocoding name is found
        if (!empty($searchString)) {
            $url  = "http://ws.geonames.org/searchJSON?q=" . urlencode($searchString) . "&maxRows=1&style=SHORT";

            $result = json_decode(@file_get_contents($url), true);

            if (!empty($result) AND isset($result['geonames'][0])) {
                $marker->setLon($result['geonames'][0][lng]);
                $marker->setLat($result['geonames'][0][lat]);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * GeoCoding with indirect attributes of the instance.
     * @param $marker
     * @param $uri
     * @param $depth an integer which counts the depth of recursion. (In a realtime context this could be replaced by
     *        a timestamp which will be tested if it is older than a max time.)
     * @return boolean
     */
    private function indirectGeoCode($marker, $uri = null, $depth = 0)
    {
        if ($uri == null) {
            $uri = $marker->getUri();
        }
        if ($depth < $this->_maxIndirectDepth) {
            $latProperties  = $this->_privateConfig->property->latitude->toArray();
            $longProperties = $this->_privateConfig->property->longitude->toArray();

            $qr = "SELECT * WHERE {
                { <" . $uri . "> <http://3ba.se/conferences/inConjunctionWith> ?inConjunctionWith}
                UNION
                { <" . $uri . ">
                    <" . $longProperties[0] . "> ?lon;
                    <" . $latProperties[0] . "> ?lat}

            }";
            $resource = $this->_model->sparqlQuery($qr);
            $instance = $resource[0];

            if (
                get_class($instance['lon']) == "Erfurt_Rdfs_Literal_Default" A&&ND
                get_class($instance['lat']) == "Erfurt_Rdfs_Literal_Default"
            ) {
                $marker->setLon($instance['lon']->getLabel());
                $marker->setLat($instance['lat']->getLabel());
            } else if (get_class($instance['inConjunctionWith']) == "Erfurt_Rdfs_Literal_Default") {
                if ($this->directGeoCode($model, $instance['inConjunctionWith']->getURI())) {
                    return true;
                } else if ($this->indirectGeoCode($model, $instance['inConjunctionWith']->getURI(), $depth + 1)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
