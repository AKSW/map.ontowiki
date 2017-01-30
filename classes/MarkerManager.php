<?php

/**
 * MarkerManager-Class of the OW MapPlugin
 *
 * @category OntoWiki
 * @package Extensions_Map_Classes
 * @author Natanael Arndt <arndtn@gmail.com>
 * @author OW MapPlugin-Team <mashup@comiles.eu>
 * TODO comments
 */
class MarkerManager
{
    private $_markers = array();
    private $_icons = array();
    private $_model;

    /**
     * Maybe we should put the createMarkers() and/or createEdges() function
     * into the constructor or call them from the constructor.
     * @param $model    the URI to the actual selected model
     * @param $config
     * @param $erfurt   the erfurt model
     */
    public function __construct($model, $config, $erfurt)
    {
        $this->_model = $model;

        // get the marker & cluster icons from the MapPlugin knowledgebase
        // the places we look for them ( the order is important)
        /*
           $places[] = "http://ns.aksw.org/MapPlugin/";
        //      $places[] = "http://localhost/OntoWiki/Config/";
        $places[] = $this->_model->getModelURI();
        foreach ($places as $p){
        // get all icons we can find, we dont distinguish between instance and class
        $query = new Erfurt_Sparql_SimpleQuery();
        $query->setSelectClause('SELECT ?instance ?icon');
        $query->addFrom($p);
        $query->setWherePart('WHERE { ?instance <http://xmlns.com/foaf/0.1/depiction> ?icon }');
        $endpoint = new Erfurt_Sparql_Endpoint_Default($this->config);

        //              $endpoint -> setQuery($query);
        //              $endpoint -> addModel($p);
        //              $endpoint -> setRenderer('Default');

        $rs = $store->sparqlQuery($query);

        foreach ( $rs as $r){
        $this->_icons[$r['?inst']->getURI()] = $r['?icon']->getLabel();
        }
        }
         */

        $actionConfig = $erfurt->getAc()->getActionConfig('MapPlugin');
        $this->_icons['default'] = $actionConfig['defaultIcon'];
        $this->_icons['cluster'] = $actionConfig['clusterIcon'];
    }

    /**
     * Calculates which markers to return
     * @param $viewArea representation of the piece of the world we are currently looking at the moment
     * @return array of markers which are supposed to be displayed on the map
     */
    public function getMarkers( $viewArea, $clusterOn = true, $clustGridCount = 3, $clustMaxMarkers = 2 )
    {
        /**
         * check if all 4 viewArea values are present, else set the viewArea to the bestViewArea
         */
        if (!isset($viewArea[3])) {
            $viewArea = $this->getBestViewArea();
        }
        $top    = $viewArea[0];
        $right  = $viewArea[1];
        $bottom = $viewArea[2];
        $left   = $viewArea[3];
        //if ($top < $bottom ) { $tmp = $top; $top = $bottom; $bottom = $tmp; }
        //if ($right < $left ) { $tmp = $right; $right = $left; $left = $tmp; }
        //we don't care about the date line
        //why don't we need this? (Natanael)
        $viewArea = array( "top" => $top, "right" => $right, "bottom" => $bottom, "left" => $left  );

        /**
         * remove all Markers outside the viewArea from $this->_markers
         */
        $markersVisible = array();
        for ($i = 0; $i < count($this->_markers); $i++) {
            if (
                $this->_markers[$i]->getLat() < $viewArea['top'] &&
                $this->_markers[$i]->getLat() > $viewArea['bottom'] &&
                (
                    (
                        $this->_markers[$i]->getLon() < $viewArea['right'] &&
                        $this->_markers[$i]->getLon() > $viewArea['left']
                    ) || (
                        $viewArea['left'] > $viewArea['right'] &&
                        (
                            $this->_markers[$i]->getLon() < $viewArea['right'] ||
                            $this->_markers[$i]->getLon() > $viewArea['left']
                        )
                    )
                )
            ) {
                $markersVisible[] = &$this->_markers[$i];
            }
        }

        /**
         * check if the cluster is switched on, if so create a cluster and cluster the markers
         * else do nothing and return the markers
         */
        if ($clusterOn) {
            /**
             * Instantiate a new Clusterer object
             */
            $clusterer = new Clusterer($clustGridCount, $clustMaxMarkers);
            $clusterer->setViewArea($viewArea);
            $clusterer->setMarkers($markersVisible);//$this->_markers);
            $clusterer->ignite();
            $markersVisible = $clusterer->getMarkers();
        }
        return $markersVisible;
    }

