<?php

/**
 * Helper class for the Map component.
 * Checks whether there are geospacial properties in result of the currant QueryObject
 * and registers the Map tab component if so.
 *
 * @category OntoWiki
 * @package OntoWiki_extensions_components_map
 * @author Natanael Arndt <arndtn@gmail.com>
 * TODO comments
 */
class MapHelper extends OntoWiki_Component_Helper
{

    /**
     * Object holding the Instances with direct geo properties (e.g.: geo:long, geo:lat)
     * and the other one with indirect geo properties (e.g.: foaf:based_near)
     */
    private $_dirInstances = null;
    private $_indInstances = null;
    private $_listHelper = null;

    public function init()
    {
        $owApp = OntoWiki::getInstance();

        $logger = $owApp->logger;
        $logger->debug('Initializing MapPlugin Helper');

        // decide, if map should be on
        $onSwitch = false;

        if (isset($this->_privateConfig->show->tab)) {
            if ($this->_privateConfig->show->tab == 'ever') {
                $onSwitch = true;
            } else if ($this->_privateConfig->show->tab == 'never') {
                $onSwitch = false;
            } else {
                $onSwitch = $this->shouldShow();
            }
        } else {
            $onSwitch = $this->shouldShow();
        }

        if ($onSwitch) {
            // register new tab

            if (!OntoWiki_Navigation::isRegistered('map')) {
                OntoWiki_Navigation::register(
                    'map',
                    array(
                        'controller' => 'map',
                        'action'     => 'display',
                        'name'       => 'Map',
                        'priority'   => 20,
                        'active'     => false
                    )
                );
            }
        }
    }

    public function shouldShow ()
    {
        /**
         * for debug output
         * @var OntoWiki Instance of the App-Object
         */
        $owApp = OntoWiki::getInstance();
        $logger = $owApp->logger;
        $logger->debug('shouldShow Helper');
        if ($this->_listHelper == null) {
            $this->_listHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
        }

        /*
         * don't show on model, application, error, debug, module and index controller
         */
        /*
           $session = $owApp->session;
         */
        $front  = Zend_Controller_Front::getInstance();

        $listName = "instances";

        $latProperties  = $this->_privateConfig->property->latitude->toArray();
        $longProperties = $this->_privateConfig->property->longitude->toArray();
        $latProperty    = $latProperties[0];
        $longProperty   = $longProperties[0];

        if ($this->_owApp->lastRoute == 'properties' && $this->_owApp->SelectedResource != null) {
            //$this->_owApp->selectedResource;

            $dirQuery = '
                SELECT ?lat ?long
                WHERE {
                    <' . $this->_owApp->selectedResource->getIri() . '> <' . $latProperty . '>  ?lat;
                    <' . $longProperty . '> ?long.
                }';
            $indQuery = '
                SELECT ?lat2 ?long2
                WHERE {
                    <' . $this->_owApp->selectedResource->getIri() . '> ?p ?o.
                        ?o <' . $latProperty . '>  ?lat2;
                    <' . $longProperty . '> ?long2.
                }';

            $owApp->logger->debug(
                'MapHelper/shouldShow: sent "' . $dirQuery . '" to know if SpacialThings are available.'
            );
            $owApp->logger->debug(
                'MapHelper/shouldShow: sent "' . $indQuery . '" to know if SpacialThings are available.'
            );

            /* get result of the query */
            $dirResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($dirQuery);
            $indResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($indQuery);

            $owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($dirResult, true) . '".');
            $owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($indResult, true) . '".');

            if ($dirResult OR $indResult) {
                $result = true;
            } else {
                $result = false;
            }

