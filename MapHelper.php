<?php

/**
 * Helper class for the Map component.
 * Checks whether there are geospacial properties in result of the currant QueryObject
 * and registers the Map tab component if so.
 *
 * @category OntoWiki
 * @package Extensions_Map
 * @author Natanael Arndt <arndt@informatik.uni-leipzig.de>
 */
class MapHelper extends OntoWiki_Component_Helper
{

    /**
     * Object holding the Instances with direct geo properties (e.g.: geo:long, geo:lat)
     * and the other one with indirect geo properties (e.g.: foaf:based_near)
     */
    private $_listHelper = null;
    private $_navigation = null;

    public function init()
    {
        // get OntoWiki instance because it is not present during init
        $owApp = OntoWiki::getInstance();

        $owApp->logger->debug('Initializing MapPlugin Helper');

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
            if ($this->_navigation == null) {
                $this->_navigation = $owApp->getNavigation();
            }

            if (!$this->_navigation->isRegistered('map')) {
                $this->_navigation->register(
                    'map',
                    array(
                        'controller' => 'map',
                        'action'     => 'display',
                        'name'       => 'Map',
                        'priority'   => 100,
                        'active'     => false
                    )
                );
            }
        }
    }

    /**
     * Checks if the map tab or the map module should be shown.
     * It executes two queries to see if the selected resources have direct or indirect geographical
     * properties.
     *
     * @return boolean true if it should be shown, false if not
     */
    public function shouldShow ()
    {
        $this->_owApp->logger->debug('shouldShow Helper');

        if ($this->_listHelper == null) {
            $this->_listHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
        }

        $listName = 'instances';

        $latProperties  = $this->_privateConfig->property->latitude->toArray();
        $longProperties = $this->_privateConfig->property->longitude->toArray();
        $latProperty    = $latProperties[0];
        $longProperty   = $longProperties[0];

        if ($this->_owApp->lastRoute == 'properties' && $this->_owApp->selectedResource != null) {
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

            $this->_owApp->logger->debug(
                'MapHelper/shouldShow: sent "' . $dirQuery . '" to know if SpacialThings are available.'
            );
            $this->_owApp->logger->debug(
                'MapHelper/shouldShow: sent "' . $indQuery . '" to know if SpacialThings are available.'
            );

            /* get result of the query */
            $dirResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($dirQuery);
            $indResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($indQuery);

            $this->_owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($dirResult, true) . '".');
            $this->_owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($indResult, true) . '".');

            if ($dirResult || $indResult) {
                $result = true;
            } else {
                $result = false;
            }

            return $result;
        } else if ($this->_owApp->lastRoute == 'instances') {
            if ($this->_listHelper->listExists($listName)) {
                $resourceVar = $this->_listHelper->getList($listName)->getResourceVar();
                $instancesQuery = $this->_listHelper->getList($listName)->getResourceQuery();

                $latVar         = new Erfurt_Sparql_Query2_Var('lat');
                $longVar        = new Erfurt_Sparql_Query2_Var('long');
                $indirLatVar    = new Erfurt_Sparql_Query2_Var('lat2');
                $indirLongVar   = new Erfurt_Sparql_Query2_Var('long2');

                $dirQuery = clone $instancesQuery;
                $indQuery = clone $instancesQuery;

                $dirQuery->setLimit(1);
                $dirQuery->setOffset(0);
                $indQuery->setLimit(1);
                $indQuery->setOffset(0);

                /**
                 * Direct Query, to check for direct geoproperties
                 */
                $dirQuery->setQueryType(Erfurt_Sparql_Query2::typeSelect); /* would like to ask but ask lies */
                $dirQuery->removeAllOptionals()->removeAllProjectionVars();

                /**
                 * Indirect Query, to check for indirect geoproperties
                 */
                $indQuery->setQueryType(Erfurt_Sparql_Query2::typeSelect); /* would like to ask but ask lies */
                $indQuery->removeAllOptionals()->removeAllProjectionVars();

                $dirQuery->addProjectionVar($resourceVar);
                $dirQuery->addProjectionVar($latVar);
                $dirQuery->addProjectionVar($longVar);

                $indQuery->addProjectionVar($resourceVar);
                $indQuery->addProjectionVar($indirLatVar);
                $indQuery->addProjectionVar($indirLongVar);

                $dirQuery->addTriple($resourceVar, $latProperty, $latVar);
                $dirQuery->addTriple($resourceVar, $longProperty, $longVar);

                // should be a Erfurt_Sparql_Query2_BlankNode but i heard this is not supported by zendb
                $node     = new Erfurt_Sparql_Query2_Var('node');
                $indQuery->addTriple(
                    $resourceVar, new Erfurt_Sparql_Query2_Var('pred'), $node
                );
                $indQuery->addTriple($node, $latProperty, $indirLatVar);
                $indQuery->addTriple($node, $longProperty, $indirLongVar);

                $this->_owApp->logger->debug(
                    'MapHelper/shouldShow: sent "' . $dirQuery . '" to know if SpacialThings are available.'
                );
                $this->_owApp->logger->debug(
                    'MapHelper/shouldShow: sent "' . $indQuery . '" to know if SpacialThings are available.'
                );

                /* get result of the query */
                try {
                    $dirResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($dirQuery);
                    $indResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($indQuery);

                    $this->_owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($dirResult, true) . '".');
                    $this->_owApp->logger->debug('MapHelper/shouldShow: got respons "' . var_export($indResult, true) . '".');

                    if ($dirResult || $indResult) {
                        $result = true;
                    } else {
                        $result = false;
                    }
                } catch (Erfurt_Store_Adapter_Exception $e) {
                    $this->_owApp->logger->err(
                        'Caught exception on query, but I am just a Helper, but I will show anyways: ',
                        $e->getMessage(),
                        PHP_EOL
                    );
                    $result = true;
                }

                return $result;
            }
        }

        if (Zend_Controller_Front::getInstance()->getRequest()->isXmlHttpRequest()) {
            $this->_owApp->logger->debug('MapHelper/shouldShow: xmlHttpRequest → no map.');
        } else if (!$this->_listHelper->listExists($listName)) {
            $this->_owApp->logger->debug('MapHelper/shouldShow: no instances list found → no instances to show → no map.');
        } else {
            $this->_owApp->logger->debug(
                'MapHelper/shouldShow: decided to hide the map, but not because of a XmlHttpRequest and not, because '
                . 'there is no valide session.'
            );
        }

        return false;
    }
}
