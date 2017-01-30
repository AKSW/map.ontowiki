<?php

/**
 * The Clusterer object controlls the creation of the clusters on the map
 *
 * @category OntoWiki
 * @package Extensions_Map_Classes
 * @author Natanael Arndt <arndtn@gmail.com>
 * @author OW MapPlugin-Team <mashup@comiles.eu>
 */

class Clusterer
{

    /**
     * The maximum amount of Markers in an unclustered square ==
     * The minimum amount of Markers in a Cluster
     */
    private $_maxVisibleMarkers;

    /**
     * The number of clusteringsquares in the vertical
     */
    private $_gridCount;

    /**
     * The array of all Markers
     */
    private $_markers = array();

    /**
     * The array of all Clusters
     */
    private $_clusters = array();

    /**
     * The viewabe area on the map in which we will Cluster the Markers
     */
    private $_viewArea = array();

    /**
     * The array of the resulting Markers after clustering, it contains Markers and Cluster
     */
    private $_resultingMarkers = array();

    /**
     * This is the constructor for a clusterer, which manages the creation on
     * clusters
     * @param $gridCount vertical count of cells, regulates the fineness of the
     * grid layed over the map
     * @param $maxVisibleMarkers stay unclustered in cells which contains a less
     * or equal count of markers to this value
     */
    public function __construct($gridCount, $maxVisibleMarkers)
    {
        $this->_maxVisibleMarkers = $maxVisibleMarkers;
        $this->_gridCount = $gridCount;
    }

    /**
     * Gets the array of resulting markers/clusters.
     * @return array of the resulting markers/clusters
     */
    public function getMarkers()
    {
        return $this->_resultingMarkers;
    }

    /**
     * is the function to add a marker to the Clusterer to cluster it
     * @param &$marker a reference of a marker, to be added to the Clusterer
     */
    public function addMarker(&$marker)
    {
        $this->_markers[] = $marker;
    }

    /**
     * Adds a array of markers.
     * @param &$markers array of markers, which is to add; given by reference
     */
    public function setMarkers(&$markers)
    {
        $this->_markers = $markers;
    }

    /**
     * With this function you set the viewArea of the actual map.
     * The viewArea is described by its boarders beginning from the top and going on clockwise.
     * @param $viewArea is an array of float
     */
    public function setViewArea( $viewArea )
    {
        $this->_viewArea = $viewArea;
    }

    /**
     * The gridCount tells us, how many squares should be between the left and
     * the right border of the viewArea the amount of the squares betweed top
     * and bottom will be calculated relatively
     * @param $gridCount vertical count of cells, regulates the fineness of the
     * grid layed over the map
     */
    public function setGridSize($gridCount)
    {
        if ($gridCount == 0) {
            $gridCount = 1;
        }
        $this->_gridCount = $gridCount;
    }

