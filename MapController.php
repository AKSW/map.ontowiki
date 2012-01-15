<?php

/**
 * Map, Geocode, Geoservices and Geocoding component controller.
 *
 * @category OntoWiki
 * @package OntoWiki_extensions_components_map
 * @author Natanael Arndt <arndtn@gmail.com>
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * TODO comments
 */

require_once ('classes/GeocodingResult.php');
require_once ('classes/GeocoderService.php');

class MapController extends OntoWiki_Controller_Component
{

    private $_model;
    private $_resource      = null;
    private $_store;
    private $_listHelper    = null;
    private $_instances     = null;
    private $_resources     = null;
    private $_resourceVar   = 'resource';
    private $_translate;

    public static $maxResources = 1000;

    public function init()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('Initializing MapPlugin Controller');

        parent::init();

        $resource = $this->_owApp->selectedResource;
        if (is_object($resource) && $resource instanceof Erfurt_Rdf_Resource) {
            $this->_resource = $resource->getIri();
        }
        $this->_model       = $this->_owApp->selectedModel; // TODO: check if model is selected before
        $this->_store       = $this->_erfurt->getStore();
        $this->_translate   = $this->_owApp->translate;
    }

    public function __call($method, $args)
    {
        $this->_forward('view');
    }

    public function displayAction()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('displayAction');
        if (isset($this->_request->inline)) {
            $inline = true;
        } else {
            $inline = false;
        }

        if ($inline == true) {
            /**
             * returns the plain map without markers, as html.
             * Markers are fetched via Ajax by means of the markerActions.
             * this function is mostly similar to the displayAction in its code.
             * I think the inlineAction will be used in the diyplaAction in the future
             */
            $this->_helper->layout->disableLayout();

            // default values from configuration
            $jsonRequestUrl = new OntoWiki_Url(
                array(
                    'controller' => 'map',
                    'action' => 'marker'
                ),
                array('single_instance')
            );
            //$jsonRequestUrl->setParam('clustering', 'off', true);
            $jsonRequestUrl->setParam('use_limit', 'on', true);
            $jsonRequestUrl->setParam('extent', '__extent__', true);

            $this->view->jsonRequestUrl   = $jsonRequestUrl;
            $this->view->componentUrlBase = $this->_componentUrlBase;
            $this->view->extent           = $this->_getMaxExtent();
            $this->view->config           = $this->_privateConfig;
            $this->view->mapId            = 'mapContainer';
            $this->view->mapVar           = 'minimap';
        } else {
            /**
             * Shows the plain map without markers.
             * Markers are fetched via Ajax by means of the markerActions.
             */
            $this->addModuleContext('main.window.map');
            $this->view->placeholder('main.window.title')->set('OntoWiki Map Component');

            $jsonRequestUrl = new OntoWiki_Url(array('controller' => 'map', 'action' => 'marker'), array());
            $jsonRequestUrl->setParam('use_limit', 'off', true);
            $jsonRequestUrl->setParam('extent', '__extent__', true);

            // Controller == 'resource' and Action == 'properties'
            if ($this->_owApp->lastRoute == 'properties') {
                $jsonRequestUrl->setParam('single_instance', 'on', true);
            }

            $this->view->jsonRequestUrl   = $jsonRequestUrl;
            $this->view->componentUrlBase = $this->_componentUrlBase;
            $this->view->extent           = $this->_getMaxExtent();
            $this->view->config           = $this->_privateConfig;
            $this->view->mapId            = 'mainMap';
            $this->view->mapVar           = 'manager';

            $this->_owApp->logger->debug(
                'MapComponent/displayAction: maximal map extention: ' . var_export($this->view->extent, true)
            );
        }
    }

    /**
     * Retrieves map markers for the current resource and sends a JSON array with markers
     */
    public function markerAction()
    {
        require_once $this->_componentRoot . 'classes/Marker.php';
        require_once $this->_componentRoot . 'classes/Clusterer.php';
        //        require_once $this->_componentRoot . 'classes/GeoCoder.php';

        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        if (isset($this->_request->extent)) {
            //$extent = $this->getParam('extent', true);
            $extent   = explode(",", $this->_request->extent);
            $viewArea = array(
                    "top"    => $extent[0],
                    "right"  => $extent[1],
                    "bottom" => $extent[2],
                    "left"   => $extent[3]);
        } else {
            $viewArea = array(
                    "top"    => 90,
                    "right"  => 180,
                    "bottom" => -90,
                    "left"   => -180 );
        }

        if ($this->_resources === null) {
            $this->_getResources($viewArea);
        }

        $markers = array();

        if ($this->_resources) {

            foreach ($this->_resources as $r) {

                /**
                 * for single instances
                 * @var String contains the uri of the current property
                 */
                $uri = isset($r[$this->_resourceVar]) ? $r[$this->_resourceVar] : $this->_resource;

                if (empty ($r['lat']) || empty ($r['long'])) {
                    if (isset($r['lat2']) && isset($r['long2']) && !empty($r['lat2']) && !empty($r['long2'])) {
                        $lat = $r['lat2'];
                        $long = $r['long2'];
                    }
                } else {
                    $lat = $r['lat'];
                    $long = $r['long'];
                }

                if (!empty($lat) && !empty($long)) {
                    $marker = new Marker($uri);
                    $marker->setLat($lat);
                    $marker->setLon($long);
                    $marker->setIcon(null);

                    $markers[] = $marker;
                }
                unset($lat);
                unset($long);
            }

            /**
             * cluster the markers
             */
            if ($this->_request->clustering != 'off') {
                $clustererGridCount = $this->_privateConfig->clusterer->gridCount;
                $clustererMaxMarkers = $this->_privateConfig->clusterer->maxMarkers;

                $clusterer = new Clusterer($clustererGridCount, $clustererMaxMarkers);
                $clusterer->setViewArea($viewArea);
                $clusterer->setMarkers($markers);
                $clusterer->ignite();
                $markers = $clusterer->getMarkers();
            }
        }

        $this->_owApp->logger->debug(
            'MapComponent/markerAction responds with '
            . count($markers)
            . ' Markers in the viewArea: '
            . var_export($viewArea, true)
        );

        // $this->_response->setHeader('Content-Type', 'application/json', true);
        $this->_response->setBody(json_encode($markers));
    }

    /**
     * TODO implement this function
     */
    public function configAction()
    {
        $this->view->placeholder('main.window.title')->set('OntoWiki Map Extension Configuration');
        // this function gets and sends some persistent configuration values
        // $this->view->OpenLayersVersion = JavaScript, does this
        $this->view->componentUrlBase = $this->_componentUrlBase;
        $this->view->apikey = $this->_privateConfig->apikey;

        $this->view->config = $this->_privateConfig;
        $this->view->configArray = $this->_privateConfig->toArray();
    }

    /**
     * give a boundingbox, to generate a filter, which contains only markers within this box
     */
    public function filterAction()
    {
        /**
         * read configuration
         */
        $latProperties  = $this->_privateConfig->property->latitude->toArray();
        $longProperties = $this->_privateConfig->property->longitude->toArray();
        $latProperty    = $latProperties[0];
        $longProperty   = $longProperties[0];

        /*
         * Find the used datatypes
         */
        $datatypes = array();

        /**
         * Get the bounds in which the filtert markers should be
         * @var array with numbers
         */
        $bounds = array();
        $bounds['top'] = $this->_request->getParam('top', null);
        $bounds['rgt'] = $this->_request->getParam('right', null);
        $bounds['btm'] = $this->_request->getParam('bottom', null);
        $bounds['lft'] = $this->_request->getParam('left', null);

        $dttyps = array();
        foreach ($datatypes as $datatype) {

            $bnd = array();

            foreach ($bounds as $key => $_bnd) {
                $bnd[$key] = new Erfurt_Sparql_Query2_RDFLiteral($_bnd, $datatype);
            }

            $bnd['top'] = new Erfurt_Sparql_Query2_Smaller('?lat', $bnd['top']);
            $bnd['rgt'] = new Erfurt_Sparql_Query2_Smaller('?long', $bnd['rgt']);
            $bnd['btm'] = new Erfurt_Sparql_Query2_Larger('?lat', $bnd['btm']);
            $bnd['lft'] = new Erfurt_Sparql_Query2_Larger('?long', $bnd['lft']);
            $dttyps[]   = new Erfurt_Sparql_Query2_ConditionalAndExpression($bnd);
        }
        $filter = new Erfurt_Sparql_Query2_Filter(new Erfurt_Sparql_Query2_ConditionalOrExpression($dttyps));

        $this->_session->instances->addTripleFilter($filter, "mapBounds");
    }


    /**
     * Polls geocoding services for location information using the ressource's address as query and using JSON requests
     * from: GeoserviceController
     */
    public function geocodeAction()
    {
        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_owApp->logger->debug('Geocoding/geocodeAction action called');

        $searchString = $searchStringShort = $this->_request->q;

        $uri = $this->_resource;
        if (empty($searchString)) {
            /**
             * $instance contains string to check if suitable for direct geocoding
             * $searchString contains, address, city and country, if found
             * $searchStringShort city and country only
             */

            /**
             * Read property values from module's configuration
             */
            $streetProperties      = $this->_privateConfig->property->street->toArray();
            $housenumberProperties     = $this->_privateConfig->property->housenumber->toArray();
            $cityProperties      = $this->_privateConfig->property->city->toArray();
            $countryProperties     = $this->_privateConfig->property->country->toArray();
            $streetProperty        = $streetProperties[0];
            $housenumberProperty       = $housenumberProperties[0];
            $cityProperty        = $cityProperties[0];
            $countyProperty       = $countryProperties[0];

            $qr = "SELECT * WHERE {
            { <" . $uri . "> <" . $streetProperty . "> ?street}
            OPTIONAL
            { <" . $uri . "> <" . $housenumberProperty . "> ?housenumber}
            OPTIONAL
            { <" . $uri . "> <" . $cityProperty . "> ?city}
            OPTIONAL
            { <" . $uri . "> <" . $countryProperty . "> ?country}
        }";
            $resource = $this->_model->sparqlQuery($qr);
            $instance = $resource[0];

            $searchString = $instance['street'];
            $searchString = empty($searchString) ?
                $instance['housenumber'] :
                $searchString . " " . (string)$instance['housenumber'];

            // Use the TitleHelper to get the actual strings for cities and countries
            //require_once 'OntoWiki/Model/TitleHelper.php';
            $titleHelper = new OntoWiki_Model_TitleHelper($this->_model);
            $titleHelper->addResource($instance['city']);
            $titleHelper->addResource($instance['country']);

            $searchStringShort = $titleHelper->getTitle($instance['city']);
            $searchStringShort .= ", " . $titleHelper->getTitle($instance['country']);

            $searchString .= ", " . $searchStringShort;
        }

        // Create the singleton for the resultset
        $geocodingResult = GeocodingResult::getInstance();

        // Check which geocoders are available
        foreach ($this->_privateConfig->active_geocoders->toArray() as $geocoder) {
            require_once $this->_componentRoot . 'classes/GeocoderService/' . ucfirst($geocoder) . '.php';

            eval('$geocoderService = new GeocoderService_' . ucfirst($geocoder) . ';');
            $geocoderResult = $geocoderService->servicepollaction($searchString, $searchStringShort, $uri);
            if ($geocoderResult) {
                //array_push($data, $geocoderResult);
                $geocodingResult->pushData($geocoderResult);
            }
        }

        // $this->_response->setHeader('Content-Type', 'application/json', true);
        $this->_response->setBody(json_encode($geocodingResult->getResultset()));

        OntoWiki::getInstance()->session->gcResult[$uri] = $geocodingResult->getResultset();

        return true;
    }

    /**
     * Sends the previously geocoded markerdata for an uri in a map-component compatible format
     * from: GeoserviceController
     */
    public function getmarkerAction()
    {
        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_owApp->logger->debug('Geocoding/getmarkerAction action called');

        // Marker-class from Map-component to create set of markers
        include('extensions/components/map/classes/Marker.php');
        $markers = array();

        foreach (OntoWiki::getInstance()->session->gcResult[$this->_resource] as $gcResult) {
            $marker = new Marker($this->_resource . "/" . $gcResult['label']);
            $marker->setLat($gcResult[0]['lat']);
            $marker->setLon($gcResult[0]['lon']);
            $marker->setIcon(null);
            $markers[] = $marker;
        }

        $this->_response->setBody(json_encode($markers));
    }

    /**
     * Saves lat/lon data passed in request parameter "coordinates" into the model
     * from: GeoserviceController
     */
    public function storecoordsAction()
    {
        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_owApp->logger->debug('Geocoding/storecoordsAction action called');

        if ($this->_model->isEditable()) {
            $uri = $this->_resource;

            $longitudeProps = $this->_privateConfig->property->longitude->toArray();
            $latitudeProps = $this->_privateConfig->property->latitude->toArray();
            $accuracyProps = $this->_privateConfig->property->accuracy->toArray();

            $coordinates = $this->_request->coordinates;
            $coordinatearray = explode(",", $coordinates);

            $predicates = array();
            $longitude = array('value' => $coordinatearray[1], 'type' => 'literal', 'datatype' => "xsd:float");

            $latitude = array('value' => $coordinatearray[0], 'type' => 'literal', 'datatype' => "xsd:float");

            $accuracy = array('value' => $this->_request->accuracy, 'type' => 'literal','datatype' => "xsd:float");

            $predicates[$longitudeProps[0]][] = $longitude;
            $predicates[$latitudeProps[0]][] = $latitude;
            $predicates[$accuracyProps[0]][] = $accuracy;
            $statementsAdd = array( $uri => $predicates );

            $versioning                 = $this->_erfurt->getVersioning();
            $actionSpec                 = array();
            $actionSpec['type']         = 666;
            $actionSpec['modeluri']     = (string) $this->_owApp->selectedModel;
            $actionSpec['resourceuri']  = $uri;

            $versioning->startAction($actionSpec);
            $result = $this->_model->deleteMatchingStatements($uri, $longitudeProps[0], null);
            $result = $this->_model->deleteMatchingStatements($uri, $latitudeProps[0], null);
            $result = $this->_model->deleteMatchingStatements($uri, $accuracyProps[0], null);
            $result = $this->_model->addMultipleStatements($statementsAdd);
            $versioning->endAction($actionSpec);

            // This variant using SPARQL Update language did not work reliable
            /*  $modelUri = $this->_owApp->selectedModel->getModelIri();

            $sparULstring = 'INSERT DATA INTO <' . $modelUri . '> {<'
                . $uri . '> <' . $longitudeProps[0] . '> "' . $coordinatearray[1]
                . '" .} DELETE DATA FROM <' . $modelUri . '> {<' . $uri . '> <' . $longitudeProps[0] . '> <*> .}';

            $url = $this->_config->urlBase . "update/?query=" . urlencode($sparULstring);

            $output = @file_get_contents($url);
            */

            $output = array('status' => 'OK');
            $this->_response->setBody(json_encode($output));
        } else {
            $output = array('status' => 'ERROR', 'message' => 'permission denied');
            $this->_response->setBody(json_encode($output));
        }

        return true;
    }


    /**
     * Get the markers in the specified area
     * TODO implement using the viewArea
     */
    private function _getResources( $viewArea = false )
    {
        /**
         * read configuration
         */
        $latProperties  = $this->_privateConfig->property->latitude->toArray();
        $longProperties = $this->_privateConfig->property->longitude->toArray();
        $latProperty    = $latProperties[0];
        $longProperty   = $longProperties[0];

        if ($this->_request->single_instance != 'on') {
            $latVar         = new Erfurt_Sparql_Query2_Var('lat');
            $longVar        = new Erfurt_Sparql_Query2_Var('long');

            //the future was yesterday
            if ($this->_instances === null) {

                if ($this->_listHelper == null) {
                    // get listHelper
                    $this->_listHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
                }

                $listName = "instances";
                if ($this->_listHelper->listExists($listName)) {
                    $list = $this->_listHelper->getList($listName);
                    $this->_owApp->logger->debug(
                        'MapComponent/_getResources: clone "' . $listName . '"-list from listHelper'
                    );
                    $this->_instances = clone $list;
                } else {
                    //error
                    $this->_owApp->logger->error(
                        'MapComponent/_getResources: list "' . $listName .'" doesn\'t exist in listHelper'
                    );
                    //$this->_instances = new QueryObject();
                    $this->_instances = null;
                }
            } else {
                $this->_owApp->logger->debug('MapComponent/_getResources: this->_instances already set');
                // don't load instances again
            }

            if ($this->_request->use_limit == 'off') {
                $this->_instances->setLimit(self::$maxResources);
                $this->_instances->setOffset(0);
            } else {
                // use the limit and offset set in the instances
            }

            $query = $this->_instances->getResourceQuery();

            $query->removeAllOptionals()->removeAllProjectionVars();

            $query->addProjectionVar($this->_instances->getResourceVar());
            $query->addProjectionVar($latVar);
            $query->addProjectionVar($longVar);

            $queryOptionalCoke     = new Erfurt_Sparql_Query2_OptionalGraphPattern();
            $queryOptionalPepsi    = new Erfurt_Sparql_Query2_OptionalGraphPattern();

            // should be $node = new Erfurt_Sparql_Query2_BlankNode('bn') but I heard this is not supported by zendb
            $node = new Erfurt_Sparql_Query2_Var('node');
            $queryOptionalCoke->addTriple($this->_instances->getResourceVar(), $latProperty, $latVar);
            $queryOptionalCoke->addTriple($this->_instances->getResourceVar(), $longProperty, $longVar);
            $queryOptionalPepsi->addTriple(
                $this->_instances->getResourceVar(),
                new Erfurt_Sparql_Query2_Var('pred'),
                $node
            );
            $queryOptionalPepsi->addTriple($node, $latProperty, $latVar);
            $queryOptionalPepsi->addTriple($node, $longProperty, $longVar);

            $query->setQueryType(Erfurt_Sparql_Query2::typeSelect);
            $queryDirect = clone $query;
            $queryIndire = clone $query;
            $queryDirect->addElement($queryOptionalCoke);
            $queryIndire->addElement($queryOptionalPepsi);
            $this->_owApp->logger->debug(
                'MapComponent/_getResources sent directQuery: "' . $queryDirect . '" to get markers.'
            );
            $this->_owApp->logger->debug(
                'MapComponent/_getResources sent indirectQuery: "' . $queryIndire . '" to get markers.'
            );

            /* get result of the query */
            $resourcesDir    = $this->_owApp->erfurt->getStore()->sparqlQuery($queryDirect);
            $resourcesInd    = $this->_owApp->erfurt->getStore()->sparqlQuery($queryIndire);

            $this->_resourceVar  = $this->_instances->getResourceVar()->getName();

            /**
             * merge theses two results
             */
            //$resourcesDir = $this->_cpVarToKey($resourcesDir, $this->_resourceVar);
            //$resourcesInd = $this->_cpVarToKey($resourcesInd, $this->_resourceVar);

            $this->_resources = array_merge_recursive($resourcesDir, $resourcesInd);

            /**
             * If you get problems with multiple coordinates for one resource you have to remove all array values with
             * non string-keys
             */

        } else if ($this->_request->single_instance == 'on') {
            //$query = new Erfurt_Sparql_SimpleQuery();
            $directQueryString = '
                SELECT ?lat ?long
                WHERE {
                    <' . $this->_resource . '> <' . $latProperty . '> ?lat;
                    <' . $longProperty . '> ?long.
                }';
            $indireQueryString = '
                SELECT ?lat ?long
                WHERE {
                    <' . $this->_resource . '> ?p ?node.
                        ?node <' . $latProperty . '> ?lat;
                    <' . $longProperty . '> ?long.
                }';
            $this->_owApp->logger->debug('MapComponent/_getResources direct query "' . $directQueryString . '".');
            $this->_owApp->logger->debug('MapComponent/_getResources indirect query "' . $indireQueryString . '".');
            $queryDirect = Erfurt_Sparql_SimpleQuery::initWithString($directQueryString);
            $queryIndire = Erfurt_Sparql_SimpleQuery::initWithString($indireQueryString);

            /* get result of the query */
            $this->_resources   = $this->_owApp->erfurt->getStore()->sparqlQuery($queryDirect);

            if (empty($this->_resources[0]['lat']) OR empty($this->_resources[0]['long'])) {
                $this->_resources = $this->_owApp->erfurt->getStore()->sparqlQuery($queryIndire);
            }

        } else {
            $this->_owApp->logger->debug(
                'MapComponent/_getResources request single_instace contains neither "on" nor "off"'
            );
        }
    }

    /**
     * Calculates the maximum distance of the markers, to get the optimal viewArea/extent for initial map view.
     * This function has many code duplicats, needs a rework.
     * @return array {"top" (max. lat.), "right"  (max. long.), "bottom" (min. lat.), "left" (min. long.)}
     */
    private function _getMaxExtent ()
    {

        if ($this->_resources === null) {
            $this->_getResources();
        }

        $lat = array();
        $long = array();
        foreach ($this->_resources as $r) {
            if (!empty($r['lat'])) {
                $lat[] = $r['lat'];
            }
            if (!empty($r['lat2'])) {
                $lat[] = $r['lat2'];
            }
            if (!empty($r['long'])) {
                $long[] = $r['long'];
            }
            if (!empty($r['long2'])) {
                $long[] = $r['long2'];
            }
        }

        if (count($lat) > 0 AND count($long) > 0) {
            $return = array(
                    "top"    => max($lat),
                    "right"  => max($long),
                    "bottom" => min($lat),
                    "left"   => min($long)
                    );
        } else {
            /**
             * set default possition, if no resource is selected
             */
            $return = array(
                    "top"    => $this->_privateConfig->default->latitude,
                    "right"  => $this->_privateConfig->default->longitude,
                    "bottom" => $this->_privateConfig->default->latitude,
                    "left"   => $this->_privateConfig->default->longitude
                    );
        }

        $this->_owApp->logger->debug('MapComponent/_getMaxExtent: extent: ' . var_export($return, true));

        return $return;
    }

    /**
     * Copies a uri from its value field in the resultset to the key of the array-element.
     * The $key identifies the key to the uri.
     * $array = array(
     *  0 => array(
     *      'resourceUri' => 'http://comiles.eu/~natanael/foaf.rdf#me',
     *      'long' => '12.3456',
     *      'lat' => '12.3456'
     *  )
     * );
     * $key = 'resourceUri';
     *
     * will become
     *
     * $array = array(
     *  'http://comiles.eu/~natanael/foaf.rdf#me' => array(
     *      'resourceUri' => 'http://comiles.eu/~natanael/foaf.rdf#me',
     *      'long' => '12.3456',
     *      'lat' => '12.3456'
     *  )
     * );
     *
     * @param array $array The Resultset, which is returned by a sparqlquery
     * @param String $key of the array element holding the URI
     */
    private function _cpVarToKey($array, $key)
    {
        for ($i = 0; $i < count($array); $i++) {
            if (isset($array[$array[$i][$key]])) {
                $array[$array[$i][$key]] = array_merge($array[$array[$i][$key]], $array[$i]);
            } else {
                $array[$array[$i][$key]] = $array[$i];
            }
            unset($array[$i]);
        }
    }
}

