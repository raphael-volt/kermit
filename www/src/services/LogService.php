<?php
namespace src\services;

class LogService
{

    /**
     * 
     * @var LogService
     */
    private static $_instance;
    /**
     * 
     * @return \src\services\LogService
     */
    static  function instance() {
        if(! self::$_instance)
            self::$_instance = new LogService();
        return self::$_instance;
    }
    /**
     *
     * @var FileService
     */
    private $fs;

    private function __construct()
    {
        $this->fs = FileService::instance();
    }

    function error(...$text)
    {
        $eol = PHP_EOL . PHP_EOL;
        $text = join($eol, $text);
        file_put_contents($this->getLogFilename("errors.log"), $text . $eol, FILE_APPEND);
    }

    function getLogFilename($file)
    {
        return $this->fs->logs($file);
    }
}

