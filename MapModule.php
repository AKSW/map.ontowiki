<?php

/**
 * OntoWiki module – minimap
 *
 * display a minimap of the currently visible resources (if any)
 * shows a short statistical summary about geographical data in a ressource
 *
 * @category OntoWiki
 * @package Extensions_Map
 * @author Natanael Arndt <arndtn@gmail.com>
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
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
    }

    public function getTitle()
    {
        $logger = OntoWiki::getInstance()->logger;
        $logger->debug('getTitle');
        return $this->_owApp->translate->_('Map');
    }

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

