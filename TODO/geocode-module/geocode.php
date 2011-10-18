<?php

/**
 * OntoWiki module – Geocode
 *
 * offers geocoding to ressources
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_modules_geocode
 * @author     Claudius Henrichs <chenrichs@gmail.com>
 * @copyright  Copyright (c) 2010, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id: quickstats.php $
 */
class GeocodeModule extends OntoWiki_Module
{
	/**
	 * Object holding the Instances with direct geo properties (e.g.: geo:long, geo:lat)
	 */
	private $dirInstances = null;

	public function init() {
		$this->view->headScript()->appendFile($this->view->moduleUrl . 'geocode.js');
		$this->view->headLink()->appendStylesheet($this->view->moduleUrl . 'geocode.css', 'screen');

		#$this->_owApp->translate->addTranslation(_OWROOT . $this->_config->extensions->modules .
		#	$this->_name . DIRECTORY_SEPARATOR . 'languages/', null,
		#	array('scan' => Zend_Translate::LOCALE_FILENAME));
	}


	/**
	 * Returns the translated box title
	 */
	public function getTitle() {
		return $this->_owApp->translate->_('Geocoding');
	}

	/**
	 * Returns the content
	 */
	public function getContents() {
		$data['resourceUri'] = $this->_owApp->selectedResource->getIri();

		// Get address data
		/**
		* $instance contains string to check if suitable for direct geocoding
		* $searchString contains, address, city and country, if found
		* $searchString_short city and country only
		*/
		$uri = $this->_owApp->selectedResource->getUri();
		$this->model = $this->_owApp->selectedModel;

		$qr = "SELECT * WHERE {
		{ <" . $uri . "> ?p ?o}
		OPTIONAL
		{ <" . $uri . "> <".$this->_privateConfig->street."> ?street}
		OPTIONAL
		{ <" . $uri . "> <".$this->_privateConfig->housenumber."> ?housenumber}
		OPTIONAL
		{ <" . $uri . "> <".$this->_privateConfig->postcode."> ?postcode}
		OPTIONAL
		{ <" . $uri . "> <".$this->_privateConfig->city."> ?city}
		OPTIONAL
		{ <" . $uri . "> <".$this->_privateConfig->country."> ?country}
		OPTIONAL
		{ <" . $uri . "> <".$this->_privateConfig->accuracy."> ?accuracy}
		}";
		$resource = $this->model->sparqlQuery($qr);
		if (count($resource) > 0) {
			$instance = $resource[0];

			$data['searchString'] = $instance['street'];
			$data['searchString'] = empty($data['searchString']) ? $instance['housenumber'] : $data['searchString'] . " " . (string)$instance['housenumber'];

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
				$data['accuracy']	= $instance['accuracy'];
			} else {
				$data['accuracy'] = null;
			}
			
			// Save instance data in session; Needed for some geocoding services like postcodeNL
			OntoWiki::getInstance()->session->gcInstancedata[$uri] = $instance;
			// what to do else?


		} else {
			$data['searchString'] = "";
			$data['accuracy']	= null;
		}
		$data['urlBase']	= $this->_config->urlBase;
		$data['controller']	= $this->_request->getControllerName();
		$data['action']		= $this->_request->getActionName();
		$data['accuracyLimit']	= $this->_privateConfig->accuracyLimit;
		$content		= $this->render('templates/geocode', $data, 'data');
		
		return $content;
	}

	public function shouldShow ()
	{
		return true;

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
		$cityProperty	= $this->_privateConfig->city;
		$countryProperty	= $this->_privateConfig->country;

		$cityVar	= new Erfurt_Sparql_Query2_Var('city');
		$countryVar	= new Erfurt_Sparql_Query2_Var('country');

		if($this->dirInstances === null) {
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

		$owApp->logger->debug('geocode/shouldShow: sent "' . $dirQuery . '" to check for availability of  SpacialThings.');

		/* get result of the query */
		$dirResult   = $this->_owApp->erfurt->getStore()->sparqlQuery($dirQuery);

		$owApp->logger->debug('geocode/shouldShow: got response "' . var_export($dirResult, true) . '".');

		if ($dirResult) {
			$result = true;
		} else {
			$result = false;
		}

		return $result;
		/*
		 } else {
		 if($front->getRequest()->isXmlHttpRequest()) {
		 $owApp->logger->debug('geocode/shouldShow: xmlHttpRequest → no map.');
		 } else if(!isset($session->instances)) {
		 $owApp->logger->debug('geocode/shouldShow: no instances object set in session → no instances to show → no map.');
		 } else {
		 $owApp->logger->debug('geocode/shouldShow: decided to hide the map, but not because of a XmlHttpRequest and not, because there is no valide session.');
		 }

		 return false;*/
		//}
	}
}


