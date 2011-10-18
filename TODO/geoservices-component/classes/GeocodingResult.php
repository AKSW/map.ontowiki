<?php
final class GeocodingResult {

    // Create the single instance
    private static $instance = NULL;

    private static $resultset = array();

    // Private constructor to force instanciation only from within itself.
    private function __construct() {}

    // Static method returning the one and only instance.
    public static function getInstance() {

        if (self::$instance === NULL) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    // Prohibit cloning by using 'clone()' externally.
    private function __clone() {}

    public static function pushData($data) {
        array_push(self::$resultset, $data);
        return;
    }


    public static function getResultset() {
        return self::$resultset;
    }

}

?>
