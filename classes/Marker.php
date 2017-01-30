<?php

/**
 * Marker-Class of the OW MapPlugin
 *
 * a Marker is an object representing an ontology object. This marker object
 * will later be shown on a map.
 *
 * @category OntoWiki
 * @package Extensions_Map_Classes
 * @author Natanael Arndt <arndtn@gmail.com>
 * @author OW MapPlugin-Team <mashup@comiles.eu>
 */
class Marker
{
    /**
     * The geo location (normaly on the earth)
     */
    public $longitude, $latitude;

    /**
     * The resource identifier of the resource represented by this marker
     */
    public $uri;

    /**
     * status properties indeicating, if a marker is on the Map (visibility) and if a marker is clustered and
     * represented by a cluster on the map
     */
    private $_onMap;
    private $_inCluster;

    //public $isCluster;

    /**
     *  The url of the icon for the marker
     */
    public $icon;

    /**
     * Constructor of a Marker object.
     * @param $uri uri of the resource, which is represented by the marker
     */
    public function __construct($uri)
    {
        $this->uri = $uri;
    }

    /**
     * Gets the uri of the object, which is represented by the marker.
     * @return uri of the object, which is represented by the marker
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Sets the _onMap-attribute, which indicates whether the marker is already
     * added to the map.
     * @param $onMapIn indicates whether the marker is already added to the map
     */
    public function setOnMap($onMapIn)
    {
        $this->_onMap = $onMapIn;
    }

    /**
     * Gets the _onMap-attribute, which indicates whether the marker is already
     * added to the map.
     * @return marker is already added to the map
     */
    public function getOnMap()
    {
        return $this->_onMap;
    }

    /**
     * Sets the _inCluster-attribute, which indicates whether the marker is
     * contained in a cluster.
     * @param $inClusterIn indicates whether the marker is contained in a
     * cluster
     */
    public function setInCluster($inClusterIn)
    {
        $this->_inCluster = $inClusterIn;
    }

    /**
     * Gets the _inCluster-attribute, which indicates whether the marker is
     * contained in a cluster.
     * @return marker is contained in a cluster
     */
    public function getInCluster()
    {
        return $this->_inCluster;
    }

    /**
     * Sets the longitude of the marker.
     * @param $lon longitude of the marker
     */
    public function setLon($lon)
    {
        $this->longitude = $lon;
    }

    /**
     * Sets the latitude of the marker.
     * @param $lon latitude of the marker
     */
    public function setLat($lat)
    {
        $this->latitude = $lat;
    }

    /**
     * Gets the longitude of the marker.
     * @return longitude of the marker
     */
    public function getLon()
    {
        return $this->longitude;
    }

    /**
     * Gets the latitude of the marker.
     * @return latitude of the marker
     */
    public function getLat()
    {
        return $this->latitude;
    }

    /**
     * Sets the icon of the marker.
     * @param $icon of the marker
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    /**
     * Gets the icon of the marker.
     * @return icon of the marker
     */
    public function getIcon()
    {
        return $this->icon;
    }

}
