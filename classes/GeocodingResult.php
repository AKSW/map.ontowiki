<?php

final class GeocodingResult
{
    // Create the single instance
    private static $_instance = NULL;

    private static $_resultset = array();

    // Private constructor to force instanciation only from within itself.
    private function __construct()
    {
    }

    // Static method returning the one and only instance.
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    // Prohibit cloning by using 'clone()' externally.
    private function __clone()
    {
    }

    public static function pushData($data)
    {
        array_push(self::$_resultset, $data);
    }


    public static function getResultset()
    {
        return self::$_resultset;
    }
}
