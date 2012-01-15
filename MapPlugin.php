<?php
/**
 * @category   OntoWiki
 * @package    OntoWiki_extensions_plugins
 */
class MapPlugin extends OntoWiki_Plugin
{

    public function init()
    {
        $this->properties = array_values($this->_privateConfig->property->accuracy->toArray());
    }

    public function onDisplayLiteralPropertyValue($event)
    {
        if (in_array($event->property, $this->properties)) {
            $htmlout  = "<span style='"
                . " background: url(" . $this->_pluginUrlBase . "resources/star.gif) repeat-x 0 -32px;"
                . " float: left;"
                . " height: 16px;"
                . " width: " . 8 * $event->value . "px;"
                . "'></span>"
                . "<span style='"
                . "background: url(" . $this->_pluginUrlBase . "resources/star.gif) repeat-x 0 0px;"
                . "float: left;"
                . "height: 16px;"
                . "width: " . 8 * (10 - $event->value) . " px;"
                . "background-position: " . 8 * (10 - $event->value) . "px 0px'"
                . " title='Accuracy: " . $event->value . "'></span>";
            return $htmlout;
        }
    }
}
