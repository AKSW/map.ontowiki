<?php

/**
 * OntoWiki module – Short geo statistics
 *
 * shows a short statistical summary about geographical data in a ressource
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_modules_geostats
 * @author     Claudius Henrichs <chenrichs@gmail.com>
 * @copyright  Copyright (c) 2010, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id: geostats.php $
 */
class GeostatsModule extends OntoWiki_Module
{
    public function init() {
	/*$query = new Erfurt_Sparql_SimpleQuery();

        $query->setProloguePart('SELECT DISTINCT ?uri')
              ->setWherePart('WHERE {
                    ?subject ?uri <' . (string) $this->_owApp->selectedResource . '> .
                    FILTER (isURI(?subject))
                }')
              ->setLimit(OW_SHOW_MAX);

        $this->predicates = $this->_owApp->selectedModel->sparqlQuery($query);*/

	$this->_owApp->translate->addTranslation(_OWROOT . $this->_config->extensions->modules .
                                               $this->_name . DIRECTORY_SEPARATOR . 'languages/', null,
                                               array('scan' => Zend_Translate::LOCALE_FILENAME));
    }
    
    /**
     * Returns the translated box title
     */
    public function getTitle() {
	return $this->_owApp->translate->_('Geodata statistics');
    }
    
    /**
     * Returns the content
     */
    public function getContents() {
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

	$stats['typedResources'] = $this->_owApp->erfurt->getStore()->countWhereMatches($this->_owApp->selectedResource->getIri(), "WHERE {
		?uri  <".$this->_privateConfig->typeuri."> <".$this->_privateConfig->resourcetype."> .
               }", "?uri", true);

	// Count ressources with geographical coordinates
	$stats['with_coords'] = $this->_owApp->erfurt->getStore()->countWhereMatches($this->_owApp->selectedResource->getIri(), "WHERE {
                ?uri  <".$this->_privateConfig->typeuri."> <".$this->_privateConfig->resourcetype."> .
                ?uri <".$this->_privateConfig->longitude."> ?long .
               }", "?uri", true);

	$stats['without_coords'] = $stats['typedResources'] - $stats['with_coords'];

	// Count ressources with accuracy beween 0 and 5
	$stats['low_accuracy'] = $this->_owApp->erfurt->getStore()->countWhereMatches($this->_owApp->selectedResource->getIri(), "WHERE {
                    ?uri  <".$this->_privateConfig->typeuri."> <".$this->_privateConfig->resourcetype."> .
                    ?uri <".$this->_privateConfig->accuracy."> ?accuracy
                    FILTER (?accuracy < 6 && ?accuracy > -1)
               }", "?uri", true);

	// Count ressources geocoded via postcodeNL geocoder
	$stats['postcode_geocoded'] = $this->_owApp->erfurt->getStore()->countWhereMatches($this->_owApp->selectedResource->getIri(), "WHERE {
                    ?uri  <".$this->_privateConfig->typeuri."> <".$this->_privateConfig->resourcetype."> .
                    ?uri <".$this->_privateConfig->accuracy."> ?accuracy
                    FILTER (?accuracy < 0)
               }", "?uri", true);
        
	$content = $this->render('geostats', $stats, 'stats');
	
	return $content;
    }
	
    public function shouldShow(){
	return true;
	}
}
