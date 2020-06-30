<?php

/**
 * Fixed thelia class
 *
 * @FixVersion 2
 */
class Autoload
{

    /**
     *
     * @var Autoload
     */
    private static $instance;

    /**
     *
     * @return Autoload
     */
    public static function instance()
    {
        if (! self::$instance)
            self::$instance = new Autoload();

        return self::$instance;
    }

    static function join(...$parts)
    {
        return join(DIRECTORY_SEPARATOR, $parts);
    }

    protected $ns_directories = [];

    protected function __construct()
    {
        $this->addNSDirectoty(__DIR__);
    }

    function addNSDirectoty($directory)
    {
        $this->checkAdd($directory, $this->ns_directories);
    }

    /**
     *
     * @param string $directory
     * @param array $dictories
     */
    private function checkAdd(string $directory, array &$directories)
    {
        $directory = realpath($directory);
        if (array_search($directory, $directories) === false)
            $directories[] = $directory;
    }

    /**
     * Registers this instance as an autoloader.
     *
     * @param Boolean $prepend
     *            Whether to prepend the autoloader or not
     * @api
     */
    public function register($prepend = false)
    {
        spl_autoload_register(array(
            $this,
            'loadClass'
        ), true, $prepend);
    }

    /**
     *
     * @param string $class
     *            Name of the class
     */
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            require_once $file;
        }
    }

    public function findFile($class)
    {
        $ext = ".php";
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        foreach ($this->ns_directories as $dir) {
            $file = self::join($dir, $path . $ext);
            if (is_file($file))
                return $file;
        }
    }
}

Autoload::instance()->register();
?>