    /**
     * This function sets the fuse on fire.
     * It is the main Function in wich the clustering algorithem runs.
     */
    public function ignite()
    {
        /**
         * If the top boarder is below the bottom boarder they will be swaped
         */
        if ($this->_viewArea["top"] < $this->_viewArea["bottom"]) {
            $tmp = $this->_viewArea["top"];
            $this->_viewArea["top"] = $this->_viewArea["bottom"];
            $this->_viewArea["bottom"] = $tmp;
        }

        /**
         * Calculate the size of the cluster squares (cell). If the amount if markers in one cell is biger then
         * maxVisisbleMarkers all markers in this cell will be clustered
         */
        $lonInc = $latInc = ($this->_viewArea["top"] - $this->_viewArea["bottom"]) / $this->_gridCount;

        /**
         * Just a constraint
         */
        if (false && ($latInc > 0) && ($lonInc > 0)) {

            /**
             * Iterate each cell
             */
            for ($lat = $this->_viewArea["bottom"]; $lat < $this->_viewArea["top"]; $lat += $latInc ) {
                for (
                    $lon = $this->_viewArea["left"];
                    $this->between($lon, $this->_viewArea["left"], $this->_viewArea["right"]);
                    $lon = (($lon + $lonInc) < 180) ? ($lon + $lonInc) : (($lon + $lonInc) - 360)
                ) {
                    $cellViewArea = array(
                        'bottom' => $lat,
                        'top' => $lat + $latInc,
                        'left' => $lon,
                        'right' => (($lon + $lonInc) < 180) ? ($lon + $lonInc) : (($lon + $lonInc) - 360)
                    );

                    /**
                     * Create a new cluster object for the current cell.
                     */
                    $cluster = new Cluster($cellViewArea);

                    /**
                     * Check if the dateline is in this cell and tell this to the cluster.
                     */
                    $cluster->setWithDateLine(($lon > ((($lon + $lonInc) < 180) ? ($lon + $lonInc) : (($lon + $lonInc) - 360))));

                    /**
                     * Iterate all markers
                     */
                    for ($i = 0; $i < count($this->_markers); $i++) {

                        /**
                         * Check if the marker is already in a cluster and if it is in the current cell
                         */
                        if ((!$this->_markers[$i]->getInCluster())
                            &&
                            $this->isInViewArea(
                                $this->_markers[$i],
                                array(
                                    'bottom' => $lat,
                                    'top' => $lat + $latInc,
                                    'left' => $lon,
                                    'right' => (($lon + $lonInc) < 180) ? ($lon + $lonInc) : (($lon + $lonInc) - 360)
                                )
                            )
                        ) {
                            /**
                             * Add the marker to the cluster and set inCluster true.
                             */
                            $cluster->addMarker($this->_markers[$i]);
                            $this->_markers[$i]->setInCluster(true);
                        }
                    }

                    /**
                     * Check if the just created cluster reaches the minimum size
                     */
                    if ($cluster->countMarkers() <= $this->maxVisisbleMarkers) {

                        /**
                         * If it is not big enought smash it.
                         */
                        unset($cluster);
                    } else {

                        /**
                         * Else calculate latitude and longitude for the current cluster
                         * and add it to the clusters array
                         */
                        $cluster->createLonLat(true);
                        $this->_clusters[] = $cluster;
                    }
                }
            }
        }

        for ($i = 0; $i < count($this->_markers); $i++) {
            if (!$this->_markers[$i]->getInCluster()) {
                for ($j = $i + 1; $j < count($this->_markers); $j++) {
                    if (!$this->_markers[$j]->getInCluster()) {
                        if ($this->_markers[$i]->getLon() == $this->_markers[$j]->getLon()
                            && $this->_markers[$i]->getLat() == $this->_markers[$j]->getLat()
                        ) {
                            $cluster = new Cluster($this->_viewArea);
                            if (!$this->_markers[$i]->getInCluster()) {
                                $cluster->addMarker($this->_markers[$i]);
                            }
                            $cluster->addMarker($this->_markers[$j]);
                            $cluster->setIsMicroCluster(true);
                            $cluster->setUri(
                                'cluster://lat/'
                                . $this->_markers[$i]->getLat()
                                . '/long/'
                                .  $this->_markers[$i]->getLon()
                                . '/'
                            );
                            $cluster->createLonLat(true);
                            $this->_clusters[] = $cluster;
                        }
                    }
                }
            }
        }

        /**
         * Add the obtained clusters to the resulting markers.
         */
        $this->_resultingMarkers = $this->_clusters;

        /**
         * Iterate all markers
         */
        for ($i = 0; $i < count($this->_markers); $i++ ) {

            /**
             * Add the unclustered markers to the resulting markers.
             */
            if (!$this->_markers[$i]->getInCluster()) {
                $this->_resultingMarkers[] = $this->_markers[$i];
            }
        }
    }

    /**
     * Returns whether the marker is located in the view area.
     * @param &$marker marker, which is to be tested; given by reference.
     * @param $viewArea view area, which is to be tested
     * @return marker is located in the view area
     */
    private function isInViewArea( &$marker, $viewArea )
    {
        return (
                $marker->getLat() <= $viewArea['top'] AND
                $marker->getLat() >= $viewArea['bottom'] AND
                $this->between($marker->getLon(), $viewArea["left"], $viewArea["right"])
               );
    }

    /**
     * Check if a value is between a left and a right boarder. This value is normaly a longitude.
     * @param $value the value to chack, a float between -180 and 180
     * @param $left the left boarder, a float between -180 and 180
     * @param $right the right boarder, a float between -180 and 180
     * @return boolean
     */
    private function between($value, $left, $right)
    {
        return (
            ($value >= $left AND $value < $right)
             OR
            ($left > $right AND ( $value >= $left OR $value < $right))
        );
    }
}
