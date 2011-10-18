<?php
/**
 * Geocoding component controller.
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_geocoding
 * @author Claudius Henrichs <chenrichs@gmail.com>
 * @author Natanael Arndt <arndtn@gmail.com>
 * @version $Id$
 * TODO comments
 */

// Include the singleton class for the resultset
require_once ('classes/GeocodingResult.php');

// Import of base class for forking support (requires PHP with pcntl-support => *nix only)
//require_once ("classes/Fork.php");

class GeoservicesController extends OntoWiki_Controller_Component
{

	/**
	 * The currently selected resource object
	 * @var
	 */
	protected $_resource = null;

	/**
	 * The currently selected resource's URI
	 * @var
	 */
	protected $_resourceUri = null;


	public function init()
	{
		parent::init();
		if(is_object($this->_owApp->selectedResource)){
			$this->resource = $this->_owApp->selectedResource->getIri();
		}
		$this->model    = $this->_owApp->selectedModel;
		$this->store    = $this->_erfurt->getStore();

		if ($this->_owApp->selectedResource instanceof Erfurt_Rdf_Resource) {
			$this->_resource = $this->_owApp->selectedResource;
			$this->_resourceUri = $this->_resource->getUri();
		}
	}


	/**
	 * Polls geocoding services for location information using the ressource's address as query and using JSON requests
	 */
	public function geocodeAction()
	{
		// tells the OntoWiki to not apply the template to this action
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		$this->_owApp->logger->debug('Geocoding/geocodeAction action called');

		$searchString = $searchString_short = $this->_request->q;

		$uri = $this->_resourceUri;
		if (empty($searchString)) {
			/**
			 * $instance contains string to check if suitable for direct geocoding
			 * $searchString contains, address, city and country, if found
			 * $searchString_short city and country only
			 */

			/**
			 * Read property values from module's configuration
			 */
			$streetProperties  	= $this->_privateConfig->property->street->toArray();
			$housenumberProperties 	= $this->_privateConfig->property->housenumber->toArray();
			$cityProperties  	= $this->_privateConfig->property->city->toArray();
			$countryProperties 	= $this->_privateConfig->property->country->toArray();
			$streetProperty    	= $streetProperties[0];
			$housenumberProperty   	= $housenumberProperties[0];
			$cityProperty    	= $cityProperties[0];
			$countyProperty   	= $countryProperties[0];

			$qr = "SELECT * WHERE {
			{ <" . $uri . "> <" . $streetProperty . "> ?street}
			OPTIONAL
			{ <" . $uri . "> <" . $housenumberProperty . "> ?housenumber}
			OPTIONAL
			{ <" . $uri . "> <" . $cityProperty . "> ?city}
			OPTIONAL
			{ <" . $uri . "> <" . $countryProperty . "> ?country}
		}";
			$resource = $this->model->sparqlQuery($qr);
			$instance = $resource[0];

			$searchString = $instance['street'];
			$searchString = empty($searchString) ? $instance['housenumber'] : $searchString . " " . (string)$instance['housenumber'];

			// Use the TitleHelper to get the actual strings for cities and countries
			//require_once 'OntoWiki/Model/TitleHelper.php';
			$titleHelper = new OntoWiki_Model_TitleHelper($this->model);
			$titleHelper->addResource($instance['city']);
			$titleHelper->addResource($instance['country']);

			$searchString_short = $titleHelper->getTitle($instance['city']);
			$searchString_short .= ", " . $titleHelper->getTitle($instance['country']);

			$searchString .= ", " . $searchString_short;
		}

		// Create the singleton for the resultset
		$GeocodingResult = GeocodingResult::getInstance();

		// Check which geocoders are available
		foreach ($this->_privateConfig->active_geocoders->toArray() as $geocoder) {
			require_once $this->_componentRoot . 'geocoder_services/' . $geocoder . '.php';

			eval('$Geocoder_service = new '.ucfirst($geocoder) . '_service;');
			$Geocoder_result = $Geocoder_service->servicepollaction($searchString, $searchString_short, $uri);
			if ($Geocoder_result) {
				//array_push($data, $Geocoder_result);
				$GeocodingResult->pushData($Geocoder_result);
			}
		}

		// $this->_response->setHeader('Content-Type', 'application/json', true);
		$this->_response->setBody(json_encode($GeocodingResult->getResultset()));

		OntoWiki::getInstance()->session->gcResult[$uri] = $GeocodingResult->getResultset();

		return true;
	}


	/**
	 * Sends the previously geocoded markerdata for an uri in a map-component compatible format
	 */
	public function getmarkerAction() {

		// tells the OntoWiki to not apply the template to this action
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		$this->_owApp->logger->debug('Geocoding/getmarkerAction action called');

		// Marker-class from Map-component to create set of markers
		include('extensions/components/map/classes/Marker.php');
		$markers = array();

		foreach (OntoWiki::getInstance()->session->gcResult[$this->_resourceUri] as $gcResult) {
			$marker = new Marker($this->_resourceUri . "/" . $gcResult['label']);
			$marker->setLat($gcResult[0]['lat']);
			$marker->setLon($gcResult[0]['lon']);
			$marker->setIcon(null);
			$markers[] = $marker;
		}

		$this->_response->setBody(json_encode($markers));
	}


	/**
	 * Saves lat/lon data passed in request parameter "coordinates" into the model
	 */
	public function storecoordsAction() {

		// tells the OntoWiki to not apply the template to this action
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		$this->_owApp->logger->debug('Geocoding/storecoordsAction action called');

		if ($this->model->isEditable()) {
			$uri = $this->_resourceUri;

			$coordinates = $this->_request->coordinates;
			$coordinatearray = explode (",", $coordinates);

			$predicates = array();
			$longitude = array('value' => $coordinatearray[1], 'type' => 'literal', 'datatype' => "xsd:float");

			$latitude = array('value' => $coordinatearray[0], 'type' => 'literal', 'datatype' => "xsd:float");

			$accuracy = array('value' => $this->_request->accuracy, 'type' => 'literal','datatype' => "xsd:float");

			$predicates[$this->_privateConfig->longitude][] = $longitude;
			$predicates[$this->_privateConfig->latitude][] = $latitude;
			$predicates[$this->_privateConfig->accuracy][] = $accuracy;
			$statementsAdd = array( $uri => $predicates );

			$versioning                 = $this->_erfurt->getVersioning();
			$actionSpec                 = array();
			$actionSpec['type']         = 666;
			$actionSpec['modeluri']     = (string) $this->_owApp->selectedModel;
			$actionSpec['resourceuri']  = $uri;

			$versioning->startAction($actionSpec);
			$result = $this->model->deleteMatchingStatements( $uri, $this->_privateConfig->longitude, null);
			$result = $this->model->deleteMatchingStatements( $uri, $this->_privateConfig->latitude, null);
			$result = $this->model->deleteMatchingStatements( $uri, $this->_privateConfig->accuracy, null);
			$result = $this->model->addMultipleStatements( $statementsAdd );
			$versioning->endAction($actionSpec);
				

			// This variant using SPARQL Update language did not work reliable
			/*  $modelUri = $this->_owApp->selectedModel->getModelIri();

			$sparULstring = 'INSERT DATA INTO <'. $modelUri .'> {<'.$uri.'> <' . $this->_privateConfig->longitude .'> "'.$coordinatearray[1].'" .}
			DELETE DATA FROM <'. $modelUri .'> {<'.$uri.'> <' . $this->_privateConfig->longitude .'> <*> .}';


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

}

interface Geocoder_service {
	public function servicepollaction($searchstring, $searchstring_short, $uri);
}