    /**
     * this function creates the markers from the ontology specified by the $uri
     * @param $uri
     * @param $filter
     */
    public function createMarkers($uri, $filter)
    {
        // create a GeoCode object used to GeoCode Markers
        $geoCoder = new GeoCoder($this->_model);
        // stores every SPARQL-Query we want to make and the icon which belongs to the class
        $output = array();
        // stores all markers
        $markerArray = array();

        $uriList = array();
        if ($uri != $this->_model->getModelURI()) {
            // will give us only those instances which belong to $uri and
            // contain lat. and long. display one instance
            // has $uri a superclass ?
            $instQuery = 'SELECT * WHERE { <'. $uri .'> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?super}';
            // has $uri a instances
            $testQrInst = 'SELECT * WHERE { ?inst <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <'. $uri .'> }';
            // has $uri a subclass
            $testQrSubclass = 'SELECT * WHERE { ?sub <http://www.w3.org/2000/01/rdf-schema#subClassOf> <'. $uri .'> }';
            $rs = $this->_model->sparqlQuery($instQuery);
            $rsInst = $this->_model->sparqlQuery($testQrInst);
            $rsSub = $this->_model->sparqlQuery($testQrSubclass);
            // is $uri a instance ?
            if (0 != count($rs) && 0 == count($rsInst) && 0 == count($rsSub)) {
                // display one instance
                $instQr = 'SELECT * WHERE {'
                    . ' OPTIONAL{ <' . $uri . '> <http://www.w3.org/2003/01/geo/wgs84_pos#long> ?long. }'
                    . ' OPTIONAL{ <' . $uri . '> <http://www.w3.org/2003/01/geo/wgs84_pos#lat> ?lat. }'
                    . ' <' . $uri . '> <http://www.w3.org/2000/01/rdf-schema#label> ?label;'
                    . '                <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?class.'
                    . '}';
                $output[] = $instQr;

            } else {
                //create query for class + subclasses
                $uriList[] = $uri;
                // if the list still contains unchecked URIs -> will be
                // checked for further subclasses
                for ( $i = 0; $i < sizeof($uriList); $i++ ) {
                    $checkUri = $uriList[$i];
                    $subSearch = 'SELECT * WHERE {'
                        . ' ?subclass <http://www.w3.org/2000/01/rdf-schema#subClassOf>  <' . $checkUri . '>'
                        . '}';
                    $nextSubclass = $this->_model->sparqlQuery($subSearch);
                    // add subclasses to the end of the list
                    foreach ($nextSubclass as $ns) {
                        $uriList[] = $ns['subclass']->getURI();
                    }
                }

                // get all instances related to the URIs in the uriList
                // (with the query)
                foreach ( $uriList as $uri ) {
                    $instances = '
                        PREFIX swrc: <http://swrc.ontoware.org/ontology#>
                        PREFIX wgs84_pos: <http://www.w3.org/2003/01/geo/wgs84_pos#>
                        SELECT * WHERE { ?inst <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <'. $uri .'>.
                            ?inst <http://www.w3.org/2000/01/rdf-schema#label> ?label.
                                ?inst <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?class
                                OPTIONAL{ ?inst wgs84_pos:long ?long } OPTIONAL{ ?inst wgs84_pos:lat ?lat } }';
                    $output[] = $instances;
                }
            }
        } else {// will give us all instances from the active model(knowledgbase)
            $instances = "SELECT * WHERE { ?inst <http://www.w3.org/2003/01/geo/wgs84_pos#long> ?long;
            <http://www.w3.org/2003/01/geo/wgs84_pos#lat> ?lat;
            <http://www.w3.org/2000/01/rdf-schema#label> ?label;
            <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?class.}";
            $output[] = $instances;
        }
        foreach ($output as $o) {
            // a query foreach class we found, we know the icon which belongs
            // to the class
            $qr = $this->_model->sparqlQuery($o);
            foreach ($qr as $f) {
                // we do this because the sparql-query for one instance
                // dosn't have a "?inst" (no way of getting the URI)
                if ($f['inst']) {
                    $uri = $f['inst']->getURI();
                }
                $temp = new Marker($uri);
                $temp->setLabel($f['label']->getLabel());
                // are there any icons connected to the instance ?
                // if note use the default-marker (-> else )
                if ($this->_icons[$f['class']->getURI()] || $this->_icons[$uri]) {
                    // if we have a specific icon for the instance we use it
                    // otherwise we take the icon of the class(if there is one)
                    if ($this->_icons[$uri]) {
                        $temp->setIcon($this->_icons[$uri]);
                    } else {
                        $temp->setIcon($this->_icons[$f['class']->getURI()]);
                    }
                } else {
                    // set the default marker
                    $temp->setIcon($this->_icons['default']);
                }
                if ($f['lat'] && $f['long']) {
                    $temp->setLat($f['lat']->getLabel());
                    $temp->setLon($f['long']->getLabel());
                } else {
                    $result = $geoCoder->geoCode(&$temp);
                    // $result is boolean indicating geoCode() was successfull
                }

                $markerArray[] = $temp;
            }
        }
        $this->_markers = $markerArray;
    }

    /**
     * Calculates the best view area for the given markers.
     * @return array which keeps the values for top, right, bottom and left border (in this order)
     */
    public function getBestViewArea()
    {
        if (count($this->_markers) > 0) {

            // Calculation for longitude:

            $markersTmp = array();
            $markersSorted = array();

            // Put all markers in a new temporary array
            for ($i = 0; $i < count($this->_markers); $i++) {
                $markersTmp[] = &$this->_markers[$i];
            }

            // Sort markers by longitude and store them in markersSorted
            $min = 0;
            $minIndex = 0;
            $k = 0;
            while ( $k < count($this->_markers)) {
                $min = 181;
                for ($i = 0; $i < count($this->_markers); $i++) {
                    if (isset($markersTmp[$i])) {
                        if ($markersTmp[$i]->getLon() < $min) {
                            $min = $markersTmp[$i]->getLon();
                            $minIndex = $i;
                        }
                    }
                }
                $markersSorted[] = &$markersTmp[$minIndex];
                unset($markersTmp[$minIndex]);
                $k++;
            }

            // Find maximum difference in longitude between two adjacent markers
            $max = 0;
$maxIndex = 0;
            for ($i = 0; $i < count($markersSorted) - 1; $i++) {
                if ($markersSorted[$i + 1]->getLon() - $markersSorted[$i]->getLon() > $max) {
                    $max = $markersSorted[$i + 1]->getLon() - $markersSorted[$i]->getLon();
                    $maxIndex = $i;
                }
            }
            // Don't forget the difference between the last and first marker (180? -> -180?!)
            if ($markersSorted[0]->getLon() + 360 - $markersSorted[count($markersSorted) - 1]->getLon() > $max) {
                $max = $markersSorted[0]->getLon() + 360 - $markersSorted[count($markersSorted) - 1]->getLon();
                $maxIndex = count($markersSorted) - 1;
            }

            // assign left and right border, calculate the longitude center and difference
            if ($maxIndex == count($markersSorted) - 1) {
                $right = $markersSorted[$maxIndex]->getLon();
                $left = $markersSorted[0]->getLon();
                $centerLon = ($left + $right) / 2;
                $diffLon = $right - $left;
            } else {
                $right = $markersSorted[$maxIndex]->getLon();
                $left = $markersSorted[$maxIndex + 1]->getLon();
                $centerLon = ($left + $right + 360) / 2;
                while ( $centerLon > 180) {
                    $centerLon -= 360;
                }
                $diffLon = $right + 360 - $left;
            }

            /*
               echo "\nLeft: ".$left;
               echo "\nRight: ".$right;
               echo "\nMaximale Differenz Lon: ".$max." an Stelle ".$maxIndex.":";
               for ($i = 0; $i < count($markersSorted); $i++) {
               echo "\nmarkersSorted[ ".$i."]: ".$markersSorted[$i]->getLon();
               }
             */

            // Calculation for latitude

            // Find marker with the minimal latitude -> bottom
            $bottom = $this->_markers[0]->getLat();
            for ($i = 1; $i < count($this->_markers); $i++) {
                if ($this->_markers[$i]->getLat() < $bottom) {
                    $bottom = $this->_markers[$i]->getLat();
                }
            }

            // Find marker with the maximal latitude -> top
            $top = $this->_markers[0]->getLat();
            for ($i = 1; $i < count($this->_markers); $i++) {
                if ($this->_markers[$i]->getLat() > $top) {
                    $top = $this->_markers[$i]->getLat();
                }
            }

            // Calculate the latitude center and difference
            $centerLat = ($top + $bottom) / 2;
            $diffLat = $top - $bottom;
        } else {
            // No existing marker
            $centerLat = 0;
            $centerLon = 0;
            $diffLat = 360;
            $diffLon = 180;
        }

        // Create array to return all values
        $bestViewArea = array( $top, $right, $bottom, $left );

        // $arrayCenterDiff = array( $centerLat, $centerLon, $diffLat, $diffLon );
        // print_r( $arrayCenterDiff );
        return $bestViewArea;
    }
}
