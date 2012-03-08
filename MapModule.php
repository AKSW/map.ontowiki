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

    /**
     * Get the map content
     */
    public function getContents()
    {
        $this->_owApp->logger->debug('getContents');

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
}

