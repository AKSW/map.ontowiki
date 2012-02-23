<?php

/**
 * OntoWiki module – minimap
 *
 * display a minimap of the currently visible resources (if any)
 * shows a short statistical summary about geographical data in a ressource
 *
 * @category OntoWiki
 * @package OntoWiki_extensions_components_map
 * @author Natanael Arndt <arndtn@gmail.com>
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class MapModule extends OntoWiki_Module
{
    public function init()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('Initializing MapPlugin Module');
        // TODO: fix it, cause exception
        /*    $this->_owApp->translate->addTranslation(_OWROOT . $this->_config->extensions->modules .
              $this->_name . DIRECTORY_SEPARATOR . 'languages/', null,
              array('scan' => Zend_Translate::LOCALE_FILENAME));
         */

        /**
         * From geocode module
         */
        $this->view->headScript()->appendFile($this->view->moduleUrl . 'classes/geocode.js');
        $this->view->headLink()->appendStylesheet($this->view->moduleUrl . 'css/geocode.css', 'screen');
    }

    public function getTitle()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('getTitle');
        return $this->_owApp->translate->_('Map');
    }

    // TODO: merge with geocode shouldShow code
    public function shouldShow()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('shouldShow?');
        if (class_exists('MapHelper')) {
            $helper = $this->_owApp->extensionManager->getComponentHelper('map');
            // $helper = new MapHelper($this->_owApp->extensionManager);
            return $helper->shouldShow();
        } else {
            return false;
        }
        // geostats says always true
    }

    /**
     * Returns the menu of the module
     *
     * @return string
     */
    public function getMenu()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('getMenu');
        // build main menu (out of sub menus below)
        $mainMenu = new OntoWiki_Menu();

        // edit sub menu
        if ($this->_owApp->erfurt->getAc()->isModelAllowed('edit', $this->_owApp->selectedModel) ) {

            $configUrl = new OntoWiki_Url(array('controller' => 'map', 'action' => 'config'));

            $editMenu = new OntoWiki_Menu();
            $editMenu->setEntry('Add resource at location', "javascript:minimapAddElement()");
            $editMenu->setEntry('Toggle moveable marker', "javascript:minimapToggleMoveables()");
            $editMenu->setEntry('Configuration', $configUrl->__toString());
            $mainMenu->setEntry('Edit', $editMenu);
        }

        // layer sub menu
        $layerMenu = new OntoWiki_Menu();
        $layerMenu->setEntry('Google Streets', "javascript:minimap.selectLayer('Google Streets')")
            ->setEntry('Google Hybrid', "javascript:minimap.selectLayer('Google Hybrid')")
            ->setEntry('Google Satellite', "javascript:minimap.selectLayer('Google Hybrid')")
            ->setEntry('Google Physical', "javascript:minimap.selectLayer('Google Hybrid')")
            ->setEntry('OpenStreetMaps', "javascript:minimap.selectLayer('OpenStreetMap')")
            ->setEntry('OpenStreetMaps (Tiles@Home)', "javascript:minimap.selectLayer('OpenStreetMap (Tiles@Home)')");

        // zoom sub menu
        $zoomMenu = new OntoWiki_Menu();
        $zoomMenu->setEntry('Zoom in', "javascript:minimap.zoomIn()")
            ->setEntry('Zoom out', "javascript:minimap.zoomOut()")
            ->setEntry('Zoom to elements', "javascript:minimap.zoomIdeal()")
            ->setEntry('Zoom world', "javascript:minimap.zoomMax()");

        // view sub menu
        $viewMenu = new OntoWiki_Menu();
        $viewMenu->setEntry('Layer', $layerMenu);
        $viewMenu->setEntry('Toggle Marker', "javascript:minimapToggleMarker()");
        $viewMenu->setEntry('Toggle Searchbar', "javascript:minimapToggleSearchbar()");
        $mainMenu->setEntry('View', $viewMenu);
        $mainMenu->setEntry('Zoom', $zoomMenu);

        return $mainMenu;
    }

    public function getStateId()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('getStateId');
        $id = $this->_owApp->selectedModel
            . $this->_owApp->selectedResource;

        return $id;
    }

    public function getContents()
    {
        $this->_owApp->logger->debug('getContents');

        // TODO should show geocode options only on single resource view
        return $this->getGeocodeContents() . $this->getMapContents() . $this->getGeostatsContents();
    }

    /**
     * Get the map content
     */
    public function getMapContents()
    {
        // if (isset($this->_owApp->session->instances)) {
        $this->_owApp->logger->debug('MimimapModule/getContents: lastRoute = "' . $this->_owApp->lastRoute . '".');
        if ($this->_owApp->lastRoute == 'properties') {
            $this->view->context = 'single_instance';
        } else if ($this->_owApp->lastRoute == 'instances') {

        } else {

        }

        if ($this->_owApp->selectedResource) {
            $this->_owApp->logger->debug(
                'MimimapModule/getContents: selectedResource = "' . $this->_owApp->selectedResource . '".'
            );
        }
        return $this->render('minimap');
    }

    /**
     * Returns the content
     * from: Geostats module
     */
    public function getGeostatsContents()
    {
        $data['resourceUri'] = $this->_owApp->selectedResource->getIri();

        $stats['typedResources'] = 0;
        $stats['without_coords'] = 0;
        $stats['with_coords'] = 0;
        $stats['low_accuracy'] = 0;
        $stats['postcode_geocoded'] = 0;

        #PREFIX rdf:<http://www.w3.org/1999/02/22-rdf-syntax-ns#>
        #PREFIX vak:<http://vakantieland.nl/catalogue/classes/>#
        #PREFIX wgs:<http://www.w3.org/2003/01/geo/wgs84_pos#>
        #Select *
        #FROM <http://vakantieland.nl/>
        #WHERE {
        #?poiUri rdf:type vak:POI .
        #OPTIONAL { ?poiUri wgs:long ?long }
        #FILTER ( !BOUND( ?long ) )
        #}

        $longitudeProp = $this->_privateConfig->property->longitude->toArray();
        $accuracyProp = $this->_privateConfig->property->accuracy->toArray();
        $typeProp = $this->_privateConfig->property->type->toArray();

        $poiType = $this->_privateConfig->type->poi->toArray();

        $this->model = $this->_owApp->selectedModel;

        /*
         * TODO: in the following the 5th parameter for countWhereMatches is set to false, because there seams to be a
         *       problem in 'libraries/Erfurt/Erfurt/Store.php' with followImports. This has to be fixed.
         */
        $stats['typedResources'] = $this->_owApp->erfurt->getStore()->countWhereMatches(
            $this->model,
            "WHERE { ?uri <" . $typeProp[0] . "> <" . $poiType . "> . }",
            "?uri",
            true,
            false
        );

        // Count ressources with geographical coordinates
        $stats['with_coords'] = $this->_owApp->erfurt->getStore()->countWhereMatches(
            $this->model,
            "WHERE { "
            . " ?uri <" . $typeProp[0] . "> <" . $poiType . "> . "
            . " ?uri <" . $longitudeProp[0] . "> ?long . "
            . "}",
            "?uri",
            true,
            false
        );

        $stats['without_coords'] = $stats['typedResources'] - $stats['with_coords'];

        // Count ressources with accuracy beween 0 and 5
        $stats['low_accuracy'] = $this->_owApp->erfurt->getStore()->countWhereMatches(
            $this->model,
            "WHERE { "
            . " ?uri <" . $typeProp[0] . "> <" . $poiType . "> . "
            . " ?uri <" . $accuracyProp[0] . "> ?accuracy FILTER (?accuracy < 6 && ?accuracy > -1) "
            . "}",
            "?uri",
            true,
            false
        );

        // Count ressources geocoded via postcodeNL geocoder
        $stats['postcode_geocoded'] = $this->_owApp->erfurt->getStore()->countWhereMatches(
            $this->model,
            "WHERE { "
            . " ?uri <" . $typeProp[0] . "> <" . $poiType . "> . "
            . " ?uri <" . $accuracyProp[0] . "> ?accuracy FILTER (?accuracy < 0) "
            . "}",
            "?uri",
            true,
            false
        );

        $content = $this->render('geostats', $stats, 'stats');

        return $content;
    }

    /**
     * Returns the content of the geocode
     */
    public function getGeocodeContents()
    {
        $data['resourceUri'] = $this->_owApp->selectedResource->getIri();

        // Get address data
        /**
        * $instance contains string to check if suitable for direct geocoding
        * $searchString contains, address, city and country, if found
        * $searchString_short city and country only
        */
        $uri = $this->_owApp->selectedResource->getUri();
        $this->model = $this->_owApp->selectedModel;

        $streetProp = $this->_privateConfig->property->street->toArray();
        $housenumberProp = $this->_privateConfig->property->housenumber->toArray();
        $postcodeProp = $this->_privateConfig->property->postcode->toArray();
        $cityProp = $this->_privateConfig->property->city->toArray();
        $countryProp = $this->_privateConfig->property->country->toArray();
        $accuracyProp = $this->_privateConfig->property->accuracy->toArray();

        $qr = "SELECT * WHERE {
        { <" . $uri . "> ?p ?o}
        OPTIONAL
        { <" . $uri . "> <".$streetProp[0]."> ?street}
        OPTIONAL
        { <" . $uri . "> <".$housenumberProp[0]."> ?housenumber}
        OPTIONAL
        { <" . $uri . "> <".$postcodeProp[0]."> ?postcode}
        OPTIONAL
        { <" . $uri . "> <".$cityProp[0]."> ?city}
        OPTIONAL
        { <" . $uri . "> <".$countryProp[0]."> ?country}
        OPTIONAL
        { <" . $uri . "> <".$accuracyProp[0]."> ?accuracy}
        }";
        $resource = $this->model->sparqlQuery($qr);
        if (count($resource) > 0) {
            $instance = $resource[0];

            $data['searchString'] = $instance['street'];
            $data['searchString'] = empty($data['searchString']) ?
                $instance['housenumber'] :
                $data['searchString'] . " " . (string)$instance['housenumber'];

            // Use the TitleHelper to get the actual strings for cities and countries
            //require_once 'OntoWiki/Model/TitleHelper.php';
            $titleHelper = new OntoWiki_Model_TitleHelper($this->model);
            $titleHelper->addResource($instance['city']);
            $titleHelper->addResource($instance['country']);

            if ($titleHelper->getTitle($instance['city']) != "") {
                $data['searchString'] .= ", " . $titleHelper->getTitle($instance['city']);
            }
            if ($titleHelper->getTitle($instance['country']) != "") {
                $data['searchString'] .= ", " . $titleHelper->getTitle($instance['country']);
            }

            $titleHelper->addResource($instance['postcode']);
            $instance['postcode'] = $titleHelper->getTitle($instance['postcode']);

            if (!empty($instance['accuracy'])) {
                $data['accuracy'] = $instance['accuracy'];
            } else {
                $data['accuracy'] = null;
            }

            // Save instance data in session; Needed for some geocoding services like postcodeNL
            OntoWiki::getInstance()->session->gcInstancedata[$uri] = $instance;
            // what to do else?

        } else {
            $data['searchString'] = "";
            $data['accuracy']    = null;
        }
        $data['urlBase']        = $this->_config->urlBase;
        $data['controller']     = $this->_request->getControllerName();
        $data['action']         = $this->_request->getActionName();
        $data['accuracyLimit']  = $this->_privateConfig->geocodeAccuracyLimit;

    if ($data['accuracy'] === null) {
        $this->view->message = $this->_owApp->translate->_(
            'This ressource has no accuracy property. New geocoding is recommended.'
        );
    } else if ($this->data['accuracy'] < $this->data['accuracyLimit'] ) {
        $this->view->mmessage = $this->_owApp->translate->_(
            'This ressource has low accuracy. New geocoding is recommended.'
        );
    }

        $content = $this->render('geocode', $data, 'data');

        return $content;
    }

    /**
     * shouldShow method from geocode
     */
    public function shouldGeocodeShow ()
    {
//        return true;

        // TODO: Only show geocode-module if the ressource contains address properties

        $session = $this->_owApp->session;

        $owApp = OntoWiki::getInstance();
        $owApp->logger->debug('geocode/shouldShow: entering check');

        $session = $this->_owApp->session;
        /*        if (isset($session->instances)) {
         echo "There are session instances...";
         $owApp->logger->debug('geocode/shouldShow: there are session instances');
         }
         /*
         $front  = Zend_Controller_Front::getInstance();
         /*
         if (!$front->getRequest()->isXmlHttpRequest() && isset($session->instances)) {

         if (isset($session->instances)) {
         */
        // We need at least city and country to be able to geocode
        $cityProperty    = $this->_privateConfig->city;
        $countryProperty    = $this->_privateConfig->country;

        $cityVar    = new Erfurt_Sparql_Query2_Var('city');
        $countryVar    = new Erfurt_Sparql_Query2_Var('country');

        if ($this->dirInstances === null) {
            $this->dirInstances = clone $session->instances;
            $owApp->logger->debug('geocode/shouldShow: clone this->_session->instances');
        } else {
            $owApp->logger->debug('geocode/shouldShow: this->dirInstances already set');
            // don't load instances again
        }

        $this->dirInstances->setLimit(1);
        $this->dirInstances->setOffset(0);

        /**
         * Direct Query, to check for direct geoproperties
         */
        $dirQuery  = $this->dirInstances->getResourceQuery();
        $dirQuery->setQueryType(Erfurt_Sparql_Query2::typeSelect); /* would like to ask but ask lies */
        $dirQuery->removeAllOptionals()->removeAllProjectionVars();

        $dirQuery->addProjectionVar($this->dirInstances->getResourceVar());
        $dirQuery->addProjectionVar($cityVar);
        $dirQuery->addProjectionVar($countryVar);

        $dirQuery->addTriple($this->dirInstances->getResourceVar(), $cityProperty, $cityVar);
        $dirQuery->addTriple($this->dirInstances->getResourceVar(), $countryProperty, $countryVar);

        $owApp->logger->debug(
            'geocode/shouldShow: sent "'
            . $dirQuery
            . '" to check for availability of  SpacialThings.'
        );

        /* get result of the query */
        $dirResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($dirQuery);

        $owApp->logger->debug('geocode/shouldShow: got response "' . var_export($dirResult, true) . '".');

        if ($dirResult) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }
}

