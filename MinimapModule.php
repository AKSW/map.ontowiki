<?php
// vim: sw=4:sts=4:expandtab
require_once 'OntoWiki/Module.php';

/**
 * OntoWiki module – minimap
 *
 * display a minimap of the currently visible resources (if any)
 *
 * @package    ontowiki
 * @copyright  Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id: minimap.php 4241 2009-10-05 22:33:25Z arndtn $
 */
class MinimapModule extends OntoWiki_Module
{
    public function init()
    {
        // TODO: fix it, cause exception
/*    $this->_owApp->translate->addTranslation(_OWROOT . $this->_config->extensions->modules .
        $this->_name . DIRECTORY_SEPARATOR . 'languages/', null,
        array('scan' => Zend_Translate::LOCALE_FILENAME));
 */
    }

    public function getTitle()
    {
        return 'Map';
    }

    public function getContents()
    {
//        if(isset($this->_owApp->session->instances)) {
			$this->_owApp->logger->debug('MimimapModule/getContents: lastRoute = "' . $this->_owApp->lastRoute . '".');
			if ($this->_owApp->lastRoute == 'properties') {
				$this->view->context = 'single_instance';
			} else if ($this->_owApp->lastRoute == 'instances') {
				
			} else {
				
			}
			
            if($this->_owApp->selectedResource) {
            	$this->_owApp->logger->debug('MimimapModule/getContents: selectedResource = "' . $this->_owApp->selectedResource . '".');
            }
            return $this->render('minimap');
//        } else {
//            $this->view->message = 'No Instances object in session.';
//            $this->_owApp->logger->debug('minimap: error: this->_session->instances is not set!');
//            return $this->render('error');
//        } 
    }

    public function shouldShow()
    {
        if(class_exists('MapHelper')) {
            $helper = $this->_owApp->extensionManager->getComponentHelper('map');
           // $helper = new MapHelper($this->_owApp->extensionManager);
            return $helper->shouldShow();
        } else {
            return false;
        }
    }

    /**
     * Returns the menu of the module
     *
     * @return string
     */
    public function getMenu() {
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
        $layerMenu->setEntry('Google Streets', "javascript:minimapSelectLayer('Google Streets')")
            ->setEntry('Google Hybrid', "javascript:minimapSelectLayer('Google Hybrid')")
            ->setEntry('Google Satellite', "javascript:minimapSelectLayer('Google Hybrid')")
            ->setEntry('Google Physical', "javascript:minimapSelectLayer('Google Hybrid')")
            ->setEntry('OpenStreetMaps', "javascript:minimapSelectLayer('OpenStreetMap')")
            ->setEntry('OpenStreetMaps (Tiles@Home)', "javascript:minimapSelectLayer('OpenStreetMap (Tiles@Home)')")
            ;

        // zoom sub menu
        $zoomMenu = new OntoWiki_Menu();
        $zoomMenu->setEntry('Zoom in', "javascript:minimap.zoomIn()")
            ->setEntry('Zoom out', "javascript:minimap.zoomOut()")
            ->setEntry('Zoom to elements', "javascript:minimap.zoomIdeal()")
            ->setEntry('Zoom world', "javascript:minimap.zoomMax()")
            ;

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
        $id = $this->_owApp->selectedModel
            . $this->_owApp->selectedResource;
        
        return $id;
    }
}