            return $result;
        } else if ($this->_owApp->lastRoute == 'instances') {
            if (!$front->getRequest()->isXmlHttpRequest() && $this->_listHelper->listExists($listName)) {
                $instances = $this->_listHelper->getList($listName);

                $latVar         = new Erfurt_Sparql_Query2_Var('lat');
                $longVar        = new Erfurt_Sparql_Query2_Var('long');
                $indirLatVar    = new Erfurt_Sparql_Query2_Var('lat2');
                $indirLongVar   = new Erfurt_Sparql_Query2_Var('long2');

                if ($this->_dirInstances === null) {
                    $this->_dirInstances = clone $instances;
                } else {
                    $owApp->logger->debug('MapHelper/shouldShow: this->_dirInstances already set');
                    // don't load instances again
                }

                if ($this->_indInstances === null) {
                    $this->_indInstances = clone $instances;
                } else {
                    $owApp->logger->debug('MapHelper/shouldShow: this->_indInstances already set');
                    // don't load instances again
                }

                $this->_dirInstances->setLimit(1);
                $this->_dirInstances->setOffset(0);
                $this->_indInstances->setLimit(1);
                $this->_indInstances->setOffset(0);

                /**
                 * Direct Query, to check for direct geoproperties
                 */
                $dirQuery  = $this->_dirInstances->getResourceQuery();
                $dirQuery->setQueryType(Erfurt_Sparql_Query2::typeSelect); /* would like to ask but ask lies */
                $dirQuery->removeAllOptionals()->removeAllProjectionVars();

                /**
                 * Indirect Query, to check for indirect geoproperties
                 */
                $indQuery  = $this->_indInstances->getResourceQuery();
                $indQuery->setQueryType(Erfurt_Sparql_Query2::typeSelect); /* would like to ask but ask lies */
                $indQuery->removeAllOptionals()->removeAllProjectionVars();

                $dirQuery->addProjectionVar($this->_dirInstances->getResourceVar());
                $dirQuery->addProjectionVar($latVar);
                $dirQuery->addProjectionVar($longVar);

                $indQuery->addProjectionVar($this->_indInstances->getResourceVar());
                $indQuery->addProjectionVar($indirLatVar);
                $indQuery->addProjectionVar($indirLongVar);

                $dirQuery->addTriple($this->_dirInstances->getResourceVar(), $latProperty, $latVar);
                $dirQuery->addTriple($this->_dirInstances->getResourceVar(), $longProperty, $longVar);

                // should be a Erfurt_Sparql_Query2_BlankNode but i heard this is not supported by zendb
                $node     = new Erfurt_Sparql_Query2_Var('node');
                $indQuery->addTriple(
                    $this->_indInstances->getResourceVar(), new Erfurt_Sparql_Query2_Var('pred'), $node
                );
                $indQuery->addTriple($node, $latProperty, $indirLatVar);
                $indQuery->addTriple($node, $longProperty, $indirLongVar);

                $owApp->logger->debug(
                    'MapHelper/shouldShow: sent "' . $dirQuery . '" to know if SpacialThings are available.'
                );
                $owApp->logger->debug(
                    'MapHelper/shouldShow: sent "' . $indQuery . '" to know if SpacialThings are available.'
                );

                /* get result of the query */
                try {
                    $dirResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($dirQuery);
                    $indResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($indQuery);

                    $owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($dirResult, true) . '".');
                    $owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($indResult, true) . '".');

                    if ($dirResult OR $indResult) {
                        $result = true;
                    } else {
                        $result = false;
                    }
                } catch (Erfurt_Store_Adapter_Exception $e) {
                    $owApp->logger->err(
                        'Caught exception on query, but I am just a Helper, but I will show anyways: ',
                        $e->getMessage(),
                        "\n"
                    );
                    $result = true;
                }

                return $result;
            }
        }

        if ($front->getRequest()->isXmlHttpRequest()) {
            $owApp->logger->debug('MapHelper/shouldShow: xmlHttpRequest → no map.');
        } else if (!$this->_listHelper->listExists($listName)) {
            $owApp->logger->debug('MapHelper/shouldShow: no instances list found → no instances to show → no map.');
        } else {
            $owApp->logger->debug(
                'MapHelper/shouldShow: decided to hide the map, but not because of a XmlHttpRequest and not, because '
                . 'there is no valide session.'
            );
        }

        return false;
    }
}

