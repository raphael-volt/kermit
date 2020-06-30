<?php
namespace src\core\http;

use src\services\DatabaseService;

class HTTPRequest
{

    var $routeName;

    var $routeId;

    var $tableName;

    var $returnType;

    var $content;
    
    var $method;
    /**
     * @var array
     */
    var $params = [];
    
    function hasParam($key) {
        return array_key_exists($key, $this->params);
    }
    function getParam($key) {
        return $this->params[$key];
    }
    private $_valid = false;

    function __construct($uri = null)
    {
        if ($uri !== null)
            $this->setUri($uri);
    }

    /**
     *
     * @return boolean
     */
    function valid()
    {
        return $this->_valid;
    }

    /**
     *
     * @param string $uri
     * @return boolean
     */
    function setUri(string $uri)
    {
        $this->_valid = false;
        foreach ([
            "routeName",
            "routeId",
            "tableName",
            "returnType"
        ] as $key) {
            $this->{$key} = null;
        }
        
        $uri = preg_replace('/(\/api\/)/', "", $uri);
        $params = parse_url($uri, PHP_URL_QUERY);
        if(strlen($params)) {
            parse_str($params, $this->params);
            $uri = explode("?", $uri)[0];
        }
        $uri = explode("/", $uri);
        $n = count($uri);
        if ($n > 0) {
            
            if ($n > 2)
                return FALSE;
            $this->routeName = $uri[0];
            if($uri[0] != "auth" && $uri[0] != "testdata" && $uri[0] != "watch") {
                $this->tableName = DatabaseService::getTableByRoute($uri[0]);
                if ($this->tableName === null)
                    return FALSE;
                $this->returnType = DatabaseService::getClassByRoute($uri[0]);
                if ($n > 1) {
                    $this->routeId = intval($uri[1]);
                }
            }
            $this->_valid = TRUE;
        }
        return $this->_valid;
    }
}

