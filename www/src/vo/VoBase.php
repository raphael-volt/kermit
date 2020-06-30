<?php
namespace src\vo;

class VoBase
{

    function __construct($object = null) {
        if($object)
            $this->assign($object);
    }
    /**
     * 
     * @param object $object
     */
    function assign(object $object)
    {
        
        foreach ($object as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * 
     * @param boolean $skipNullValue
     * @return \stdClass
     */
    function toObject($skipNullValue = true)
    {
        $result = new \stdClass();
        foreach ($this as $key => $value) {
            if ($value === NULL && $skipNullValue)
                continue;
            $result->{$key} = $value;
        }
        return $result;
    }
}

