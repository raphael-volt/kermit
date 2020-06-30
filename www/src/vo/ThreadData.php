<?php
namespace src\vo;

class ThreadData extends VoBase//ThreadDataItem
{
    function assign($object) {
        parent::assign($object);
        $this->thread = new Thread($this->thread);
    }
    /**
     * 
     * @var Thread
     */
    var $thread;
    
    /**
     * 
     * @var ThreadDataItem[]
     */
    
    var $contents;
}

/*
export interface ThreadData {
    user: User
    thread: Thread
    mainContent:DeltaOperation[]
    contents:{
      user:User
      inserts: DeltaOperation[]
    }[]
  }
 */