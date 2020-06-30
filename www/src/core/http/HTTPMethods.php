<?php
namespace src\core\http;

class HTTPMethods
{
    const GET = "GET";
    
    const POST = "POST";
    
    const PUT = "PUT";
    
    const DELETE = "DELETE";
    
    const OPTIONS = "OPTIONS";
    
    private static $_methods = [
        self::GET,
        self::POST,
        self::PUT,
        self::DELETE,
        self::OPTIONS
    ];
    
    static function is($method)
    {
        return array_search($method, self::$_methods) !== FALSE;
    }
    
    static function header()
    {
        header("Access-Control-Allow-Methods: " . join(", ", self::$_methods));
    }
    
    static function setResponseCode($code, $protocol = null)
    {
        if($protocol == null)
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
        
        $message = "";
        switch ($code) {
            case 200:
                $message = " OK";
                break;
            case 401:
                $message = " Unauthorized";
                break;
            case 400:
                $message = " Bad Request";
            default:
                ;
                break;
        }
        header("$protocol {$code}{$message}");
    }
}

