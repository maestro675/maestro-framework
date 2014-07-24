<?php

/**
 * 
 */
class Maestro_Errors {

    private static $_instance;

    /**
     * Return the single instance of object
     *
     * @return object
     */
    public static function &getInstance() {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Contructor of class, private
     */
    private function __construct() {

    }

    /**
     *
     */
    static function handler($errno, $errstr, $errfile, $errline) {

        if (!error_reporting())
            return;

        $params['time'] = date('d.m.Y H:i:s');
        $params['ip'] = Maestro_App::getRequest()->getServer('REMOTE_ADDR');
        $params['no'] = $errno;
        $params['str'] = $errstr;
        $params['file'] = $errfile;
        $params['line'] = $errline;

        $xmlDoc = Zend_Registry::isRegistered() ? Zend_Registry::get('document.xml') : null;
        if ('development' == APPLICATION_ENV) {
            if ($xmlDoc) {
                $error = $xmlDoc->createElement('error');
                $xmlDoc->documentElement->appendChild($error);

                foreach ($params as $key => $value) {
                    $node = $xmlDoc->createElement($key, $value);
                    $error->appendChild($node);
                }
            }
        }

        // ошибка, вызванная вручную
        /* if(!$errno)
          {

          }
          else
          if($errno==E_NOTICE)
          {

          }
          die(''); */
    }

    /**
     *
     */
    static function create($message) {
        self::handler(0, $message, 'none', 0);
    }

}