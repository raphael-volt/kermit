<?php
namespace src\services;

use src\vo\ThreadPart;

class WatchService
{

    private static $_instance;

    static function instance()
    {
        if (! self::$_instance) {
            self::$_instance = new WatchService();
        }
        return self::$_instance;
    }

    function getLoggedUsers($users = null)
    {
        $result = [];
        if(! $users)
            $users = $this->data->users;
        foreach ($users as $user) {
            $result[] = $user->id;
        }
        return $result;
    }

    const fn_user = "users-status.json";

    /**
     *
     * @var FileService
     */
    private $fs;

    private $data;

    private $saveFlag = false;

    private function __construct()
    {
        $fs = FileService::instance();
        $this->fs = $fs;
        $us = $this->getFilename();
        if (! file_exists($us)) {
            $data = new \stdClass();
            $data->users = [];
            $this->data = $data;
        } else {
            $this->data = json_decode(file_get_contents($us));
        }
        $this->saveFlag = $this->checkLatestThread();
        $this->checkUsersTime();
    }

    private function checkLatestThread()
    {
        $data = $this->data;
        $db = DatabaseService::instance();
        $threadPart = $db->getLatestThreadPart();
        $change = false;
        if (! $threadPart) {
            $threadPart = new ThreadPart();
            $threadPart->id = 0;
            $threadPart->user_id = 0;
            $threadPart->thread = 0;
            $threadPart->thread_id = 0;
        }
        foreach ([
            "thread",
            "thread_part",
            "thread_user"
        ] as $key) {
            switch ($key) {
                case "thread":
                    $value = $threadPart->thread_id;
                    break;
                case "thread_part":
                    $value = $threadPart->id;
                    break;
                case "thread_user":
                    $value = $threadPart->user_id;
                    break;
            }
            if (! property_exists($data, $key) || $data->{$key} !== $value) {
                $data->{$key} = $value;
                $change = true;
            }
        }
        if (! property_exists($data, "users_change")) {
            $change = true;
            $data->users_change = 0;
        }
        
        if (! property_exists($data, "reload")) {
            $change = true;
            $data->reload = 0;
        }
        
        return $change;
    }
    
    function setReload(bool $checkLastThread=true) {
        $this->update("reload", time());
        if($checkLastThread)
            $this->checkLatestThread();
    }

    private function findUser($id, $users)
    {
        foreach ($users as $user) {
            if ($user->id == $id)
                return $user;
        }
        return null;
    }

    private function checkUsersTime()
    {
        $users = $this->data->users;
        $saveFlag = false;
        $time = time();
        $max = 5;
        $on = [];
        foreach ($users as $user) {
            $dif = $time - $user->time;
            if ($dif >= $max) {
                $saveFlag = true;
                continue;
            }
            $on[] = $user;
        }
        $this->data->users = $on;
        if (! $this->saveFlag)
            $this->saveFlag = $saveFlag;
    }

    function setUserChange()
    {
        $this->update("users_change", time());
    }
    
    private function checkUserIsLogged($user_id, $thread_opened) {
        $users = $this->data->users;
        $user = $this->findUser($user_id, $users);
        if($user === NULL) {
            $users[] = $this->getUserStatus($user_id, time(), $thread_opened);
            $this->data->users = $users;
            $this->saveFlag = true;
        }
    }

    function setThread($value, $user_id)
    {
        $this->update("thread_user", $user_id);
        $this->update("thread", $value);
        $this->checkUserIsLogged($user_id, $value);
        return $this;
    }

    function setThreadPart($value, $user_id)
    {
        $this->update("thread_user", $user_id);
        return $this->update("thread_part", $value);
    }

    private function update($key, $value)
    {
        $current = $this->data->{$key};
        if ($current != $value) {
            $this->data->{$key} = $value;
            $this->saveFlag = true;
        }
        return $this;
    }

    private function getFilename()
    {
        return $this->fs->assets(self::fn_user);
    }

    private function getUserStatus($id, $time, $thread_opened = 0)
    {
        $result = new \stdClass();
        $result->id = $id;
        $result->time = $time;
        if ($thread_opened > 0)
            $result->thread_opened = $thread_opened;
        return $result;
    }

    private function _initDif($dif, $data, $populateUsers = true)
    {
        foreach ([
            "thread",
            "thread_part",
            "thread_user",
            "users_change"
        ] as $key) {
            $dif->{$key} = $data->{$key};
        }
        if ($populateUsers) {
            $dif->users = $this->getLoggedUsers($data->users);
        }
    }

    function check($value)
    {
        $user_id = $value->user_id;
        $status = $value->status;
        if (! property_exists($value, "thread_opened")) {
            $value->thread_opened = 0;
        }
        $thread_opened = $value->thread_opened;

        $data = $this->data;
        $dif = new \stdClass();
        $activeThreads = new \stdClass();

        $this->_initDif($dif, $data, false);

        $users = $data->users;
        $time = time();

        $currentUser = $this->findUser($user_id, $users);

        if (! $currentUser) {
            $currentUser = $this->getUserStatus($user_id, $time, $thread_opened);
            if ($status != 'off')
                $users[] = $currentUser;
        } else {
            $currentUser->time = $time;
            $currentUser->thread_opened = $thread_opened;
        }
        foreach ($users as $user) {
            if (property_exists($user, "thread_opened") && $value->thread_opened > 0) {
                $activeThreads->{$user->thread_opened}[] = $user->id;
            }
            $dif->users[] = $user->id;
        }
        $dif->active_threads = $activeThreads;

        $data->users = $users;
        $this->saveFlag = true;
        return $dif;
    }

    function save()
    {
        if ($this->saveFlag) {
            file_put_contents($this->getFilename(), json_encode($this->data, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
            $this->saveFlag = false;
        }
    }
}

