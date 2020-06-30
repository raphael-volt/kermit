<?php
namespace src\vo;

use src\vo\delta\Operation;

class ThreadPart extends Vo
{

    var $read_by = [];

    var $thread_id;

    var $user_id;

    /**
     *
     * @var Operation[]
     */
    var $content=NULL;

    function assign(object $object)
    {
        if ($object) {
            if (property_exists($object, "read_by") && is_string($object->read_by)) {
                $object->read_by = json_decode($object->read_by);
            }
        }
        parent::assign($object);

        if ($this->content !== NULL) {
            if(is_string($this->content)) {
                $this->content = json_decode($this->content);
            }
            foreach ($this->content as $key => $value) {
                $this->content[$key] = new Operation($value);
            }
            ;
        }
    }
}

